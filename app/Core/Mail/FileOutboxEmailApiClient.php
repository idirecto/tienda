<?php

declare(strict_types=1);

namespace Tienda\Core\Mail;

use Tienda\Core\Config;

/**
 * Cliente de correo falso para pruebas manuales controladas.
 *
 * No abre conexiones de red: deposita el mensaje y una copia del PDF en una
 * carpeta privada de `storage/contracts/outbox`. Solo se permite fuera de
 * produccion y con `REGISTRATION_MAIL_TRANSPORT=file`.
 */
final class FileOutboxEmailApiClient implements EmailApiClientInterface
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim(
            $directory ?? (string) Config::get(
                'registration.outbox_dir',
                TIENDA_BASE . '/storage/contracts/outbox'
            ),
            '/\\'
        );
    }

    public function isEnabled(): bool
    {
        if (strtolower((string) Config::get('app.env', 'local')) === 'production') {
            return false;
        }

        if ($this->directory === '') {
            return false;
        }

        if (!is_dir($this->directory)) {
            return @mkdir($this->directory, 0700, true) || is_dir($this->directory);
        }

        return is_writable($this->directory);
    }

    public function sendWithPdf(array $message, string $pdfPath, string $pdfName): array
    {
        if (!$this->isEnabled()) {
            return $this->failure('El buzon falso no esta disponible.');
        }

        $from = strtolower(trim((string) ($message['from'] ?? '')));
        $to = (array) ($message['to'] ?? []);
        if (!filter_var($from, FILTER_VALIDATE_EMAIL) || $to === []) {
            return $this->failure('Correo incompleto.');
        }

        $realPdf = realpath($pdfPath);
        if ($realPdf === false
            || !is_file($realPdf)
            || !is_readable($realPdf)
            || (string) file_get_contents($realPdf, false, null, 0, 5) !== '%PDF-') {
            return $this->failure('PDF no disponible.');
        }

        $id = date('YmdHis') . '-' . bin2hex(random_bytes(6));
        $copy = $this->directory . '/' . $id . '.pdf';
        if (!@copy($realPdf, $copy)) {
            return $this->failure('No se pudo depositar el PDF en el buzon falso.');
        }
        @chmod($copy, 0600);

        $record = [
            'captured_at_utc' => gmdate('c'),
            'message'          => $message,
            'pdf_name'         => basename($pdfName),
            'pdf_path'         => $copy,
            'pdf_sha256'       => hash_file('sha256', $copy),
        ];
        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonPath = $this->directory . '/' . $id . '.json';
        if (!is_string($json) || @file_put_contents($jsonPath, $json, LOCK_EX) === false) {
            @unlink($copy);
            return $this->failure('No se pudo depositar el mensaje en el buzon falso.');
        }
        @chmod($jsonPath, 0600);

        return ['ok' => true, 'http_status' => 0, 'error' => null];
    }

    /** @return array{ok:bool,http_status:int,error:?string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'http_status' => 0, 'error' => $message];
    }
}
