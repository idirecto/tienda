<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Sistema de diseno de la plataforma (white-label).
 *
 * Convierte la configuracion (`config/appearance.php`) y lo que cada tienda ha
 * guardado en `mt_stores` en un mapa de variables CSS (`--c-*`).
 *
 * El storefront nunca escribe un color a mano: consume estas variables, asi que
 * cambiar la identidad de una tienda es cambiar tres valores en el panel.
 *
 * Fuentes de datos, de menor a mayor prioridad:
 *   1. Valores por defecto de la configuracion global.
 *   2. Columnas de diseno de la tienda (`color_primary`, `color_accent`...).
 *   3. `mt_stores.theme_tokens` (JSON), que puede pisar cualquier token.
 */
final class Appearance
{
    /** Sufijo de los tokens de paleta (bg -> --c-bg). */
    private const PREFIX = '--c-';

    /** Color que se usa como texto sobre un fondo de marca claro. */
    private const ON_LIGHT = '#0b0e13';

    /** Color que se usa como texto sobre un fondo de marca oscuro. */
    private const ON_DARK = '#ffffff';

    private static ?array $config = null;

    // =====================================================================
    // CONFIGURACION
    // =====================================================================

    private static function config(): array
    {
        return self::$config ??= (array) Config::get('appearance', []);
    }

    /** Esquemas disponibles (para el panel). */
    public static function schemes(): array
    {
        return [
            'light' => 'Siempre claro',
            'dark'  => 'Siempre oscuro',
            'auto'  => 'Automatico (segun el sistema)',
        ];
    }

    /** Escalas de radio disponibles (para el panel). */
    public static function radiusScales(): array
    {
        return [
            'compact'  => 'Compacto',
            'standard' => 'Estandar',
            'rounded'  => 'Redondeado',
        ];
    }

    /** Tipografias disponibles (para el panel). */
    public static function fonts(): array
    {
        $fonts = (array) (self::config()['fonts'] ?? []);
        $out = [];
        foreach ($fonts as $key => $font) {
            $out[$key] = (string) ($font['label'] ?? $key);
        }
        return $out;
    }

    /**
     * Presets de identidad visual que ofrece el panel.
     * @return array<string, array>
     */
    public static function presets(): array
    {
        return (array) (self::config()['presets'] ?? []);
    }

    /** Normaliza el nombre de un preset. */
    public static function preset(string $key): ?array
    {
        $presets = self::presets();
        return $presets[$key] ?? null;
    }

    // =====================================================================
    // TOKENS
    // =====================================================================

    /**
     * Mapa completo de tokens: [nombre sin prefijo => valor CSS].
     *
     * @param Tenant $tenant Tienda para la que se resuelve la identidad
     * @param string $mode   light|dark
     * @return array<string, string>
     */
    public static function tokens(Tenant $tenant, string $mode = 'light'): array
    {
        $mode = $mode === 'dark' ? 'dark' : 'light';
        $config = self::config();
        $overrides = self::overrides($tenant);

        // 1. Paleta base del modo + pisotones de la tienda para ese modo.
        $palette = (array) ($config[$mode] ?? []);
        $palette = array_merge($palette, (array) ($overrides[$mode] ?? []));

        // 2. Columnas de diseno de la tienda (solo afectan a la paleta clara:
        //    el negro del modo oscuro lo aporta la paleta oscura).
        if ($mode === 'light') {
            $palette = array_merge($palette, array_filter([
                'bg'      => self::hex($tenant->colorBackground()),
                'surface' => self::hex($tenant->colorSurface()),
                'text'    => self::hex($tenant->colorText()),
                'border'  => self::hex($tenant->colorBorder()),
            ], static fn ($v) => $v !== null));
        }

        $tokens = [];
        foreach ($palette as $slot => $value) {
            $tokens[(string) $slot] = (string) $value;
        }

        // 3. Colores de marca (comunes a los dos modos) y sus derivados.
        $primary   = self::hex($tenant->colorPrimary()) ?? self::brandDefault('primary');
        $secondary = self::hex($tenant->colorSecondary()) ?? self::brandDefault('secondary');
        $accent    = self::hex($tenant->colorAccent()) ?? self::brandDefault('accent');

        $tokens = array_merge($tokens, self::brandTokens('primary', $primary, $mode));
        $tokens = array_merge($tokens, self::brandTokens('secondary', $secondary, $mode));
        $tokens = array_merge($tokens, self::brandTokens('accent', $accent, $mode));

        // 4. Radios, tipografias, sombras y layout.
        $tokens = array_merge($tokens, self::radiusTokens($tenant), self::fontTokens($tenant));
        $tokens = array_merge($tokens, self::shadowTokens($mode), self::layoutTokens());

        // 5. Tokens libres de la tienda (ganan a todo).
        $raw = (array) ($overrides['raw'] ?? []);
        foreach ($raw as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || is_array($value)) {
                continue;
            }
            $tokens[self::slot($name)] = (string) $value;
        }

