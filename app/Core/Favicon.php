<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Favicon que debe mostrar cada tienda.
 *
 * Regla unica y en un solo sitio:
 *
 *   1. Si la tienda ha subido su propio favicon y **esta disponible**, se usa.
 *   2. Si no lo ha configurado, esta vacio, la URL no es valida o el fichero ya
 *      no existe, se usa el **favicon predeterminado de Valduran**.
 *   3. Nunca se pinta una URL vacia ni un `href` roto: como ultimo recurso
 *      (configuracion mal puesta y recurso borrado) hay un SVG en linea.
 *
 * El predeterminado sale SIEMPRE de `config/brand.php` (que a su vez lee
 * `BRAND_FAVICON*` del `.env`). La tienda solo escribe su `favicon_url` en
 * `mt_stores`: no puede sustituir ni borrar el global.
 *
 * Cuando cambia el favicon de una tienda se guarda `mt_stores.favicon_version`
 * (un sello de tiempo) y se anyade `?v=` a su URL: el navegador detecta el
 * fichero nuevo y sigue cacheando el resto con normalidad.
 */
final class Favicon
{
    /**
     * Iconos del favicon predeterminado. El tipo MIME se deduce de la extension
     * del recurso, asi que cambiar el `.env` no obliga a tocar codigo.
     */
    private const DEFAULT_SLOTS = [
        ['clave' => 'default', 'rel' => 'icon',             'sizes' => 'any'],
        ['clave' => 'extra',   'rel' => 'icon',             'sizes' => ''],
        ['clave' => 'apple',   'rel' => 'apple-touch-icon', 'sizes' => '180x180'],
    ];

    /**
     * Iconos del favicon predeterminado de la plataforma, listos para el <head>.
     *
     * @return array<int, array{rel:string,href:string,type:string,sizes:string}>
     */
    public static function defaultSources(): array
    {
        $sources = [];
        $vistos = [];

        foreach (self::DEFAULT_SLOTS as $slot) {
            $raw = trim((string) Config::get('brand.favicon.' . $slot['clave'], ''));
            if ($raw === '') {
                continue;
            }

            $href = self::publicUrl($raw);
            if ($href === null || isset($vistos[$href])) {
                continue;
            }
            $vistos[$href] = true;

            $sources[] = [
                'rel'   => $slot['rel'],
                'href'  => self::withVersion($href, (string) Config::get('brand.favicon.version', '')),
                'type'  => self::typeFor($href),
                'sizes' => $slot['sizes'],
            ];
        }

        if ($sources === []) {
            $sources[] = [
                'rel'   => 'icon',
                'href'  => self::inlineFallback(),
                'type'  => 'image/svg+xml',
                'sizes' => 'any',
            ];
        }

        return $sources;
    }

    /** Primer `href` del predeterminado. Nunca devuelve cadena vacia. */
    public static function defaultHref(): string
    {
        return (string) (self::defaultSources()[0]['href'] ?? self::inlineFallback());
    }

    /**
     * Favicon efectivo de una tienda.
     *
     * @return array{href:string,type:string,custom:bool,version:int|string,reason:?string}
     */
    public static function resolve(?Tenant $tenant): array
    {
        $url     = $tenant instanceof Tenant ? trim((string) $tenant->get('favicon_url', '')) : '';
        $key     = $tenant instanceof Tenant ? trim((string) $tenant->get('favicon_key', '')) : '';
        $version = $tenant instanceof Tenant ? (int) ($tenant->get('favicon_version') ?? 0) : 0;

        $reason = null;
        if ($url !== '') {
            $segura = self::safeStoreUrl($url);
            if ($segura === null) {
                $reason = 'url_invalida';
            } elseif (self::localAvailability($segura, $key) !== false) {
                return [
                    'href'    => self::withVersion($segura, $version),
                    'type'    => self::typeFor($segura),
                    'custom'  => true,
                    'version' => $version,
                    'reason'  => null,
                ];
            } else {
                $reason = 'no_disponible';
            }
        }

        $sources = self::defaultSources();

        return [
            'href'    => (string) $sources[0]['href'],
            'type'    => (string) $sources[0]['type'],
            'custom'  => false,
            'version' => (string) Config::get('brand.favicon.version', ''),
            'reason'  => $reason,
        ];
    }

