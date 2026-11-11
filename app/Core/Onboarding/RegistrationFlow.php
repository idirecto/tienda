<?php

declare(strict_types=1);

namespace Tienda\Core\Onboarding;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use Tienda\Core\Config;
use Tienda\Core\Contract\ContractConfigurationException;
use Tienda\Core\Contract\ContractPdfGenerator;
use Tienda\Core\Contract\ContractTemplate;
use Tienda\Core\Contract\ContractTemplateException;
use Tienda\Core\Database;
use Tienda\Core\Logger;
use Tienda\Core\Mail\EmailApiClientInterface;
use Tienda\Core\Mail\RegistrationMailer;
use Tienda\Core\Registration;
use Tienda\Core\ValidationException;
use Tienda\Models\ContractAcceptance;
use Tienda\Models\ContractVersion;
use Tienda\Models\RegistrationVerification;
use Tienda\Models\StoreRegistration;

/**
 * Flujo completo de alta de una tienda:
 *
 *  1. Comprueba configuracion, plantilla y motor PDF.
 *  2. Genera el PDF individualizado y su hash.
 *  3. Crea tienda pendiente, usuario inactivo, aceptacion y codigo en UNA
 *     transaccion. El correo se envia dentro de la misma transaccion: si falla,
 *     se revierte todo y se borra el PDF huerfano.
 *  4. Verifica el codigo y activa tienda y usuario de forma idempotente.
 *
 * No registra correo, nombre, IP ni codigo en los logs.
 */
final class RegistrationFlow
{
    public const CONFIG_INCOMPLETE = 'config_incomplete';
    public const TEMPLATE_INVALID = 'template_invalid';
    public const ACCOUNT_INCOMPLETE = 'account_incomplete';
    public const REGISTRATION_CONFLICT = 'registration_conflict';
    public const MAIL_FAILED = 'mail_failed';
    public const NOT_FOUND = 'not_found';
    public const INVALID_CODE = 'invalid_code';
    public const EXPIRED = 'expired';
    public const LOCKED = 'locked';
    public const NOT_PENDING = 'not_pending';
    public const RESEND_COOLDOWN = 'resend_cooldown';
    public const RESEND_LIMIT = 'resend_limit';
    public const INTERNAL = 'internal';

    private EmailApiClientInterface $mail;
    private VerificationService $verification;
    private ContractTemplate $template;
    private ContractPdfGenerator $pdf;
    private \Closure $clock;
    private string $contractsDir;
    private string $generatedDir;

    public function __construct(
        EmailApiClientInterface $mail,
        ?VerificationService $verification = null,
        ?ContractTemplate $template = null,
        ?ContractPdfGenerator $pdf = null,
        ?\Closure $clock = null
    ) {
        $this->mail = $mail;
        $this->verification = $verification ?? new VerificationService();
        $this->template = $template ?? new ContractTemplate();
        $this->pdf = $pdf ?? new ContractPdfGenerator();
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->contractsDir = rtrim(TIENDA_BASE . '/storage/contracts', '/\\');
        $this->generatedDir = rtrim((string) Config::get(
            'registration.contract.generated_dir',
            $this->contractsDir . '/generated'
        ), '/\\');
    }

    /**
     * Problemas que impiden el alta. Vacio = flujo listo.
     *
     * @return list<string>
     */
    public static function readinessProblems(?EmailApiClientInterface $mail = null): array
    {
        $problems = [];

        if (!Registration::completionEnabled()) {
            $problems[] = 'activacion cerrada';
        }
        if (!Registration::onboardingSchemaReady()) {
            $problems[] = 'esquema pendiente';
        }

        $contract = ContractVersion::current(
            (string) Config::get('registration.contract.document_key', 'access_web')
        );
        if ($contract === null) {
            $problems[] = 'contrato vigente no disponible';
        } else {
            $template = new ContractTemplate();
            try {
                $template->assertConfigurationReady();
                $template->sourcePath($contract);
            } catch (ContractConfigurationException $e) {
                $problems[] = 'configuracion del contrato incompleta';
            } catch (ContractTemplateException $e) {
                $problems[] = 'plantilla HTML no disponible';
            }
        }

        if (!ContractPdfGenerator::isAvailable()) {
            $problems[] = 'motor PDF no disponible';
        }

        $mail ??= RegistrationMailer::create();
        if (!$mail->isEnabled()) {
            $problems[] = 'envio de correo no disponible';
        }

        $from = trim((string) Config::get('email.from', ''));
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $problems[] = 'remitente de correo no configurado';
        }

