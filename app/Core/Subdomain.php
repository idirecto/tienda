<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Direccion de una tienda: su subdominio dentro del dominio de la plataforma.
 *
 * La etiqueta se guarda en `mt_stores.slug` (es el identificador que usa el
 * resolver multi-tenant), pero aqui vive TODO lo que hace falta para que el
 * tendero pueda elegirla sin miedo:
 *
 *   - normalizacion (acepta "prueba", "Prueba" o "prueba.ecom-era.com");
 *   - etiquetas reservadas para no romper DNS, correo, panel o servicios;
 *   - comprobacion de que no la usa otra tienda;
 *   - URL publica con el dominio base real;
 *   - diagnostico DNS + web (HTTPS y certificado incluidos).
 *
 * Un subdominio NO se guarda en `mt_domains`: esa tabla es para dominios
 * propios. El subdominio es la direccion de plataforma y funciona aunque la
 * tienda no tenga ningun dominio propio.
 */
final class Subdomain
{
    public const MIN_LENGTH = 3;
    public const MAX_LENGTH = 40;

    /**
     * Etiquetas que no puede usar una tienda.
     *
     * `www` es imprescindible porque el resolver lo excluye expresamente; el
     * resto son nombres habituales de DNS, correo y servicios de la plataforma
     * que no deben quedar ocupados por una tienda.
     */
    private const RESERVED = [
        'www', 'mail', 'email', 'smtp', 'imap', 'pop', 'pop3', 'webmail',
        'autodiscover', 'autoconfig',
        'ftp', 'sftp', 'ns', 'ns1', 'ns2', 'ns3', 'ns4', 'dns',
        'panel', 'admin', 'administrador', 'api', 'app', 'cdn', 'static',
        'assets', 'media', 'localhost',
    ];

    /** Etiquetas reservadas por la plataforma. */
    public static function reserved(): array
    {
        return self::RESERVED;
    }

