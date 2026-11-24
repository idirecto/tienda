<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Core\Idirecto\Account;
use Tienda\Models\Setting;

/**
 * Consentimiento de cookies y utilidades de color seguro.
 *
 * Todo lo que se imprime en CSS o se entrega al JS sale de aqui ya validado:
 * - colores hex normalizados y con contraste WCAG calculado;
 * - cookie `id_consent` con version y duracion unicas;
 * - datos del responsable y tabla real de cookies para la politica.
 */
final class CookieConsent
{
    private const DEFAULT_TEXT = '#ffffff';

    private static ?array $config = null;

    /** @var array<string,string> */
    private static array $analyticsCache = [];

    /** Configuracion del fichero `config/cookies.php`. */
    public static function config(): array
    {
        return self::$config ??= (array) Config::get('cookies', []);
    }

    public static function name(): string
    {
        $name = trim((string) (self::config()['name'] ?? 'id_consent'));

        return preg_match('/^[A-Za-z0-9_\-]+$/', $name) === 1 ? $name : 'id_consent';
    }

    public static function version(): string
    {
        $version = trim((string) (self::config()['version'] ?? '1'));

        return $version !== '' ? $version : '1';
    }

    public static function days(): int
    {
        return max(1, (int) (self::config()['days'] ?? 180));
    }

    /**
     * Dominio de la cookie. Vacio = host-only, sin atributo Domain.
     * Si algun dia se configura, se valida para no inyectar cabeceras.
     */
    public static function domain(): string
    {
        $domain = strtolower(trim((string) (self::config()['domain'] ?? '')));
        if ($domain === '') {
            return '';
        }

        $domain = ltrim($domain, '.');
        if (strlen($domain) > 253
            || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $domain) !== 1) {
            return '';
        }

