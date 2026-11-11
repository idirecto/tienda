<?php

declare(strict_types=1);

namespace Tienda\Tests\Support;

use Tienda\Core\Mail\EmailApiClientInterface;

/**
 * Cliente falso: registra la llamada y nunca abre conexiones de red.
 */
final class FakeEmailApiClient implements EmailApiClientInterface
{
    /** @var list<array<string,mixed>> */
    public array $calls = [];

    public function __construct(private bool $enabled = true, private bool $sendOk = true)
    {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function sendWithPdf(array $message, string $pdfPath, string $pdfName): array
    {
        $this->calls[] = [
            'message'     => $message,
            'pdf_name'    => $pdfName,
            'pdf_bytes'   => is_file($pdfPath) ? (int) filesize($pdfPath) : 0,
            'pdf_sha256'  => is_file($pdfPath) ? (string) hash_file('sha256', $pdfPath) : '',
        ];

        return [
            'ok'          => $this->sendOk,
            'http_status' => $this->sendOk ? 200 : 502,
            'error'       => $this->sendOk ? null : 'Fallo simulado de la API.',
        ];
    }
}
