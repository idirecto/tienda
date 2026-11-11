<?php

declare(strict_types=1);

namespace Tienda\Core\Mail;

use Tienda\Core\Config;

/**
 * Cliente HTTP de la API interna de correo con adjuntos.
 *
 * No configura SMTP ni conoce el proveedor final. Solo llama al endpoint
 * existente `/send-email-adjunto` con Basic Auth. La integracion real esta
 * desactivada por defecto y nunca registra URL con credenciales, cuerpo,
 * PDF ni codigo de verificacion.
 */
final class EmailApiClient implements EmailApiClientInterface
{
    private bool $enabled;
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $endpoint;
    private int $connectTimeout;
    private int $timeout;
    private int $maxAttachmentBytes;

    public function __construct(?array $config = null)
    {
        $config ??= (array) Config::get('email.api', []);
        $this->enabled = (bool) ($config['enabled'] ?? false);
        $this->baseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->endpoint = trim((string) ($config['endpoint'] ?? 'send-email-adjunto'), '/');
        $this->connectTimeout = max(1, (int) ($config['connect_timeout'] ?? 5));
        $this->timeout = max(2, (int) ($config['timeout'] ?? 20));
        $this->maxAttachmentBytes = max(1, (int) ($config['max_attachment_bytes'] ?? 10485760));
    }

    public function isEnabled(): bool
    {
        if (!$this->enabled
            || $this->baseUrl === ''
            || $this->username === ''
            || $this->password === ''
            || $this->endpoint === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($this->baseUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        // En produccion no se admite enviar credenciales por HTTP plano.
        if ($scheme !== 'https' && strtolower((string) Config::get('app.env', 'local')) === 'production') {
            return false;
        }

        return function_exists('curl_init');
    }

    public function sendWithPdf(array $message, string $pdfPath, string $pdfName): array
    {
        if (!$this->isEnabled()) {
            return $this->failure('La integracion de correo esta desactivada o incompleta.');
        }

        try {
            $payload = $this->buildPayload($message, $pdfPath, $pdfName);
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\Throwable) {
            return $this->failure('No se pudo preparar el correo.');
        }

        $ch = curl_init($this->baseUrl . '/' . $this->endpoint);
        if ($ch === false) {
            return $this->failure('No se pudo iniciar la conexion con la API de correo.');
        }

        $options = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $this->username . ':' . $this->password,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_USERAGENT      => 'IDirecto-Tienda/1.0',
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
        } elseif (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $curlError !== '') {
            return [
                'ok'          => false,
                'http_status' => $status,
                'error'       => 'No se pudo conectar con la API de correo.',
            ];
        }

        $decoded = json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            return [
                'ok'          => false,
                'http_status' => $status,
                'error'       => 'La API de correo devolvio una respuesta no valida.',
            ];
        }

        if (($decoded['status'] ?? '') !== 'ok') {
            return [
                'ok'          => false,
                'http_status' => $status,
                'error'       => 'La API de correo no ha confirmado el envio.',
            ];
        }

        return ['ok' => true, 'http_status' => $status, 'error' => null];
    }

    /**
     * @param array<string,mixed> $message
     * @return array<string,mixed>
     */
    private function buildPayload(array $message, string $pdfPath, string $pdfName): array
    {
        $from = strtolower(trim((string) ($message['from'] ?? '')));
        $subject = $this->headerSafe((string) ($message['subject'] ?? ''));
        $html = (string) ($message['html'] ?? '');
        $host = trim((string) ($message['host'] ?? ''));

        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Remitente no valido.');
        }
        if ($subject === '' || $html === '' || $host === '') {
            throw new \InvalidArgumentException('Correo incompleto.');
        }

        $recipients = [];
        foreach ((array) ($message['to'] ?? []) as $recipient) {
            $email = is_array($recipient) ? (string) ($recipient['email'] ?? '') : (string) $recipient;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Destinatario no valido.');
            }
            $recipients[] = [
                'email' => $email,
                'name'  => is_array($recipient) ? (string) ($recipient['name'] ?? '') : '',
            ];
        }
        if ($recipients === []) {
            throw new \InvalidArgumentException('Falta el destinatario.');
        }

        $attachment = $this->readPdfAttachment($pdfPath, $pdfName);
        $payload = [
            'from'        => $from,
            'from-name'   => trim((string) ($message['from_name'] ?? '')),
            'to'          => $recipients,
            'subject'     => $subject,
            'html'        => $html,
            'host'        => $host,
            'attachments' => [$attachment],
        ];

        $replyTo = strtolower(trim((string) ($message['reply_to'] ?? '')));
        if ($replyTo !== '') {
            if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Reply-To no valido.');
            }
            $payload['reply'] = $replyTo;
            $payload['reply-name'] = trim((string) ($message['reply_name'] ?? ''));
        }

        return $payload;
    }

    /** @return array{name:string,mime:string,content:string} */
    private function readPdfAttachment(string $path, string $name): array
    {
        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw new \RuntimeException('PDF no disponible.');
        }

        $bytes = filesize($real);
        if ($bytes === false || $bytes <= 0 || $bytes > $this->maxAttachmentBytes) {
            throw new \RuntimeException('PDF con tamano no valido.');
        }

        $handle = fopen($real, 'rb');
        $signature = $handle !== false ? fread($handle, 5) : false;
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw new \RuntimeException('El adjunto no es un PDF valido.');
        }

        if (!function_exists('finfo_open')) {
            throw new \RuntimeException('No se puede verificar el tipo del PDF.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new \RuntimeException('No se puede verificar el tipo del PDF.');
        }
        $mime = finfo_file($finfo, $real);
        finfo_close($finfo);
        if (strtolower((string) $mime) !== 'application/pdf') {
            throw new \RuntimeException('El adjunto no es un PDF valido.');
        }

        $content = file_get_contents($real);
        if ($content === false) {
            throw new \RuntimeException('No se pudo leer el PDF.');
        }

        return [
            'name'    => $this->safePdfName($name),
            'mime'    => 'application/pdf',
            'content' => base64_encode($content),
        ];
    }

    private function safePdfName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? '';
        $name = trim($name, '._-');
        if ($name === '') {
            return 'contrato.pdf';
        }
        if (!str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        return mb_substr($name, 0, 120);
    }

    private function headerSafe(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    /** @return array{ok:bool,http_status:int,error:?string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'http_status' => 0, 'error' => $message];
    }
}
