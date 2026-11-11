<?php

declare(strict_types=1);

namespace Tienda\Core\Mail;

/**
 * Envio de correo con PDF mediante la API interna existente.
 *
 * La implementacion real solo debe activarse cuando la configuracion segura
 * de API_EMAIL, EMAIL_USER y EMAIL_PASS este confirmada. Las pruebas usan una
 * implementacion falsa que nunca abre conexiones de red.
 */
interface EmailApiClientInterface
{
    public function isEnabled(): bool;

    /**
     * @param array<string,mixed> $message from, from_name, to, subject, html...
     * @return array{ok:bool,http_status:int,error:?string}
     */
    public function sendWithPdf(array $message, string $pdfPath, string $pdfName): array;
}