        return $tokens;
    }

    /** Deriva los tokens de un color de marca (normal, hover, suave, contraste). */
    private static function brandTokens(string $name, string $hex, string $mode): array
    {
        // En claro, el hover se oscurece; en oscuro se aclara (se ve mejor).
        $hover = $mode === 'dark'
            ? self::mix($hex, '#ffffff', 0.14)
            : self::mix($hex, '#000000', 0.18);

        // Fondo muy suave del color de marca (para chips, avisos, activos).
        $soft = $mode === 'dark'
            ? self::mix($hex, '#0b0e13', 0.82)
            : self::mix($hex, '#ffffff', 0.90);

        return [
            $name              => $hex,
            $name . '-hover'   => $hover,
            $name . '-active'  => self::mix($hover, $mode === 'dark' ? '#ffffff' : '#000000', 0.12),
            $name . '-soft'    => $soft,
            $name . '-contrast' => self::contrast($hex),
        ];
    }

    /** Escala de radios elegida por la tienda. */
    private static function radiusTokens(Tenant $tenant): array
    {
        $config = self::config();
        $scales = (array) ($config['radius'] ?? []);
        $scale = $tenant->radiusScale();
        if (!isset($scales[$scale])) {
            $scale = (string) ($config['radius_default'] ?? 'standard');
        }
        $values = (array) ($scales[$scale] ?? []);

        return [
            'radius-scale' => $scale,
            'radius-sm'    => (string) ($values['sm'] ?? '6px'),
            'radius-md'    => (string) ($values['md'] ?? '10px'),
            'radius-lg'    => (string) ($values['lg'] ?? '14px'),
            'radius-xl'    => (string) ($values['xl'] ?? '20px'),
            'radius-pill'  => '999px',
        ];
    }

    /** Tipografias de cuerpo y de titulo. */
    private static function fontTokens(Tenant $tenant): array
    {
        $fonts = (array) (self::config()['fonts'] ?? []);
        $key = $tenant->font();
        if (!isset($fonts[$key])) {
            $key = (string) (self::config()['font_default'] ?? 'system');
        }
        $font = (array) ($fonts[$key] ?? []);

        return [
            'font'      => (string) ($font['body'] ?? 'system-ui, sans-serif'),
            'font-head' => (string) ($font['head'] ?? $font['body'] ?? 'system-ui, sans-serif'),
        ];
    }

    /** Sombras coherentes con el modo (en oscuro, mas profundas y opacas). */
    private static function shadowTokens(string $mode): array
    {
        if ($mode === 'dark') {
            return [
                'shadow-sm' => '0 1px 2px rgba(0, 0, 0, .45)',
                'shadow-md' => '0 6px 18px rgba(0, 0, 0, .45)',
                'shadow-lg' => '0 18px 45px rgba(0, 0, 0, .55)',
                'shadow-brand' => '0 10px 30px color-mix(in srgb, var(--c-primary) 32%, transparent)',
            ];
        }

        return [
            'shadow-sm' => '0 1px 2px rgba(15, 23, 42, .06)',
            'shadow-md' => '0 6px 18px rgba(15, 23, 42, .08)',
            'shadow-lg' => '0 18px 45px rgba(15, 23, 42, .12)',
            'shadow-brand' => '0 10px 30px color-mix(in srgb, var(--c-primary) 28%, transparent)',
        ];
    }

    /** Anchos del contenedor general. */
    private static function layoutTokens(): array
    {
        $layout = (array) (self::config()['layout'] ?? []);
        return [
            'container-min'   => (string) ($layout['container_min'] ?? '1180px'),
            'container-max'   => (string) ($layout['container_max'] ?? '1600px'),
            'container-ultra' => (string) ($layout['container_ultra'] ?? '1800px'),
            'gap'             => '1rem',
        ];
    }

    /**
     * CSS completo del tema: variables en claro/oscuro y los dos modos de
     * aplicacion del esquema elegido.
     *
     * Se emite inline en el <head> (es la identidad de la tienda y evita un
     * fichero extra y un parpadeo de color al cargar).
     */
    public static function css(Tenant $tenant): string
    {
        $light = self::declarations(self::tokens($tenant, 'light'));
        $dark = self::declarations(self::tokens($tenant, 'dark'));

        $css = ":root{color-scheme:light;{$light}}\n"
            . ":root[data-color-scheme=\"dark\"]{color-scheme:dark;{$dark}}\n"
            . "@media (prefers-color-scheme:dark){:root[data-color-scheme=\"auto\"]{color-scheme:dark;{$dark}}}";

        return $css;
    }

    /** Convierte el mapa de tokens en declaraciones CSS. */
    private static function declarations(array $tokens): string
    {
        $out = [];
        foreach ($tokens as $name => $value) {
            $out[] = self::PREFIX . self::slot($name) . ':' . $value;
        }
        return implode(';', $out) . ';';
    }

    /** Sanea el nombre de un token (sin prefijo). */
    private static function slot(string $name): string
    {
        $name = trim($name);
        $name = str_starts_with($name, self::PREFIX) ? substr($name, strlen(self::PREFIX)) : $name;
        return (string) (preg_replace('/[^a-z0-9_\-]/i', '', $name) ?: '');
    }

    /** Esquema de color de la tienda: light|dark|auto. */
    public static function scheme(Tenant $tenant): string
    {
        $scheme = strtolower(trim($tenant->colorScheme()));
        return array_key_exists($scheme, self::schemes()) ? $scheme : 'light';
    }

    /**
     * Color de la barra del navegador (`theme-color`): el color de fondo, que
     * es lo que se ve detras de la interfaz en movil.
     */
    public static function themeColor(Tenant $tenant): string
    {
        $tokens = self::tokens($tenant, self::scheme($tenant) === 'dark' ? 'dark' : 'light');
        return (string) ($tokens['bg'] ?? '#ffffff');
    }

    /**
     * Extrae el bloque de personalizacion libre de la tienda.
     *
     * Acepta tanto `{"light": {...}, "dark": {...}, "raw": {"--c-x": "..."}}`
     * como un objeto plano de tokens (`{"bg": "#fff"}`), para que sea comodo
     * inyectarlo desde el backend.
     */
    private static function overrides(Tenant $tenant): array
    {
        $raw = $tenant->themeTokens();
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $out = ['light' => [], 'dark' => [], 'raw' => []];
        $modes = ['light', 'dark'];

        foreach ($data as $key => $value) {
            $key = (string) $key;
            if (in_array($key, $modes, true) && is_array($value)) {
                foreach ($value as $k => $v) {
                    $safe = self::safeValue($v);
                    if ($safe !== null) {
                        $out[$key][self::slot((string) $k)] = $safe;
                    }
                }
                continue;
            }
            if ($key === 'raw' && is_array($value)) {
                foreach ($value as $k => $v) {
                    $safe = self::safeValue($v);
                    if ($safe !== null) {
                        $out['raw'][(string) $k] = $safe;
                    }
                }
                continue;
            }
            // Clave suelta: se interpreta como token de la paleta clara.
            $safe = self::safeValue($value);
            if ($safe !== null) {
                $out['light'][self::slot($key)] = $safe;
            }
        }

        return $out;
    }

    /** Valor por defecto de un color de marca de la plataforma. */
    private static function brandDefault(string $name): string
    {
        $brand = (array) (self::config()['brand'] ?? []);
        return self::hex((string) ($brand[$name] ?? '')) ?? '#333333';
    }

    /**
     * Sanea el valor de un token libre.
     *
     * Los tokens se imprimen dentro de un `<style>` del `<head>`, asi que un
     * valor con `<`, `>` o `{}` podria cerrar la etiqueta e inyectar HTML.
     * Ningun valor legitimo (colores, medidas, pilas de fuentes) los necesita.
     */
    private static function safeValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 300) {
            return null;
        }
        if (preg_match('/[<>{}]/', $value) === 1) {
            return null;
        }
        return $value;
    }

    // =====================================================================
    // UTILIDADES DE COLOR
    // =====================================================================

    /** Valida y normaliza un color hexadecimal (#rgb, #rgba, #rrggbb, #rrggbbaa). */
    public static function hex(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^#?([0-9a-f]{3,8})$/i', $value, $m)) {
            return null;
        }

        $hex = strtolower($m[1]);
        $len = strlen($hex);
        if ($len === 3 || $len === 4) {
            $hex = implode('', array_map(static fn (string $c): string => $c . $c, str_split($hex)));
        }
        if (strlen($hex) === 8) {
            $hex = substr($hex, 0, 6); // Se ignora el alfa: los tokens son opacos.
        }
        if (strlen($hex) !== 6) {
            return null;
        }

        return '#' . $hex;
    }

    /** Mezcla dos colores. $weight es la cantidad del segundo (0..1). */
    public static function mix(string $a, string $b, float $weight): string
    {
        $ca = self::rgb($a);
        $cb = self::rgb($b);
        $w = max(0.0, min(1.0, $weight));

        $out = [];
        for ($i = 0; $i < 3; $i++) {
            $out[$i] = (int) round($ca[$i] * (1 - $w) + $cb[$i] * $w);
        }

        return self::toHex($out);
    }

    /** Aclara un color acercandolo al blanco. */
    public static function lighten(string $hex, float $amount): string
    {
        return self::mix($hex, '#ffffff', $amount);
    }

    /** Oscurece un color acercandolo al negro. */
    public static function darken(string $hex, float $amount): string
    {
        return self::mix($hex, '#000000', $amount);
    }

    /** Color de texto legible sobre el fondo indicado (WCAG). */
    public static function contrast(string $hex): string
    {
        return self::luminance($hex) > 0.45 ? self::ON_LIGHT : self::ON_DARK;
    }

    /** Luminancia relativa (0 = negro, 1 = blanco). */
    public static function luminance(string $hex): float
    {
        $rgb = self::rgb($hex);
        $lin = [];
        foreach ($rgb as $i => $channel) {
            $c = $channel / 255;
            $lin[$i] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
    }

    /** Color con transparencia (rgba). */
    public static function rgba(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::rgb($hex);
        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.'));
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = self::hex($hex) ?? '#000000';
        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private static function toHex(array $rgb): string
    {
        return sprintf('#%02x%02x%02x', max(0, min(255, $rgb[0])), max(0, min(255, $rgb[1])), max(0, min(255, $rgb[2])));
    }
}