    /**
     * Etiquetas <link> del favicon, listas para inyectar en el <head>.
     *
     * Con favicon propio se emite una sola etiqueta y, si el recurso no se puede
     * cargar en el navegador (por ejemplo un S3 caido, que el servidor no puede
     * comprobar), un pequeno script la cambia por el predeterminado. Sin
     * favicon propio se emiten todos los iconos de la plataforma.
     */
    public static function linkTags(?Tenant $tenant): string
    {
        $favicon = self::resolve($tenant);

        if (!$favicon['custom']) {
            $html = '';
            foreach (self::defaultSources() as $source) {
                $html .= self::linkTag($source['href'], $source['type'], $source['sizes'], $source['rel']);
            }

            return $html;
        }

        $html = self::linkTag($favicon['href'], $favicon['type'], 'any', 'icon', 'favicon-tienda');
        // JSON dentro de <script>: `\u003C` para que un `<` del ajuste no pueda
        // cerrar la etiqueta.
        $defecto = str_replace('<', '\u003C', (string) json_encode(
            self::defaultHref(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
        $html .= '<script>(function(){var l=document.querySelector(\'link[data-favicon-tienda]\');'
            . 'if(!l)return;l.addEventListener("error",function(){'
            . 'l.href=' . $defecto . ';'
            . '});})();</script>';

        return $html;
    }

    /**
     * Valida la URL del favicon de una tienda.
     *
     * Se admiten rutas del propio sitio (`/public/uploads/...`) y URLs
     * absolutas http(s) o protocol-relative; se rechaza cualquier esquema
     * ejecutable (`javascript:`, `data:`) y los caracteres de control o comillas.
     */
    public static function safeStoreUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500) {
            return null;
        }
        if (preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $url) === 1) {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $url) === 1) {
            return $url;
        }
        if (str_starts_with($url, '/') && !str_contains($url, '..')) {
            return $url;
        }

        return null;
    }

    /** Mime probable del icono segun su extension (`''` = que lo decida el navegador). */
    public static function typeFor(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $ext  = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'ico'          => 'image/x-icon',
            'svg'          => 'image/svg+xml',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'avif'         => 'image/avif',
            'gif'          => 'image/gif',
            'jpg', 'jpeg'  => 'image/jpeg',
            default        => '',
        };
    }

    /** Anyade `?v=` (o `&v=`) sin pisar una version ya presente. */
    public static function withVersion(string $url, int|string|null $version): string
    {
        $version = is_string($version) ? trim($version) : $version;
        if ($version === null || $version === '' || $version === 0 || $version === '0') {
            return $url;
        }
        if (preg_match('/[?&]v=/', $url) === 1) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode((string) $version);
    }

    /**
     * ¿Se puede comprobar en disco y esta disponible?
     *
     *   true   -> es local y el fichero existe
     *   false  -> es local y NO existe (hay que caer al predeterminado)
     *   null   -> es remoto: no se puede comprobar sin red (decide el navegador)
     */
    public static function localAvailability(string $url, string $key = ''): ?bool
    {
        if (preg_match('#^(https?:)?//#i', $url) === 1) {
            return null;
        }

        $candidatos = [];

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path !== '' && str_starts_with($path, '/') && !str_contains($path, '..')) {
            $rel = ltrim($path, '/');
            $candidatos[] = TIENDA_BASE . '/' . $rel;
            $candidatos[] = TIENDA_BASE . '/public/' . $rel;
        }

        // La clave del almacenamiento es la fuente mas fiable (cubre el driver
        // local aunque la URL guardada y la carpeta real no coincidan).
        $key = ltrim(trim($key), '/');
        if ($key !== '' && !str_contains($key, '..')
            && strtolower((string) Config::get('storage.driver', 'local')) === 'local'
        ) {
            $candidatos[] = rtrim((string) Config::get('storage.local.path', TIENDA_BASE . '/public/uploads'), '/') . '/' . $key;
        }

        if ($candidatos === []) {
            // Ruta relativa rara que no podemos mapear: no la damos por rota.
            return null;
        }

        foreach ($candidatos as $candidato) {
            if (is_file($candidato)) {
                return true;
            }
        }

        return false;
    }

    /** SVG en linea de ultimo recurso: siempre es una URL valida y se ve. */
    public static function inlineFallback(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            . '<rect width="64" height="64" rx="14" fill="#e30613"/>'
            . '<path d="M14.5 17h10.2L32 38.6 39.3 17h10.2L37.2 48H26.8L14.5 17Z" fill="#ffffff"/>'
            . '</svg>';

        return 'data:image/svg+xml,' . rawurlencode($svg);
    }

    /**
     * URL publica de un recurso por defecto.
     *
     * - URL absoluta o protocol-relative: se devuelve tal cual.
     * - ruta de sitio (`/...`): se le antepone la base de la aplicacion.
     * - ruta relativa a `public/`: se resuelve con `asset()` **solo si el
     *   fichero existe** (si no, null: mejor el SVG en linea que un 404).
     */
    private static function publicUrl(string $raw): ?string
    {
        if (preg_match('#^(https?:)?//#i', $raw) === 1) {
            return $raw;
        }
        if (str_starts_with($raw, '/')) {
            return View::basePath() . $raw;
        }

        $rel = ltrim($raw, '/');
        if (str_contains($rel, '..') || !is_file(TIENDA_BASE . '/public/' . $rel)) {
            return null;
        }

        return asset($rel);
    }

    /** Una etiqueta <link> del favicon. */
    private static function linkTag(string $href, string $type, string $sizes, string $rel, string $dataAttr = ''): string
    {
        $html = '<link rel="' . e($rel) . '" href="' . e($href) . '"';
        if ($type !== '') {
            $html .= ' type="' . e($type) . '"';
        }
        if ($sizes !== '') {
            $html .= ' sizes="' . e($sizes) . '"';
        }
        if ($dataAttr !== '') {
            $html .= ' data-' . e($dataAttr);
        }

        return $html . '>' . "\n";
    }
}
