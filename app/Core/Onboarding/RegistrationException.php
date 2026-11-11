<?php

declare(strict_types=1);

namespace Tienda\Core\Onboarding;

/**
 * Error controlado del flujo de alta.
 *
 * `reason()` es un codigo estable para pruebas y para decidir el mensaje; el
 * mensaje de la excepcion debe ser seguro para mostrar al usuario.
 */
final class RegistrationException extends \RuntimeException
{
    private string $reason;

    public static function because(string $reason, string $message, ?\Throwable $previous = null): self
    {
        $exception = new self($message, 0, $previous);
        $exception->reason = $reason;

        return $exception;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
