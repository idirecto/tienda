<?php

declare(strict_types=1);

namespace Tienda\Core\Contract;

use DateTimeImmutable;
use DateTimeZone;
use Tienda\Core\Config;
use Tienda\Models\ContractVersion;

/**
 * Plantilla HTML privada del contrato de acceso.
 *
 * El PDF aprobado (`Contrato_web_clientes_v1.pdf`) es la referencia juridica.
 * Para cada cliente se parte de su hermano HTML, se sustituyen los marcadores
 * `{{...}}` por datos escapados y se aborta si queda cualquiera sin resolver.
 *
 * La aceptacion conserva el hash de la plantilla aprobada
 * (`mt_contract_versions.template_sha256`), el hash del HTML fuente usado para
 * renderizar (`source_template_sha256`) y el hash del PDF individual generado
 * (`pdf_sha256`).
 */
final class ContractTemplate
{
    /** Texto literal de la declaracion que se muestra en el formulario. */
    public const ACCEPTANCE_TEXT = 'Declaro que estoy autorizado/a para activar y utilizar la cuenta web en nombre del cliente de IDIRECTO asociado a este acceso. He leído y acepto el Contrato de acceso y uso de la web en nombre de dicho cliente. La aceptación quedará registrada electrónicamente y se enviará una confirmación al correo indicado';

    /** @var array<string,mixed> */
    private array $values;

    private ?string $sourcePathOverride;

    /**
     * @param array<string,mixed>|null $values           Valores de configuracion; null = config.
     * @param string|null              $sourcePathOverride Solo pruebas: plantilla alternativa.
     */
    public function __construct(?array $values = null, ?string $sourcePathOverride = null)
    {
        $this->values = $values ?? (array) Config::get('registration.contract.values', []);
        $this->sourcePathOverride = $sourcePathOverride;
    }

    /** @return list<string> Ajustes obligatorios que faltan o no son validos. */
    public function configurationProblems(): array
    {
        $problems = [];

        $support = trim((string) ($this->values['support_email'] ?? ''));
        if ($support === '') {
            $problems[] = 'correo de soporte';
        } elseif (!filter_var($support, FILTER_VALIDATE_EMAIL)) {
            $problems[] = 'correo de soporte no valido';
        }

        $contact = trim((string) ($this->values['idirecto_contact'] ?? ''));
        if ($contact === '') {
            $problems[] = 'contacto de IDIRECTO';
        }

        $liability = trim((string) ($this->values['liability_limit'] ?? ''));
        if ($liability === '') {
            $problems[] = 'limite de responsabilidad';
        }

        $positive = [
            'preaviso_days'          => 'plazo de preaviso',
            'export_days'            => 'plazo de exportacion',
            'negotiation_days'       => 'plazo de negociacion',
            'confidentiality_years'  => 'plazo de confidencialidad',
        ];
        foreach ($positive as $key => $label) {
            if ((int) ($this->values[$key] ?? 0) <= 0) {
                $problems[] = $label;
            }
        }

        return $problems;
    }

    public function assertConfigurationReady(): void
    {
        $problems = $this->configurationProblems();
        if ($problems !== []) {
            throw new ContractConfigurationException($problems);
        }
    }

    /**
     * Ruta del HTML hermano del PDF aprobado.
     *
     * @param array $contract Fila de `mt_contract_versions`
     */
    public function sourcePath(array $contract): string
    {
        if ($this->sourcePathOverride !== null) {
            $path = realpath($this->sourcePathOverride);
            if ($path === false || !is_file($path) || !is_readable($path)) {
                throw new ContractTemplateException('La plantilla HTML del contrato no esta disponible.');
            }

            return $path;
        }

        $pdf = ContractVersion::templateAbsolutePath($contract);
        if ($pdf === null) {
            throw new ContractTemplateException('El PDF aprobado del contrato no esta disponible.');
        }

        $base = realpath((string) Config::get('registration.contract.template_dir', ''));
        if ($base === false) {
            throw new ContractTemplateException('La carpeta privada de plantillas no esta disponible.');
        }

        $candidate = realpath(preg_replace('/\.pdf$/i', '.html', $pdf) ?? '');
        $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($candidate === false
            || !str_starts_with($candidate, $prefix)
            || !is_file($candidate)
            || !is_readable($candidate)) {
            throw new ContractTemplateException('La plantilla HTML del contrato no esta disponible.');
        }

        return $candidate;
    }

