<?php

declare(strict_types=1);

namespace Tienda\Core\Onboarding;

use Tienda\Core\Config;

/**
 * Reglas puras del codigo de verificacion de una alta.
 *
 * No escribe en base de datos ni envia correos. El codigo en claro solo se
 * devuelve a la capa que lo va a incluir en el mensaje; la persistencia debe
 * guardar exclusivamente `hash`.
 */
final class VerificationService
{
    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const EXPIRED = 'expired';
    public const USED = 'used';
    public const SUPERSEDED = 'superseded';
    public const LOCKED = 'locked';

    private int $digits;
    private int $ttlSeconds;
    private int $maxAttempts;
    private int $resendCooldownSeconds;
    private int $maxResendsPerWindow;
    private int $resendWindowSeconds;

    public function __construct(?array $config = null)
    {
        $config ??= (array) Config::get('registration.verification', []);
        $this->digits = max(6, min(12, (int) ($config['digits'] ?? 8)));
        $this->ttlSeconds = max(60, (int) ($config['ttl_seconds'] ?? 900));
        $this->maxAttempts = max(1, (int) ($config['max_attempts'] ?? 5));
        $this->resendCooldownSeconds = max(0, (int) ($config['resend_cooldown_seconds'] ?? 60));
        $this->maxResendsPerWindow = max(1, (int) ($config['max_resends_per_window'] ?? 5));
        $this->resendWindowSeconds = max(60, (int) ($config['resend_window_seconds'] ?? 3600));
    }

    /**
     * Codigo nuevo. Se devuelve en claro para el correo y su hash para BD.
     *
     * @return array{code:string,hash:string,expires_at:\DateTimeImmutable}
     */
    public function issue(\DateTimeImmutable $now): array
    {
        $code = $this->generateCode();

        return [
            'code'       => $code,
            'hash'       => $this->hashCode($code),
            'expires_at' => $now->modify('+' . $this->ttlSeconds . ' seconds'),
        ];
    }

    public function generateCode(): string
    {
        $max = (10 ** $this->digits) - 1;

        return str_pad((string) random_int(0, $max), $this->digits, '0', STR_PAD_LEFT);
    }

    public function hashCode(string $code): string
    {
        return password_hash($code, PASSWORD_DEFAULT);
    }

    /** Normaliza lo que escribe la persona: espacios y guiones no cuentan. */
    public function normalizeCode(string $submitted): string
    {
        $submitted = trim($submitted);
        if ($submitted === '') {
            return '';
        }

        $submitted = preg_replace('/[\s-]+/', '', $submitted) ?? '';
        if (!ctype_digit($submitted) || strlen($submitted) !== $this->digits) {
            return '';
        }

        return $submitted;
    }

    /**
     * Resultado de comprobar un codigo.
     *
     * No incrementa intentos: la persistencia debe hacerlo de forma atomica
     * cuando el resultado sea INVALID. Un codigo usado, caducado o anulado no
     * puede volver a activar nada.
     */
    public function verify(
        string $submitted,
        string $hash,
        int $attempts,
        ?string $consumedAt,
        ?string $supersededAt,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
        ?int $maxAttempts = null
    ): string {
        if (($consumedAt ?? '') !== '') {
            return self::USED;
        }
        if (($supersededAt ?? '') !== '') {
            return self::SUPERSEDED;
        }
        if ($now >= $expiresAt) {
            return self::EXPIRED;
        }
        $limit = $maxAttempts !== null && $maxAttempts > 0 ? $maxAttempts : $this->maxAttempts;
        if ($attempts >= $limit) {
            return self::LOCKED;
        }

        $normalized = $this->normalizeCode($submitted);
        if ($normalized === '' || $hash === '') {
            return self::INVALID;
        }

        return password_verify($normalized, $hash) ? self::VALID : self::INVALID;
    }

    /**
     * ¿Se puede emitir otro codigo?
     *
     * @param int $sendsInWindow Numero de codigos emitidos dentro de la ventana
     */
    public function mayResend(
        ?\DateTimeImmutable $lastSentAt,
        int $sendsInWindow,
        \DateTimeImmutable $now
    ): bool {
        if ($sendsInWindow >= $this->maxResendsPerWindow) {
            return false;
        }

        if ($lastSentAt !== null
            && $now->getTimestamp() < ($lastSentAt->getTimestamp() + $this->resendCooldownSeconds)) {
            return false;
        }

        return true;
    }

    public function ttlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function digits(): int
    {
        return $this->digits;
    }

    public function resendWindowSeconds(): int
    {
        return $this->resendWindowSeconds;
    }

    public function resendCooldownSeconds(): int
    {
        return $this->resendCooldownSeconds;
    }

    public function maxResendsPerWindow(): int
    {
        return $this->maxResendsPerWindow;
    }
}
