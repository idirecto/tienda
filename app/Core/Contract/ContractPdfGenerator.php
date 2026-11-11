<?php

declare(strict_types=1);

namespace Tienda\Core\Contract;

use Tienda\Core\Config;

/**
 * Generador del PDF individualizado.
 *
 * Usa TCPDF, disponible como paquete del sistema (no se anade ninguna
 * dependencia al proyecto ni se ejecuta Composer). Si el motor no esta
 * disponible, el alta se bloquea: nunca se entrega un contrato incompleto.
 */
final class ContractPdfGenerator
{
    private ?string $configuredPath;

    public function __construct(?string $tcpdfPath = null)
    {
        $this->configuredPath = $tcpdfPath
            ?? (trim((string) Config::get('registration.contract.tcpdf_path', '')) ?: null);
    }

    public static function isAvailable(): bool
    {
        if (class_exists('TCPDF')) {
            return true;
        }

        return self::detectTcpdfPath() !== null;
    }

    /**
     * @param array{title?:string,subject?:string,author?:string,keywords?:string} $metadata
     */
    public function generate(string $html, string $destination, array $metadata = []): void
    {
        if (trim($html) === '') {
            throw new ContractPdfException('El HTML del contrato esta vacio.');
        }
        if (!str_ends_with(strtolower($destination), '.pdf')) {
            throw new ContractPdfException('El destino del contrato debe ser un PDF.');
        }

        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new ContractPdfException('No se pudo crear la carpeta privada del contrato.');
        }

        $this->loadTcpdf();

        try {
            $pdf = new class ('P', 'mm', 'A4', true, 'UTF-8', false) extends \TCPDF {
                public function Footer(): void
                {
                    $this->SetY(-12);
                    $this->SetFont('helvetica', '', 7);
                    $this->SetTextColor(90, 90, 90);
                    $this->Cell(
                        0,
                        5,
                        'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(),
                        0,
                        0,
                        'C'
                    );
                }

                /** El contrato del cliente no lleva el enlace promocional de TCPDF. */
                public function disableTcpdfLink(): void
                {
                    $this->tcpdflink = false;
                }
            };
            $pdf->disableTcpdfLink();
            $pdf->SetCreator('IDirecto Tienda');
            $pdf->SetAuthor((string) ($metadata['author'] ?? 'IDIRECTO MAYORISTA S.L.'));
            $pdf->SetTitle((string) ($metadata['title'] ?? 'Contrato de acceso y uso de la plataforma web'));
            $pdf->SetSubject((string) ($metadata['subject'] ?? 'Contrato de acceso y uso de la plataforma web'));
            $pdf->SetKeywords((string) ($metadata['keywords'] ?? 'contrato, acceso, plataforma web'));

            $pdf->SetAutoPageBreak(true, 18);
            $pdf->SetMargins(18, 16, 18);
            $pdf->SetHeaderMargin(8);
            $pdf->SetFooterMargin(11);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(true);
            $pdf->SetFont('dejavusans', '', 9.5);

            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');

            $pdf->Output($destination, 'F');
            @chmod($destination, 0640);
        } catch (\Throwable $e) {
            @unlink($destination);
            throw new ContractPdfException('No se pudo generar el PDF del contrato.', 0, $e);
        }

        $size = is_file($destination) ? filesize($destination) : false;
        $signature = is_file($destination) ? (string) file_get_contents($destination, false, null, 0, 5) : '';
        if ($size === false || $size <= 0 || $signature !== '%PDF-') {
            @unlink($destination);
            throw new ContractPdfException('El PDF generado no es valido.');
        }
    }

    private function loadTcpdf(): void
    {
        if (class_exists('TCPDF')) {
            return;
        }

        $path = $this->configuredPath ?? self::detectTcpdfPath();
        if ($path === null || !is_file($path) || !is_readable($path)) {
            throw new ContractPdfException(
                'No hay un motor PDF disponible para generar el contrato. '
                . 'Instala php-tcpdf o configura CONTRACT_TCPDF_PATH.'
            );
        }

        require_once $path;

        if (!class_exists('TCPDF')) {
            throw new ContractPdfException('El motor PDF no se ha cargado correctamente.');
        }
    }

    private static function detectTcpdfPath(): ?string
    {
        foreach ([
            '/usr/share/php/tcpdf/tcpdf.php',
            '/usr/share/php/TCPDF/tcpdf.php',
            '/usr/local/share/php/tcpdf/tcpdf.php',
        ] as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