    /**
     * Renderiza el HTML con los datos del cliente.
     *
     * @param array $contract Fila de `mt_contract_versions`
     * @param array $client   legal_name, tax_id, address, representative, contact, email
     * @return array{html:string,template_sha256:string,source_path:string,acceptance_text_sha256:string}
     */
    public function render(array $contract, array $client, ?DateTimeImmutable $now = null): array
    {
        $this->assertConfigurationReady();

        $client = $this->validatedClient($client);
        $source = $this->sourcePath($contract);
        $raw = file_get_contents($source);
        if (!is_string($raw) || $raw === '') {
            throw new ContractTemplateException('La plantilla contractual esta vacia o no se puede leer.');
        }

        $version = trim((string) ($contract['version_label'] ?? ''));
        if ($version === '') {
            throw new ContractTemplateException('La version contractual no esta informada.');
        }

        $templateHash = hash('sha256', $raw);
        $timezone = new DateTimeZone((string) Config::get('app.timezone', 'Europe/Madrid'));
        $now ??= new DateTimeImmutable('now', $timezone);

        $replacements = [
            '{{FECHA_CONTRATO}}'             => $this->spanishDate($now),
            '{{CLIENTE_RAZON_SOCIAL}}'       => self::escape($client['legal_name']),
            '{{CLIENTE_NIF}}'                => self::escape($client['tax_id']),
            '{{CLIENTE_DOMICILIO}}'          => self::escape($client['address']),
            '{{CLIENTE_REPRESENTANTE}}'      => self::escape($client['representative']),
            '{{CLIENTE_CONTACTO}}'           => self::escape($client['contact']),
            '{{CONTRATO_VERSION}}'           => self::escape($version),
            '{{SOPORTE_EMAIL}}'              => self::escape((string) $this->values['support_email']),
            '{{IDIRECTO_CONTACTO}}'          => self::escape((string) $this->values['idirecto_contact']),
            '{{LIMITE_RESPONSABILIDAD}}'     => self::escape((string) $this->values['liability_limit']),
            '{{PLAZO_PREAVISO_DIAS}}'        => (string) (int) $this->values['preaviso_days'],
            '{{PLAZO_EXPORTACION_DIAS}}'     => (string) (int) $this->values['export_days'],
            '{{PLAZO_NEGOCIACION_DIAS}}'     => (string) (int) $this->values['negotiation_days'],
            '{{PLAZO_CONFIDENCIALIDAD_ANIOS}}' => (string) (int) $this->values['confidentiality_years'],
        ];

        $html = strtr($raw, $replacements);
        $this->assertNoUnresolvedMarkers($html);
        foreach (array_keys($replacements) as $marker) {
            if (!str_contains($raw, $marker)) {
                throw new ContractTemplateException(
                    'La plantilla contractual no contiene todos los datos obligatorios.'
                );
            }
        }

        return [
            'html'                    => $html,
            'template_sha256'         => $templateHash,
            'source_path'             => $source,
            'acceptance_text_sha256'  => hash('sha256', self::ACCEPTANCE_TEXT),
        ];
    }

    /** Aborta si queda cualquier marcador `{{...}}` o `[...]`. */
    public function assertNoUnresolvedMarkers(string $html): void
    {
        if (preg_match('/\{\{[^{}]{1,200}\}\}|\[[^\[\]]{1,200}\]/u', $html, $match) === 1) {
            throw new ContractTemplateException('El contrato conserva un marcador sin resolver.');
        }
    }

    /**
     * @param array $client
     * @return array{legal_name:string,tax_id:string,address:string,representative:string,contact:string,email:string}
     */
    private function validatedClient(array $client): array
    {
        $required = [
            'legal_name'     => 'razon social',
            'tax_id'         => 'NIF',
            'address'        => 'domicilio',
            'representative' => 'representante',
            'contact'        => 'contacto',
            'email'          => 'correo',
        ];

        $out = [];
        foreach ($required as $key => $label) {
            $value = trim((string) ($client[$key] ?? ''));
            if ($value === '') {
                throw new ContractTemplateException('Falta ' . $label . ' del cliente para generar el contrato.');
            }
            $out[$key] = $value;
        }

        if (!filter_var($out['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ContractTemplateException('El correo del cliente no es valido para generar el contrato.');
        }

        return $out;
    }

    /** Escapa HTML y neutraliza los caracteres que delimitan marcadores. */
    private static function escape(string $value): string
    {
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return str_replace(
            ['{', '}', '[', ']'],
            ['&#123;', '&#125;', '&#91;', '&#93;'],
            $value
        );
    }

    private function spanishDate(DateTimeImmutable $date): string
    {
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $date->format('j') . ' de ' . $months[(int) $date->format('n')] . ' de ' . $date->format('Y');
    }
}