    /**
     * Deja la entrada en una etiqueta canonica.
     *
     * Acepta una etiqueta suelta o una URL/host completo del dominio de la
     * plataforma. Si el host pertenece a otro dominio se devuelve tal cual para
     * que `formatError()` lo rechace (nunca se convierte en una etiqueta por
     * accidente).
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Quitar esquema, ruta, query y puerto.
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value) ?? '';
        $value = preg_split('~[/?#]~', $value, 2)[0] ?? '';
        $value = strtolower(trim($value));
        $value = preg_replace('/:\d+$/', '', $value) ?? '';
        $value = trim($value, ". \t\n\r\0\x0B");

        // Si llega el host completo de la plataforma, quedarse con la etiqueta.
        $bases = TenantResolver::getBaseDomains();
        usort($bases, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        foreach ($bases as $base) {
            if ($value === $base) {
                return '';
            }
            if (str_ends_with($value, '.' . $base)) {
                $value = substr($value, 0, strlen($value) - strlen('.' . $base));
                break;
            }
        }

        // Un host de otro dominio (con puntos) no es una etiqueta valida.
        if (str_contains($value, '.')) {
            return $value;
        }

        return Str::slugify($value, self::MAX_LENGTH);
    }

    /** Error de formato (independiente de si la etiqueta esta ocupada). */
    public static function formatError(string $label): ?string
    {
        $label = self::normalize($label);

        if ($label === '') {
            return 'Escribe un subdominio para tu tienda.';
        }
        if (str_contains($label, '.')) {
            return 'Usa solo la etiqueta, sin puntos: prueba en lugar de prueba.ecom-era.com.';
        }
        if (strlen($label) < self::MIN_LENGTH) {
            return 'El subdominio debe tener al menos ' . self::MIN_LENGTH . ' caracteres.';
        }
        if (strlen($label) > self::MAX_LENGTH) {
            return 'El subdominio no puede superar los ' . self::MAX_LENGTH . ' caracteres.';
        }
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label) !== 1) {
            return 'El subdominio solo admite letras, numeros y guiones, sin empezar ni terminar por guion.';
        }
        if (in_array($label, self::RESERVED, true)) {
            return 'Ese subdominio esta reservado por la plataforma. Elige otro.';
        }

        return null;
    }

    /**
     * Valida formato y disponibilidad.
     *
     * @param int|null $ignoreStoreId Tienda que se esta editando (su slug actual
     *                                no cuenta como ocupado).
     * @return string|null Mensaje de error, o null si se puede usar.
     */
    public static function validate(string $label, ?int $ignoreStoreId = null): ?string
    {
        $label = self::normalize($label);
        $error = self::formatError($label);
        if ($error !== null) {
            return $error;
        }

        $sql = 'SELECT id FROM mt_stores WHERE slug = :slug';
        $params = ['slug' => $label];
        if ($ignoreStoreId !== null && $ignoreStoreId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreStoreId;
        }
        $sql .= ' LIMIT 1';

        if (Database::scalar($sql, $params) !== null) {
            return 'Ese subdominio ya esta ocupado por otra tienda. Elige otro.';
        }

        // Un dominio propio nunca debe ocupar la direccion de plataforma.
        if (Database::tableExists('mt_domains')) {
            foreach (TenantResolver::getBaseDomains() as $base) {
                if (Database::scalar(
                    'SELECT id FROM mt_domains WHERE domain = :domain LIMIT 1',
                    ['domain' => $label . '.' . $base]
                ) !== null) {
                    return 'Ese subdominio ya esta registrado como dominio en la plataforma.';
                }
            }
        }

        return null;
    }

    /**
     * Etiqueta sugerida y segura a partir de un texto libre.
     *
     * Se usa en el alta de una tienda y como valor inicial del panel.
     */
    public static function suggest(string $value): string
    {
        $label = Str::slugify(self::normalize($value), self::MAX_LENGTH);
        if ($label === '') {
            $label = 'tienda';
        }
        if (strlen($label) < self::MIN_LENGTH) {
            $label = rtrim(substr($label . '-tienda', 0, self::MAX_LENGTH), '-');
        }
        if (in_array($label, self::RESERVED, true)) {
            $suffix = '-tienda';
            $label = rtrim(substr($label, 0, self::MAX_LENGTH - strlen($suffix)), '-') . $suffix;
        }

        return $label;
    }

    /** Dominio base que corresponde al host actual (o el configurado). */
    public static function baseDomain(?string $host = null): string
    {
        $host = TenantResolver::normalizeHost($host ?? Server::host());
        if ($host !== '') {
            $matched = TenantResolver::baseDomainForHost($host);
            if ($matched !== null) {
                return $matched;
            }
        }

        $configured = TenantResolver::normalizeHost((string) Config::get('tenant.public_domain', ''));
        if ($configured !== '') {
            return $configured;
        }

        return TenantResolver::getBaseDomains()[0] ?? '';
    }

    /** Host completo de la tienda: <etiqueta>.<dominio base>. */
    public static function host(string $label, ?string $host = null): string
    {
        $label = self::normalize($label);
        $base = self::baseDomain($host);

        return $base !== '' ? $label . '.' . $base : $label;
    }

    /** URL publica del subdominio (http en local, https en produccion). */
    public static function url(string $label, ?string $host = null): string
    {
        return self::scheme() . '://' . self::host($label, $host);
    }

    /** Esquema que debe usarse en las URLs que se muestran al tendero. */
    public static function scheme(): string
    {
        if (PHP_SAPI === 'cli') {
            $scheme = parse_url((string) Config::get('app.url', ''), PHP_URL_SCHEME);
            return $scheme === 'http' ? 'http' : 'https';
        }

        return Server::scheme();
    }

    /**
     * Comprueba la resolucion DNS del subdominio.
     *
     * @param callable|null $lookup fn(string $tipo, string $host): array
     * @return array{level:string,message:string,host:string,a:array,cname:array,expected_a:string,expected_cname:string}
     */
    public static function dnsStatus(string $label, ?string $host = null, ?callable $lookup = null): array
    {
        $label = self::normalize($label);
        $fullHost = self::host($label, $host);
        $lookup ??= [Dns::class, 'lookupReal'];

        $safe = static function (string $type, string $name) use ($lookup): array {
            try {
                $records = $lookup($type, $name);
                return is_array($records) ? array_values(array_filter(array_map('strval', $records))) : [];
            } catch (\Throwable) {
                return [];
            }
        };

        $a = $safe('A', $fullHost);
        $cname = $safe('CNAME', $fullHost);
        $expectedA = TenantResolver::normalizeHost(Dns::platformIp());
        $expectedCname = TenantResolver::normalizeHost(Dns::platformCname());

        $aMatch = $expectedA !== '' && in_array($expectedA, array_map(
            static fn (string $v): string => TenantResolver::normalizeHost($v),
            $a
        ), true);
        $cnameMatch = $expectedCname !== '' && in_array($expectedCname, array_map(
            static fn (string $v): string => TenantResolver::normalizeHost($v),
            $cname
        ), true);

        $level = 'ok';
        if ($a === [] && $cname === []) {
            $level = 'error';
            $message = 'El subdominio no resuelve todavia. En produccion hace falta el DNS wildcard *.'
                . self::baseDomain($host) . '; en local, anade una entrada a /etc/hosts.';
        } elseif ($aMatch || $cnameMatch) {
            $message = 'El DNS del subdominio apunta a la plataforma.';
        } elseif ($a !== [] && $expectedA === '') {
            // Hay A y no hay IP configurada con la que comparar: el DNS existe.
            $message = 'El subdominio resuelve a ' . implode(', ', $a) . '.';
        } elseif ($cname !== [] && $expectedCname === '') {
            $message = 'El subdominio resuelve por CNAME a ' . implode(', ', $cname) . '.';
        } elseif ($expectedA !== '' && $a !== []) {
            $level = 'warning';
            $message = 'El subdominio resuelve a ' . implode(', ', $a) . ', pero se esperaba ' . $expectedA . '.';
        } else {
            $level = 'warning';
            $message = 'El DNS existe, pero no apunta al valor esperado de la plataforma.';
        }

        return [
            'level'          => $level,
            'message'        => $message,
            'host'           => $fullHost,
            'a'              => $a,
            'cname'          => $cname,
            'expected_a'     => $expectedA,
            'expected_cname' => $expectedCname,
        ];
    }

    /**
     * Diagnostico completo: DNS + carga real del subdominio.
     *
     * La prueba web es la que detecta el fallo tipico de produccion: el DNS
     * wildcard apunta al servidor, pero el certificado TLS solo cubre
     * `ecom-era.com` y no `*.ecom-era.com`. En ese caso se devuelve un error
     * entendible en lugar de dejar al tendero con una pantalla en blanco.
     *
     * @param callable|null $lookup     Inyectable para pruebas sin red.
     * @param callable|null $webProbe   Inyectable: fn(string $scheme, string $host): array
     */
    public static function diagnose(
        string $label,
        ?string $host = null,
        ?callable $lookup = null,
        ?callable $webProbe = null
    ): array {
        $dns = self::dnsStatus($label, $host, $lookup);
        $result = [
            'level'      => $dns['level'],
            'message'    => $dns['message'],
            'host'       => $dns['host'],
            'url'        => self::url($label, $host),
            'checked_at' => date('Y-m-d H:i:s'),
            'dns'        => $dns,
        ];

        if ($dns['level'] === 'error') {
            return $result;
        }

        $probe = $webProbe ?? [self::class, 'probeWeb'];
        $web = $probe(self::scheme(), $dns['host']);
        $result['web'] = $web;

        if (empty($web['ok'])) {
            $result['level'] = 'error';
            $result['message'] = $dns['message'] . ' Pero la web no carga: ' . (string) ($web['message'] ?? 'error desconocido');
        } elseif ($dns['level'] === 'warning') {
            $result['level'] = 'warning';
            $result['message'] = $dns['message'] . ' La web responde correctamente.';
        } else {
            $result['level'] = 'ok';
            $result['message'] = 'El subdominio esta listo y la web responde correctamente.';
        }

        return $result;
    }

    /**
     * Comprueba que el host responde y que su certificado TLS es valido.
     *
     * La conexion se hace en dos pasos (TCP -> TLS) para poder capturar el
     * error real de OpenSSL cuando el certificado no cubre el subdominio. Con
     * `tls://` directo, PHP devuelve un error vacio y el tendero solo veria
     * «no se pudo conectar».
     *
     * @return array{ok:bool,code:int,message:string}
     */
    public static function probeWeb(string $scheme, string $host): array
    {
        $scheme = $scheme === 'http' ? 'http' : 'https';
        $port = $scheme === 'https' ? 443 : 80;
        $target = 'tcp://' . $host . ':' . $port;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, 4, STREAM_CLIENT_CONNECT);

        if ($socket === false) {
            $detail = trim($errstr) !== '' ? trim($errstr) : 'no se pudo conectar a ' . $target;
            return ['ok' => false, 'code' => 0, 'message' => $detail];
        }

        stream_set_timeout($socket, 4);

        if ($scheme === 'https') {
            stream_context_set_option($socket, 'ssl', 'peer_name', $host);
            stream_context_set_option($socket, 'ssl', 'SNI_enabled', true);
            stream_context_set_option($socket, 'ssl', 'verify_peer', true);
            stream_context_set_option($socket, 'ssl', 'verify_peer_name', true);
            stream_context_set_option($socket, 'ssl', 'allow_self_signed', false);

            $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($crypto !== true) {
                $last = error_get_last();
                $detail = trim((string) ($last['message'] ?? ''));
                fclose($socket);
                return ['ok' => false, 'code' => 0, 'message' => self::tlsFailure($host, $detail)];
            }
        }

        // Se pide un ESTATICO (no una pagina PHP): nginx/Apache lo sirven sin
        // pasar por PHP-FPM. Asi la comprobacion no puede quedarse esperando a
        // un pool con un unico worker mientras atiende esta misma peticion.
        $probePath = Server::publicPath() . '/assets/css/shop.css';
        @fwrite($socket, "HEAD {$probePath} HTTP/1.0\r\nHost: {$host}\r\nConnection: close\r\n\r\n");
        $response = '';
        while (!feof($socket) && strlen($response) < 4096) {
            $chunk = fread($socket, 1024);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;
        }
        fclose($socket);

        if (preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $response, $matches) !== 1) {
            return ['ok' => true, 'code' => 0, 'message' => 'La conexion funciona, pero no se pudo leer el estado HTTP.'];
        }

        $code = (int) $matches[1];
        $ok = $code >= 200 && $code < 400;

        return [
            'ok'      => $ok,
            'code'    => $code,
            'message' => 'La web responde con HTTP ' . $code . '.',
        ];
    }

    /** Mensaje entendible para un fallo de TLS, con la pista del wildcard. */
    private static function tlsFailure(string $host, string $detail): string
    {
        $detail = preg_replace('/^stream_socket_enable_crypto\(\):\s*/i', '', $detail) ?? $detail;
        $detail = trim(preg_replace('/\s+/', ' ', $detail) ?? $detail);

        if (stripos($detail, 'certificate') !== false || stripos($detail, 'ssl') !== false) {
            $base = self::baseDomain($host);
            $hint = $base !== ''
                ? 'El certificado TLS no cubre ' . $host . '. Hace falta un certificado wildcard *.' . $base . ' (o uno para este subdominio).'
                : 'El certificado TLS no es valido para ' . $host . '.';

            return $hint . ($detail !== '' ? ' Detalle: ' . mb_substr($detail, 0, 300) : '');
        }

        return 'No se pudo establecer HTTPS con ' . $host . ': ' . ($detail !== '' ? $detail : 'error desconocido');
    }
}