        return array_values(array_unique($problems));
    }

    /**
     * Alta completa: genera PDF, crea registros pendientes y envia el codigo.
     *
     * @param array $account  Fila de `tiendas` del mayorista
     * @param array $representative first_name,last_name,role,phone
     * @param array $request  ip, user_agent, base_url
     * @return array{uuid:string,registration_id:int,store_id:int,user_id:int,slug:string,pdf_path:string,pdf_sha256:string,template_sha256:string}
     */
    public function register(
        array $account,
        string $email,
        string $panelPassword,
        ?string $slug,
        array $representative,
        array $request
    ): array {
        $this->assertReady();

        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw RegistrationException::because(self::INTERNAL, 'El correo del alta no es valido.');
        }
        if (strlen($panelPassword) < 8) {
            throw RegistrationException::because(self::INTERNAL, 'La contrasena del panel no es valida.');
        }

        $accountId = (int) ($account['id'] ?? 0);
        if ($accountId <= 0) {
            throw RegistrationException::because(self::ACCOUNT_INCOMPLETE, 'La cuenta del mayorista no es valida.');
        }

        $contract = ContractVersion::current(
            (string) Config::get('registration.contract.document_key', 'access_web')
        );
        if ($contract === null) {
            throw RegistrationException::because(self::CONFIG_INCOMPLETE, 'No hay un contrato vigente disponible.');
        }

        $client = $this->clientData($account, $email, $representative);
        $now = $this->nowUtc();

        try {
            $rendered = $this->template->render($contract, $client, $this->nowLocal());
        } catch (ContractConfigurationException) {
            throw RegistrationException::because(
                self::CONFIG_INCOMPLETE,
                'Faltan datos legales obligatorios del contrato; el alta permanece bloqueada.'
            );
        } catch (ContractTemplateException) {
            throw RegistrationException::because(
                self::TEMPLATE_INVALID,
                'No se pudo generar el contrato correctamente; el alta permanece bloqueada.'
            );
        }

        $uuid = self::uuid4();
        $pdfRelative = 'generated/' . $uuid . '.pdf';
        $pdfAbsolute = $this->generatedDir . '/' . $uuid . '.pdf';
        $issued = $this->verification->issue($now);
        $code = $issued['code'];
        $verificationUrl = $this->verificationUrl((string) ($request['base_url'] ?? ''), $uuid);

        try {
            $this->pdf->generate($rendered['html'], $pdfAbsolute, [
                'title'  => 'Contrato de acceso y uso de la plataforma web',
                'subject'=> 'Contrato individualizado del cliente ' . $client['legal_name'],
            ]);
            $pdfHash = hash_file('sha256', $pdfAbsolute);
            if (!is_string($pdfHash) || strlen($pdfHash) !== 64) {
                throw new \RuntimeException('No se pudo calcular el hash del PDF.');
            }

            $message = $this->verificationMessage(
                $client,
                $email,
                $code,
                $verificationUrl,
                $this->verification->ttlSeconds()
            );

            $result = $this->transaction(function () use (
                $account,
                $email,
                $panelPassword,
                $slug,
                $representative,
                $request,
                $contract,
                $rendered,
                $client,
                $uuid,
                $pdfRelative,
                $pdfAbsolute,
                $pdfHash,
                $issued,
                $now,
                $message
            ): array {
                try {
                    $ids = Registration::registerPending($account, $email, $panelPassword, $slug);
                } catch (ValidationException $e) {
                    throw RegistrationException::because(
                        self::REGISTRATION_CONFLICT,
                        'Esa cuenta ya tiene una tienda o un alta pendiente.',
                        $e
                    );
                }

                $registrationId = StoreRegistration::create([
                    'public_uuid'                => $uuid,
                    'store_id'                   => $ids['store_id'],
                    'store_user_id'              => $ids['user_id'],
                    'id_tienda_idirecto'         => (int) $account['id'],
                    'email'                      => $email,
                    'representative_first_name'  => $representative['first_name'],
                    'representative_last_name'   => $representative['last_name'],
                    'representative_role'        => $representative['role'],
                    'representative_phone'       => $representative['phone'],
                    'contract_version_id'        => (int) $contract['id'],
                    'status'                     => StoreRegistration::STATUS_PENDING,
                    'expires_at'                 => $this->pendingExpiry($now)->format('Y-m-d H:i:s'),
                ]);

                $acceptanceId = ContractAcceptance::record([
                    'event_uuid'                => self::uuid4(),
                    'registration_id'           => $registrationId,
                    'store_id'                  => $ids['store_id'],
                    'client_account_id'         => (int) $account['id'],
                    'contract_version_id'       => (int) $contract['id'],
                    'template_sha256'           => strtolower((string) $contract['template_sha256']),
                    'source_template_sha256'    => $rendered['template_sha256'],
                    'pdf_path'                  => $pdfRelative,
                    'pdf_sha256'                => $pdfHash,
                    'representative_first_name' => $representative['first_name'],
                    'representative_last_name'  => $representative['last_name'],
                    'representative_role'       => $representative['role'],
                    'representative_email'      => $email,
                    'company_legal_name'        => $client['legal_name'],
                    'company_tax_id'            => $client['tax_id'],
                    'company_address'           => $client['address'],
                    'acceptance_text_sha256'    => $rendered['acceptance_text_sha256'],
                    'accepted_at_utc'           => $now->format('Y-m-d H:i:s.u'),
                    'timezone'                  => (string) Config::get('app.timezone', 'Europe/Madrid'),
                    'ip_address'                => $this->requestIp((string) ($request['ip'] ?? '')),
                    'user_agent'                => $this->requestUserAgent((string) ($request['user_agent'] ?? '')),
                ]);

                $verificationId = RegistrationVerification::createForRegistration(
                    $registrationId,
                    $issued['hash'],
                    $issued['expires_at']->format('Y-m-d H:i:s'),
                    $this->verification->maxAttempts(),
                    $now->format('Y-m-d H:i:s')
                );

                $sent = $this->mail->sendWithPdf($message, $pdfAbsolute, $this->pdfName($ids['slug']));
                if (!($sent['ok'] ?? false)) {
                    throw RegistrationException::because(
                        self::MAIL_FAILED,
                        'No se pudo enviar el correo de confirmacion. No se ha creado ninguna cuenta.'
                    );
                }

                StoreRegistration::markEmailSent($registrationId, $now->format('Y-m-d H:i:s'));

                return [
                    'registration_id' => $registrationId,
                    'acceptance_id'   => $acceptanceId,
                    'verification_id' => $verificationId,
                    'store_id'        => $ids['store_id'],
                    'user_id'         => $ids['user_id'],
                    'slug'            => $ids['slug'],
                ];
            });

            Logger::info('acceso', 'Alta pendiente de verificacion', [
                'registration_uuid' => $uuid,
                'store_id'          => $result['store_id'],
                'contract_version'  => (string) $contract['version_label'],
                'pdf_sha256'        => $pdfHash,
            ]);

            return [
                'uuid'            => $uuid,
                'registration_id' => $result['registration_id'],
                'store_id'        => $result['store_id'],
                'user_id'         => $result['user_id'],
                'slug'            => $result['slug'],
                'pdf_path'        => $pdfRelative,
                'pdf_sha256'      => $pdfHash,
                'template_sha256' => $rendered['template_sha256'],
            ];
        } catch (RegistrationException $e) {
            @unlink($pdfAbsolute);
            Logger::warning('acceso', 'Alta de tienda no completada', [
                'motivo' => $e->reason(),
            ]);
            throw $e;
        } catch (Throwable $e) {
            @unlink($pdfAbsolute);
            Logger::error('acceso', 'Alta de tienda no completada', [
                'motivo'    => self::INTERNAL,
                'excepcion' => get_class($e),
            ]);
            throw RegistrationException::because(
                self::INTERNAL,
                'No se pudo completar el alta. No se ha creado ninguna cuenta.',
                $e
            );
        }
    }

    /**
     * Verifica el codigo y activa tienda + usuario en una transaccion.
     *
     * Es idempotente: si el alta ya esta verificada devuelve
     * `already_verified` sin volver a consumir ni activar nada.
     *
     * @return array{outcome:string,registration:array,store:?array,attempts_left:?int}
     */
    public function verify(string $uuid, string $code): array
    {
        return $this->transaction(function () use ($uuid, $code): array {
            $registration = $this->lockedRegistration($uuid);
            if ($registration === null) {
                throw RegistrationException::because(self::NOT_FOUND, 'El alta no existe.');
            }

            if ((string) $registration['status'] === StoreRegistration::STATUS_VERIFIED) {
                return [
                    'outcome'      => 'already_verified',
                    'registration' => $registration,
                    'store'        => $this->storeRow((int) $registration['store_id']),
                    'attempts_left'=> null,
                ];
            }
            if ((string) $registration['status'] !== StoreRegistration::STATUS_PENDING) {
                return [
                    'outcome'      => self::NOT_PENDING,
                    'registration' => $registration,
                    'store'        => $this->storeRow((int) $registration['store_id']),
                    'attempts_left'=> null,
                ];
            }

            $now = $this->nowUtc();
            if ($this->expired($registration['expires_at'] ?? null, $now)) {
                StoreRegistration::markExpired((int) $registration['id']);
                return [
                    'outcome'      => self::EXPIRED,
                    'registration' => $registration,
                    'store'        => $this->storeRow((int) $registration['store_id']),
                    'attempts_left'=> null,
                ];
            }

            $verification = Database::first(
                'SELECT *
                   FROM mt_registration_verifications
                  WHERE registration_id = :registration_id
                    AND consumed_at IS NULL
                    AND superseded_at IS NULL
                  ORDER BY id DESC
                  LIMIT 1
                  FOR UPDATE',
                ['registration_id' => (int) $registration['id']]
            );
            if ($verification === null) {
                return [
                    'outcome'      => self::INVALID_CODE,
                    'registration' => $registration,
                    'store'        => $this->storeRow((int) $registration['store_id']),
                    'attempts_left'=> 0,
                ];
            }

            $expiresAt = $this->parseUtc((string) $verification['expires_at']);
            if ($expiresAt === null) {
                throw RegistrationException::because(self::INTERNAL, 'La caducidad del codigo no es valida.');
            }

            $result = $this->verification->verify(
                $code,
                (string) $verification['code_hash'],
                (int) $verification['attempts'],
                $verification['consumed_at'] !== null ? (string) $verification['consumed_at'] : null,
                $verification['superseded_at'] !== null ? (string) $verification['superseded_at'] : null,
                $expiresAt,
                $now,
                (int) $verification['max_attempts']
            );

            if ($result === VerificationService::INVALID) {
                RegistrationVerification::recordFailedAttempt((int) $verification['id']);
                $attempts = (int) $verification['attempts'] + 1;
                $left = max(0, (int) $verification['max_attempts'] - $attempts);

                return [
                    'outcome'      => self::INVALID_CODE,
                    'registration' => $registration,
                    'store'        => null,
                    'attempts_left'=> $left,
                ];
            }
            if ($result === VerificationService::EXPIRED) {
                return [
                    'outcome'      => self::EXPIRED,
                    'registration' => $registration,
                    'store'        => null,
                    'attempts_left'=> null,
                ];
            }
            if ($result === VerificationService::LOCKED) {
                return [
                    'outcome'      => self::LOCKED,
                    'registration' => $registration,
                    'store'        => null,
                    'attempts_left'=> 0,
                ];
            }
            if ($result !== VerificationService::VALID) {
                return [
                    'outcome'      => self::INVALID_CODE,
                    'registration' => $registration,
                    'store'        => null,
                    'attempts_left'=> null,
                ];
            }

            $consumed = Database::execute(
                'UPDATE mt_registration_verifications
                    SET consumed_at = :consumed_at
                  WHERE id = :id
                    AND consumed_at IS NULL
                    AND superseded_at IS NULL',
                ['consumed_at' => $now->format('Y-m-d H:i:s'), 'id' => (int) $verification['id']]
            );
            if ($consumed !== 1) {
                return [
                    'outcome'      => self::INVALID_CODE,
                    'registration' => $registration,
                    'store'        => null,
                    'attempts_left'=> 0,
                ];
            }

            StoreRegistration::markVerified((int) $registration['id'], $now->format('Y-m-d H:i:s'));
            Database::execute(
                'UPDATE mt_stores SET status = :status WHERE id = :id',
                ['status' => Registration::STORE_ACTIVE, 'id' => (int) $registration['store_id']]
            );
            Database::execute(
                'UPDATE mt_store_users
                    SET active = 1
                  WHERE id = :id
                    AND store_id = :store_id',
                ['id' => (int) $registration['store_user_id'], 'store_id' => (int) $registration['store_id']]
            );

            $store = $this->storeRow((int) $registration['store_id']);
            $userActive = (int) (Database::scalar(
                'SELECT active FROM mt_store_users WHERE id = :id AND store_id = :store_id LIMIT 1',
                ['id' => (int) $registration['store_user_id'], 'store_id' => (int) $registration['store_id']]
            ) ?? 0);

            if ($store === null
                || (int) $store['status'] !== Registration::STORE_ACTIVE
                || $userActive !== 1) {
                throw RegistrationException::because(
                    self::INTERNAL,
                    'No se pudo activar la tienda y el usuario.'
                );
            }

            Logger::info('acceso', 'Alta verificada y tienda activada', [
                'registration_uuid' => $uuid,
                'store_id'          => (int) $registration['store_id'],
            ]);

            return [
                'outcome'      => 'verified',
                'registration' => $registration,
                'store'        => $store,
                'attempts_left'=> null,
            ];
        });
    }

    /**
     * Reenvia el codigo (y el mismo PDF) respetando espera y cuota.
     *
     * @return array{outcome:string,registration:array,sent_at:string}
     */
    public function resend(string $uuid, array $request): array
    {
        $this->assertMailReady();

        return $this->transaction(function () use ($uuid, $request): array {
            $registration = $this->lockedRegistration($uuid);
            if ($registration === null) {
                throw RegistrationException::because(self::NOT_FOUND, 'El alta no existe.');
            }
            if ((string) $registration['status'] === StoreRegistration::STATUS_VERIFIED) {
                return [
                    'outcome'      => 'already_verified',
                    'registration' => $registration,
                    'sent_at'      => '',
                ];
            }
            if ((string) $registration['status'] !== StoreRegistration::STATUS_PENDING) {
                throw RegistrationException::because(self::NOT_PENDING, 'El alta ya no esta pendiente.');
            }

            $now = $this->nowUtc();
            if ($this->expired($registration['expires_at'] ?? null, $now)) {
                StoreRegistration::markExpired((int) $registration['id']);
                throw RegistrationException::because(self::EXPIRED, 'El plazo del alta ha caducado.');
            }

            $acceptance = ContractAcceptance::latestForRegistration((int) $registration['id']);
            if ($acceptance === null) {
                throw RegistrationException::because(self::INTERNAL, 'Falta la evidencia de aceptacion del alta.');
            }
            $pdfAbsolute = $this->absolutePdfPath((string) $acceptance['pdf_path']);
            if ($pdfAbsolute === null) {
                throw RegistrationException::because(self::INTERNAL, 'El contrato firmado ya no esta disponible.');
            }
            $pdfHash = hash_file('sha256', $pdfAbsolute);
            if (!is_string($pdfHash)
                || !hash_equals(strtolower((string) $acceptance['pdf_sha256']), strtolower($pdfHash))) {
                throw RegistrationException::because(self::INTERNAL, 'El contrato firmado no supera la comprobacion de integridad.');
            }

            $latest = RegistrationVerification::latestForRegistration((int) $registration['id']);
            $lastSentAt = $latest !== null && ($latest['sent_at'] ?? '') !== ''
                ? $this->parseUtc((string) $latest['sent_at'])
                : null;
            $since = $now->modify('-' . $this->verification->resendWindowSeconds() . ' seconds')
                ->format('Y-m-d H:i:s');
            $sends = RegistrationVerification::sendsSince((int) $registration['id'], $since);

            if ($sends >= $this->verification->maxResendsPerWindow()) {
                throw RegistrationException::because(self::RESEND_LIMIT, 'Se ha alcanzado el maximo de reenvios.');
            }
            if ($lastSentAt !== null
                && $now->getTimestamp() < ($lastSentAt->getTimestamp() + $this->verification->resendCooldownSeconds())) {
                throw RegistrationException::because(self::RESEND_COOLDOWN, 'Espera antes de pedir otro codigo.');
            }

            $issued = $this->verification->issue($now);
            $url = $this->verificationUrl((string) ($request['base_url'] ?? ''), $uuid);
            $client = [
                'legal_name'     => (string) $acceptance['company_legal_name'],
                'tax_id'         => (string) $acceptance['company_tax_id'],
                'address'        => (string) $acceptance['company_address'],
                'representative' => trim(
                    (string) $acceptance['representative_first_name'] . ' '
                    . (string) $acceptance['representative_last_name']
                ),
                'contact'        => (string) $acceptance['representative_email'],
                'email'          => (string) $registration['email'],
            ];
            $message = $this->verificationMessage(
                $client,
                (string) $registration['email'],
                $issued['code'],
                $url,
                $this->verification->ttlSeconds()
            );

            RegistrationVerification::supersedeActive(
                (int) $registration['id'],
                $now->format('Y-m-d H:i:s')
            );
            RegistrationVerification::createForRegistration(
                (int) $registration['id'],
                $issued['hash'],
                $issued['expires_at']->format('Y-m-d H:i:s'),
                $this->verification->maxAttempts(),
                $now->format('Y-m-d H:i:s')
            );

            $sent = $this->mail->sendWithPdf(
                $message,
                $pdfAbsolute,
                $this->pdfName((string) $this->storeSlug((int) $registration['store_id']))
            );
            if (!($sent['ok'] ?? false)) {
                throw RegistrationException::because(
                    self::MAIL_FAILED,
                    'No se pudo enviar el correo de confirmacion. El codigo anterior sigue vigente.'
                );
            }

            StoreRegistration::markEmailSent((int) $registration['id'], $now->format('Y-m-d H:i:s'));

            Logger::info('acceso', 'Codigo de alta reenviado', [
                'registration_uuid' => $uuid,
                'store_id'          => (int) $registration['store_id'],
            ]);

            return [
                'outcome'      => 'resent',
                'registration' => $registration,
                'sent_at'      => $now->format('Y-m-d H:i:s'),
            ];
        });
    }

    /** @return array|null Registro publico sin datos personales de mas. */
    public static function findByUuid(string $uuid): ?array
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) !== 1) {
            return null;
        }

        return StoreRegistration::findByUuid(strtolower($uuid));
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    private function assertReady(): void
    {
        $problems = self::readinessProblems($this->mail);
        if ($problems !== []) {
            throw RegistrationException::because(
                self::CONFIG_INCOMPLETE,
                'El alta no esta disponible: ' . implode(', ', $problems) . '.'
            );
        }
    }

    private function assertMailReady(): void
    {
        if (!$this->mail->isEnabled()) {
            throw RegistrationException::because(self::CONFIG_INCOMPLETE, 'El envio de correo no esta disponible.');
        }
        $from = trim((string) Config::get('email.from', ''));
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw RegistrationException::because(self::CONFIG_INCOMPLETE, 'Falta configurar el remitente del correo.');
        }
    }

    /**
     * @return array{legal_name:string,tax_id:string,address:string,representative:string,contact:string,email:string}
     */
    private function clientData(array $account, string $email, array $representative): array
    {
        $legalName = trim((string) ($account['nombre_sociedad'] ?? ''));
        if ($legalName === '') {
            $legalName = trim((string) ($account['nombre'] ?? ''));
        }
        $taxId = trim((string) ($account['nif_cif'] ?? ''));
        $address = trim(implode(', ', array_filter([
            trim((string) ($account['direccion'] ?? '')),
            trim(trim((string) ($account['cp'] ?? '')) . ' ' . trim((string) ($account['poblacion'] ?? ''))),
            trim((string) ($account['id_provincia'] ?? '')),
        ], static fn (string $value): bool => $value !== '')));

        $firstName = trim((string) ($representative['first_name'] ?? ''));
        $lastName = trim((string) ($representative['last_name'] ?? ''));
        $role = trim((string) ($representative['role'] ?? ''));
        $phone = trim((string) ($representative['phone'] ?? ''));

        if ($legalName === '' || $taxId === '' || $address === '') {
            throw RegistrationException::because(
                self::ACCOUNT_INCOMPLETE,
                'La cuenta del mayorista no tiene razon social, NIF o domicilio completos.'
            );
        }
        if ($firstName === '' || $lastName === '' || $role === '') {
            throw RegistrationException::because(
                self::ACCOUNT_INCOMPLETE,
                'Faltan datos de la persona que realiza el alta.'
            );
        }

        return [
            'legal_name'     => $legalName,
            'tax_id'         => $taxId,
            'address'        => $address,
            'representative' => trim($firstName . ' ' . $lastName . ($role !== '' ? ' (' . $role . ')' : '')),
            'contact'        => $email . ($phone !== '' ? ' · ' . $phone : ''),
            'email'          => $email,
        ];
    }

    private function verificationMessage(
        array $client,
        string $email,
        string $code,
        string $url,
        int $ttlSeconds
    ): array {
        $safeName = $this->e((string) $client['representative']);
        $safeUrl = $this->e($url);
        $codeDisplay = trim(chunk_split($code, 4, ' '));
        $safeCode = $this->e($codeDisplay);
        $minutes = (int) ceil($ttlSeconds / 60);
        $safeFromName = $this->e((string) Config::get('email.from_name', 'Tienda'));

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111;line-height:1.5">'
            . '<h1 style="font-size:20px">Confirma tu correo</h1>'
            . '<p>Hola ' . $safeName . ',</p>'
            . '<p>Hemos recibido tu solicitud para activar la tienda. Introduce este codigo en la pagina de verificacion:</p>'
            . '<p style="font-size:24px;letter-spacing:3px;font-weight:bold">' . $safeCode . '</p>'
            . '<p><a href="' . $safeUrl . '">Confirmar el correo y activar la tienda</a></p>'
            . '<p>El codigo caduca en ' . $minutes . ' minutos. Si no has solicitado el alta, ignora este mensaje.</p>'
            . '<p>Adjuntamos el contrato de acceso y uso de la plataforma que has aceptado.</p>'
            . '<p style="color:#555;font-size:12px">' . $safeFromName . '</p>'
            . '</div>';

        $message = [
            'from'      => trim((string) Config::get('email.from', '')),
            'from_name' => (string) Config::get('email.from_name', 'Tienda'),
            'to'        => [[
                'email' => $email,
                'name'  => (string) $client['representative'],
            ]],
            'subject'   => 'Confirma tu correo para activar tu tienda',
            'html'      => $html,
            'host'      => $this->origin($url),
        ];

        $replyTo = trim((string) Config::get('email.reply_to', ''));
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $message['reply_to'] = $replyTo;
            $message['reply_name'] = (string) Config::get('email.from_name', 'Tienda');
        }

        return $message;
    }

    private function lockedRegistration(string $uuid): ?array
    {
        if (self::findByUuid($uuid) === null) {
            return null;
        }

        return Database::first(
            'SELECT * FROM mt_store_registrations WHERE public_uuid = :uuid LIMIT 1 FOR UPDATE',
            ['uuid' => strtolower($uuid)]
        );
    }

    private function storeRow(int $storeId): ?array
    {
        if ($storeId <= 0) {
            return null;
        }

        return Database::first(
            'SELECT id, slug, name, status FROM mt_stores WHERE id = :id LIMIT 1',
            ['id' => $storeId]
        );
    }

    private function storeSlug(int $storeId): string
    {
        $slug = Database::scalar('SELECT slug FROM mt_stores WHERE id = :id LIMIT 1', ['id' => $storeId]);

        return $slug === null ? 'tienda' : (string) $slug;
    }

    private function absolutePdfPath(string $relative): ?string
    {
        $relative = str_replace('\\', '/', trim($relative));
        if ($relative === ''
            || str_starts_with($relative, '/')
            || preg_match('#(^|/)\.\.(/|$)#', $relative) === 1) {
            return null;
        }

        $base = realpath($this->contractsDir);
        if ($base === false) {
            return null;
        }
        $candidate = realpath($base . '/' . ltrim($relative, '/'));
        $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($candidate === false
            || !str_starts_with($candidate, $prefix)
            || !is_file($candidate)
            || !is_readable($candidate)) {
            return null;
        }

        return $candidate;
    }

    private function verificationUrl(string $baseUrl, string $uuid): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            $baseUrl = rtrim((string) Config::get('app.url', ''), '/');
        }
        if ($baseUrl !== '') {
            $path = parse_url($baseUrl, PHP_URL_PATH);
            if (is_string($path) && str_contains($path, '/registro/verificar/')) {
                $baseUrl = substr($baseUrl, 0, (int) strpos($baseUrl, '/registro/verificar/'));
            }
        }
        if ($baseUrl === '') {
            $baseUrl = 'https://' . (string) Config::get('registration.contract.public_host', 'localhost');
        }

        return rtrim($baseUrl, '/') . '/registro/verificar/' . $uuid;
    }

    /** Esquema + host (+ puerto) para el campo `host` de la API de correo. */
    private function origin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return $url;
        }

        $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        return $origin;
    }

    private function pdfName(string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9-]+/i', '-', $slug) ?? 'tienda';
        $slug = trim($slug, '-_');

        return 'contrato-acceso-' . ($slug !== '' ? $slug : 'tienda') . '.pdf';
    }

    private function requestIp(string $ip): string
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            return $ip;
        }

        return '0.0.0.0';
    }

    private function requestUserAgent(string $userAgent): string
    {
        $userAgent = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $userAgent) ?? '');
        if ($userAgent === '') {
            return 'desconocido';
        }

        return mb_substr($userAgent, 0, 512);
    }

    private function pendingExpiry(DateTimeImmutable $now): DateTimeImmutable
    {
        $ttl = max(600, (int) Config::get('registration.verification.pending_ttl_seconds', 604800));

        return $now->modify('+' . $ttl . ' seconds');
    }

    private function expired(mixed $expiresAt, DateTimeImmutable $now): bool
    {
        if ($expiresAt === null || (string) $expiresAt === '') {
            return false;
        }
        $date = $this->parseUtc((string) $expiresAt);

        return $date !== null && $now >= $date;
    }

    private function parseUtc(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }
    }

    private function nowUtc(): DateTimeImmutable
    {
        $now = ($this->clock)();

        return $now->setTimezone(new DateTimeZone('UTC'));
    }

    private function nowLocal(): DateTimeImmutable
    {
        return $this->nowUtc()->setTimezone(new DateTimeZone((string) Config::get('app.timezone', 'Europe/Madrid')));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Transaccion con soporte de savepoints si la prueba ya abrio una.
     */
    private function transaction(callable $fn): mixed
    {
        $pdo = Database::pdo();

        if ($pdo->inTransaction()) {
            $savepoint = 'reg_' . bin2hex(random_bytes(6));
            $pdo->exec('SAVEPOINT ' . $savepoint);
            try {
                $result = $fn();
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                return $result;
            } catch (Throwable $e) {
                try {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                } catch (Throwable) {
                    // Si el savepoint ya no existe, se propaga el error original.
                }
                throw $e;
            }
        }

        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