        return $domain;
    }

    /** @return array{decided:bool,analytics:bool,marketing:bool,external:bool} */
    public static function emptyState(): array
    {
        return [
            'decided'   => false,
            'analytics' => false,
            'marketing' => false,
            'external'  => false,
        ];
    }

    /** @return array{decided:bool,analytics:bool,marketing:bool,external:bool} */
    public static function stateFromRequest(): array
    {
        $raw = $_COOKIE[self::name()] ?? null;

        return is_string($raw) ? self::stateFromCookie($raw) : self::emptyState();
    }

    /**
     * Lee el formato `a:0|m:0|e:0|v:1`. Solo acepta la version actual y las
     * cuatro claves; cualquier valor incompleto equivale a "sin decision".
     *
     * @return array{decided:bool,analytics:bool,marketing:bool,external:bool}
     */
    public static function stateFromCookie(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return self::emptyState();
        }

        $raw = rawurldecode($raw);
        $values = [];
        foreach (explode('|', $raw) as $part) {
            if (preg_match('/^([amve]):([^\|]*)$/', $part, $match) !== 1) {
                return self::emptyState();
            }
            $values[$match[1]] = $match[2];
        }

        if (($values['v'] ?? '') !== self::version()
            || !isset($values['a'], $values['m'], $values['e'])) {
            return self::emptyState();
        }

        return [
            'decided'   => true,
            'analytics' => $values['a'] === '1',
            'marketing' => $values['m'] === '1',
            'external'  => $values['e'] === '1',
        ];
    }

    /**
     * @param array{decided?:mixed,analytics?:mixed,marketing?:mixed,external?:mixed} $state
     */
    public static function cookieValue(array $state): string
    {
        return 'a:' . (!empty($state['analytics']) ? '1' : '0')
            . '|m:' . (!empty($state['marketing']) ? '1' : '0')
            . '|e:' . (!empty($state['external']) ? '1' : '0')
            . '|v:' . self::version();
    }

    /**
     * Escribe la cookie desde el servidor (degradacion sin JavaScript).
     * Host-only: no se anade Domain. Secure solo si la peticion es HTTPS.
     *
     * @param array{decided?:mixed,analytics?:mixed,marketing?:mixed,external?:mixed} $state
     */
    public static function setCookie(array $state): bool
    {
        $options = [
            'expires'  => time() + self::days() * 86400,
            'path'     => '/',
            'secure'   => Server::isSecure(),
            'httponly' => false, // El JS debe poder leerla para bloquear terceros.
            'samesite' => 'Lax',
        ];

        $domain = self::domain();
        if ($domain !== '') {
            $options['domain'] = $domain;
        }

        $set = setcookie(self::name(), self::cookieValue($state), $options);
        if ($set) {
            $_COOKIE[self::name()] = self::cookieValue($state);
        }

        return $set;
    }

    /**
     * ID de analitica por tienda. Sin ID valido no se solicita nada: el
     * cargador del JS queda inactivo. No hay ningun ID global ni se copia el
     * UA historico de idirecto.
     */
    public static function analyticsId(Tenant $tenant): string
    {
        $storeId = $tenant->id();
        if ($storeId <= 0) {
            return '';
        }

        $key = trim((string) (self::config()['analytics_setting'] ?? 'analytics_ga4_id'));
        if ($key === '') {
            return '';
        }

        $cacheKey = $storeId . '|' . $key;
        if (array_key_exists($cacheKey, self::$analyticsCache)) {
            return self::$analyticsCache[$cacheKey];
        }

        $raw = trim((string) Setting::get($storeId, $key, ''));
        $id = preg_match('/^G-[A-Z0-9]{4,20}$/i', $raw) === 1 ? strtoupper($raw) : '';

        return self::$analyticsCache[$cacheKey] = $id;
    }

    /** Configuracion que consume `cookies-consent.js`. */
    public static function jsConfig(Tenant $tenant): array
    {
        return [
            'cookieName'   => self::name(),
            'version'      => self::version(),
            'days'         => self::days(),
            'cookieDomain' => self::domain(),
            'analyticsId'  => self::analyticsId($tenant),
        ];
    }

    /**
     * Datos que la politica muestra del responsable. La tienda aporta sus
     * datos publicos y el NIF/CIF se toma de Account::forStore() si la cuenta
     * esta enlazada. Si no hay NIF, la vista omite el dato.
     */
    public static function responsible(Tenant $tenant): array
    {
        $legalName = trim($tenant->legalName());
        $postalCode = trim($tenant->postalCode());
        $city = trim($tenant->city());
        $locality = trim($postalCode . ($postalCode !== '' && $city !== '' ? ' ' : '') . $city);
        $taxId = '';

        try {
            $account = Account::forStore($tenant->toArray());
            $taxId = trim((string) ($account['nif'] ?? ''));
        } catch (\Throwable $e) {
            // Una cuenta del mayorista no disponible no puede romper la política.
            $taxId = '';
        }

        return [
            'legal_name' => $legalName !== '' ? $legalName : $tenant->brandName(),
            'tax_id'     => $taxId,
            'address'    => trim($tenant->address()),
            'locality'   => $locality,
            'province'   => trim($tenant->province()),
            'country'    => trim((string) $tenant->get('country', '')),
            'email'      => trim($tenant->email()),
        ];
    }

    /** Datos de la politica, sin HTML y sin datos fijos de una tienda. */
    public static function policy(Tenant $tenant): array
    {
        return [
            'responsible'    => self::responsible($tenant),
            'own_cookies'    => (array) (self::config()['own_cookies'] ?? []),
            'local_storage'  => (array) (self::config()['local_storage'] ?? []),
            'updated_at'     => (string) (self::config()['policy_updated_at'] ?? ''),
            'analytics_id'   => self::analyticsId($tenant),
        ];
    }

    /**
     * Devuelve una ruta local segura para redirigir despues del POST del
     * formulario sin JavaScript. Rechaza esquemas, hosts, barras dobles,
     * retrocesos y caracteres de control (open redirect / inyeccion).
     */
    public static function safeReturnPath(string $raw, string $fallback): string
    {
        $fallback = trim($fallback);
        if ($fallback === ''
            || $fallback[0] !== '/'
            || str_starts_with($fallback, '//')
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $fallback) === 1) {
            $fallback = '/';
        }

        $raw = trim($raw);
        if ($raw === ''
            || $raw[0] !== '/'
            || str_starts_with($raw, '//')
            || preg_match('/[\x00-\x1F\x7F\\\\]/', $raw) === 1) {
            return $fallback;
        }

        $parts = parse_url($raw);
        if ($parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || !str_starts_with((string) ($parts['path'] ?? ''), '/')
            || str_starts_with((string) ($parts['path'] ?? ''), '//')) {
            return $fallback;
        }

        return $raw;
    }

    /**
     * Elimina del CSS propio de la tienda cualquier regla que intente tocar el
     * aviso (`#idc-*`, `.idc-*`) y cualquier declaracion `--idc-*`.
     *
     * Asi el componente no puede ocultarse ni recolorearse desde custom_css:
     * sus valores salen exclusivamente de las variables generadas en servidor.
     */
    public static function sanitizeCustomCss(string $css): string
    {
        $css = trim($css);
        if ($css === '' || stripos($css, 'idc') === false) {
            return $css;
        }

        $filtered = preg_replace_callback(
            '/([^{}]+)\{([^{}]*)\}/s',
            static function (array $match): string {
                if (stripos($match[1], 'idc') !== false) {
                    return '';
                }

                $body = preg_replace('/(?:^|;)\s*--idc-[^;}]*(?:;|$)/i', ';', $match[2]) ?? '';

                return $match[1] . '{' . $body . '}';
            },
            $css
        );

        if (!is_string($filtered)) {
            return '';
        }

        // Cubre declaraciones sueltas que no hayan caido dentro de un bloque.
        return preg_replace('/(?:^|;)\s*--idc-[^;}]*(?:;|$)/i', ';', $filtered) ?? '';
    }

    // =====================================================================
    // COLORES
    // =====================================================================

    /** Relacion de contraste WCAG entre dos colores hex. */
    public static function contrastRatio(string $a, string $b): float
    {
        $la = Appearance::luminance($a);
        $lb = Appearance::luminance($b);
        $light = max($la, $lb);
        $dark = min($la, $lb);

        return ($light + 0.05) / ($dark + 0.05);
    }

    /** Blanco o negro segun cual tenga mas contraste sobre el fondo. */
    public static function bestTextColor(string $background): string
    {
        $white = self::contrastRatio($background, '#ffffff');
        $black = self::contrastRatio($background, '#000000');

        return $white >= $black ? '#ffffff' : '#000000';
    }

    /**
     * Ajusta un color de texto/enlace hasta que alcance el contraste pedido
     * sobre el fondo. Conserva el tono mezclando hacia negro o blanco.
     */
    public static function ensureContrast(string $color, string $background, float $target = 4.5): string
    {
        $color = self::safeColor($color, self::fallbackColor());
        $background = self::safeColor($background, '#ffffff');

        if (self::contrastRatio($color, $background) >= $target) {
            return $color;
        }

        $towards = Appearance::luminance($background) >= 0.5 ? '#000000' : '#ffffff';
        for ($i = 1; $i <= 20; $i++) {
            $candidate = Appearance::mix($color, $towards, $i / 20);
            if (self::contrastRatio($candidate, $background) >= $target) {
                return $candidate;
            }
        }

        return $towards;
    }

    /** Ajusta un fondo hasta que contraste con su texto. */
    public static function ensureBackgroundContrast(string $background, string $text, float $target = 4.5): string
    {
        $background = self::safeColor($background, self::fallbackColor());
        $text = self::safeColor($text, self::DEFAULT_TEXT);

        if (self::contrastRatio($background, $text) >= $target) {
            return $background;
        }

        $towards = $text === '#ffffff' ? '#000000' : '#ffffff';
        for ($i = 1; $i <= 20; $i++) {
            $candidate = Appearance::mix($background, $towards, $i / 20);
            if (self::contrastRatio($candidate, $text) >= $target) {
                return $candidate;
            }
        }

        return $towards;
    }

    /** Variables CSS del consentimiento para un modo (light|dark). */
    public static function cssVars(Tenant $tenant, string $mode = 'light'): array
    {
        $mode = $mode === 'dark' ? 'dark' : 'light';
        $tokens = Appearance::tokens($tenant, $mode);
        $primary = self::primaryColor($tenant);

        $surfaceFallback = $mode === 'dark' ? '#141b25' : '#ffffff';
        $textFallback = $mode === 'dark' ? '#e2e8f0' : '#1f2937';
        $mutedFallback = $mode === 'dark' ? '#94a3b8' : '#64748b';
        $borderFallback = $mode === 'dark' ? '#26313f' : '#e2e8f0';
        $surface = self::safeColor((string) ($tokens['surface'] ?? ''), $surfaceFallback);
        $surface2 = self::safeColor((string) ($tokens['surface-2'] ?? ''), $mode === 'dark' ? '#1b2431' : '#f8fafc');

        $text = self::safeColor((string) ($tokens['text'] ?? ''), $textFallback);
        if (self::contrastRatio($text, $surface) < 4.5) {
            $text = self::bestTextColor($surface);
        }

        $heading = self::safeColor((string) ($tokens['heading'] ?? ''), $text);
        if (self::contrastRatio($heading, $surface) < 4.5) {
            $heading = $text;
        }

        $muted = self::ensureContrast(
            self::safeColor((string) ($tokens['muted'] ?? ''), $mutedFallback),
            $surface,
            4.5
        );
        $border = self::safeColor((string) ($tokens['border'] ?? ''), $borderFallback);

        // Enlaces: mismo tono de marca, pero siempre legibles sobre la superficie.
        $link = self::ensureContrast($primary, $surface, 4.5);

        // Botones Aceptar/Rechazar: el mismo fondo y el mismo texto.
        $controlBg = $primary;
        $controlText = self::bestTextColor($controlBg);
        $controlBg = self::ensureBackgroundContrast($controlBg, $controlText, 4.5);
        $controlBorder = $controlText;
        $hoverToward = $controlText === '#ffffff' ? '#000000' : '#ffffff';
        $controlHover = self::ensureBackgroundContrast(
            Appearance::mix($controlBg, $hoverToward, 0.12),
            $controlText,
            4.5
        );

        // Foco: se exige 4,5:1 con la superficie, no solo 3:1 de componente.
        $focus = self::ensureContrast($primary, $surface, 4.5);

        // Tipografía y formas efectivas de la tienda, aisladas del custom_css.
        $font = self::safeFont((string) ($tokens['font'] ?? ''), 'system-ui, sans-serif');
        $fontHead = self::safeFont((string) ($tokens['font-head'] ?? $font), $font);

        return [
            'primary'        => $primary,
            'surface'        => $surface,
            'surface-2'      => $surface2,
            'text'           => $text,
            'heading'        => $heading,
            'muted'          => $muted,
            'border'         => $border,
            'link'           => $link,
            'control-bg'     => $controlBg,
            'control-text'   => $controlText,
            'control-border' => $controlBorder,
            'control-hover'  => $controlHover,
            'focus'          => $focus,
            'font'           => $font,
            'font-head'      => $fontHead,
            'radius-sm'      => self::safeLength((string) ($tokens['radius-sm'] ?? ''), '6px'),
            'radius-md'      => self::safeLength((string) ($tokens['radius-md'] ?? ''), '10px'),
            'radius-lg'      => self::safeLength((string) ($tokens['radius-lg'] ?? ''), '14px'),
            'radius-pill'    => self::safeLength((string) ($tokens['radius-pill'] ?? ''), '999px'),
            'shadow-lg'      => '0 18px 45px rgba(15, 23, 42, .18)',
            'container-max'  => self::safeLength((string) ($tokens['container-max'] ?? ''), '1600px'),
        ];
    }

    /** CSS inline con las variables de ambos modos. */
    public static function css(Tenant $tenant): string
    {
        $light = self::declarations(self::cssVars($tenant, 'light'));
        $dark = self::declarations(self::cssVars($tenant, 'dark'));

        return ":root{{$light}}\n"
            . ":root[data-color-scheme=\"dark\"]{{$dark}}\n"
            . "@media (prefers-color-scheme:dark){:root[data-color-scheme=\"auto\"]{{$dark}}}\n";
    }

    /** @param array<string,string> $vars */
    private static function declarations(array $vars): string
    {
        $out = [];
        foreach ($vars as $name => $value) {
            // El custom_css de la tienda se imprime antes; estas variables no
            // pueden pisarse desde el.
            $out[] = '--idc-' . $name . ':' . $value . ' !important';
        }

        return implode(';', $out) . ';';
    }

    /**
     * Primario efectivo: el override libre de `theme_tokens.raw` si es un hex
     * valido; si no, `mt_stores.color_primary`; si tampoco vale, #333333.
     *
     * Se mira `raw` porque `Appearance` aplica ahi los tokens que pueden pisar
     * el color de marca; las claves de paleta claras/planas se sobrescriben
     * despues con el color de la tienda en el generador del tema.
     */
    private static function primaryColor(Tenant $tenant): string
    {
        $raw = trim($tenant->themeTokens());
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['raw']) && is_array($data['raw'])) {
                foreach (['--c-primary', 'primary'] as $key) {
                    $value = $data['raw'][$key] ?? null;
                    if (is_scalar($value)) {
                        $hex = Appearance::hex((string) $value);
                        if ($hex !== null) {
                            return $hex;
                        }
                    }
                }
            }
        }

        return Appearance::hex($tenant->colorPrimary()) ?? self::fallbackColor();
    }

    /** Fuente segura para una variable CSS (`--idc-font*`). */
    private static function safeFont(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 300 || preg_match('/[<>{};()]/', $value) === 1) {
            return $fallback;
        }

        return $value;
    }

    /** Longitud/radio seguro; solo admite unidades CSS sencillas. */
    private static function safeLength(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^(?:0|\d+(?:\.\d+)?(?:px|rem|em|%|vw|vh|dvh))$/', $value) === 1) {
            return $value;
        }

        return $fallback;
    }

    private static function safeColor(string $value, string $fallback): string
    {
        return Appearance::hex($value) ?? Appearance::hex($fallback) ?? '#333333';
    }

    private static function fallbackColor(): string
    {
        return Appearance::hex((string) (self::config()['fallback_color'] ?? '#333333')) ?? '#333333';
    }
}
