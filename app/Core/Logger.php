<?php

declare(strict_types=1);

namespace Tienda\Core;

use Throwable;

/**
 * Registro de actividad de la aplicacion, en ficheros por dia y por tienda.
 *
 * Un unico punto de entrada (`Logger::info('compras', 'Pedido registrado', [...])`)
 * escribe una linea JSON en:
 *
 *     storage/logs/<canal>/<AAAA-MM-DD>/<id-tienda>_<slug>.log
 *
 * Reglas de este proyecto:
 *
 *  1. **Nunca rompe la peticion.** Si no se puede escribir (permisos, disco
 *     lleno...), se traga el error y se intenta el log de PHP. Un fallo del log
 *     no puede tumbar una compra.
 *  2. **Nunca guarda secretos.** Contrasenas, tokens CSRF, cookies, tarjetas...
 *     se sustituyen por `***` antes de tocar el disco.
 *  3. **Sabe de que tienda es.** La tienda sale de la sesion del panel o del
 *     hostname (lo fija `index.php`), nunca de un parametro del navegador.
 *  4. **Se limpia solo.** Pasada `log.retention_days`, la limpieza borra los
 *     dias viejos (la lanza el panel, la consola o la propia escritura con una
 *     probabilidad baja).
 *
 * El lector es `Tienda\Core\Log\LogReader`.
 */
final class Logger
{
    /** Niveles ordenados de menos a mas grave (mismo orden que PSR-3). */
    public const LEVELS = [
        'debug'    => 10,
        'info'     => 20,
        'notice'   => 25,
        'warning'  => 30,
        'error'    => 40,
        'critical' => 50,
    ];

    /** Canal que se usa cuando no se indica otro. */
    public const DEFAULT_CHANNEL = 'sistema';

    /** Tienda de la peticion (la fija `index.php` con el tenant resuelto). */
    private static ?int $storeId = null;
    private static ?string $storeSlug = null;

    /** Id de peticion: correlaciona todas las lineas de una misma peticion. */
    private static ?string $requestId = null;

    /** Memo de slug por tienda (una consulta como mucho por peticion). */
    private static array $slugCache = [];

    /** Evita registrar dos veces el manejador de errores fatales. */
    private static bool $handlersReady = false;

    /** Overrides para pruebas (tools/verify.php): no tocar los logs reales. */
    private static ?bool $enabledOverride = null;
    private static ?string $pathOverride = null;

    // -------------------------------------------------------------------------
    // CONTEXTO
    // -------------------------------------------------------------------------

    /**
     * Fija la tienda de la peticion (la resuelve `index.php` por hostname).
     *
     * Es solo el respaldo: si el evento trae su propio `store_id` en el contexto
     * se usa ese, y si hay sesion de panel, la tienda del usuario (un tendero
     * que entra por otro dominio debe ver sus propios logs).
     */
    public static function setStore(?int $id, ?string $slug = null): void
    {
        self::$storeId = ($id !== null && $id > 0) ? $id : null;
        self::$storeSlug = ($slug !== null && $slug !== '') ? self::cleanSlug($slug) : null;
    }

    /** Activa o desactiva el log (util en pruebas; con null vuelve a la config). */
    public static function setEnabled(?bool $enabled): void
    {
        self::$enabledOverride = $enabled;
    }

    /** Fuerza la carpeta de logs (pruebas); con null vuelve a la configuracion. */
    public static function setPath(?string $path): void
    {
        $path = $path !== null ? trim($path) : '';
        self::$pathOverride = $path !== '' ? rtrim($path, '/\\') : null;
    }

    /** Id de la peticion actual (misma cadena en todas sus lineas). */
    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(6));
    }

    public static function enabled(): bool
    {
        if (self::$enabledOverride !== null) {
            return self::$enabledOverride;
        }

        try {
            return (bool) Config::get('log.enabled', true);
        } catch (Throwable) {
            return true;
        }
    }

    // -------------------------------------------------------------------------
    // ESCRITURA
    // -------------------------------------------------------------------------

    public static function debug(string $channel, string $message, array $context = []): void
    {
        self::log('debug', $channel, $message, $context);
    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::log('info', $channel, $message, $context);
    }

    public static function notice(string $channel, string $message, array $context = []): void
    {
        self::log('notice', $channel, $message, $context);
    }

    public static function warning(string $channel, string $message, array $context = []): void
    {
        self::log('warning', $channel, $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::log('error', $channel, $message, $context);
    }

    public static function critical(string $channel, string $message, array $context = []): void
    {
        self::log('critical', $channel, $message, $context);
    }

    /**
     * Escribe una entrada. Es el metodo de todos los demas.
     *
     * @param string $level   debug|info|notice|warning|error|critical
     * @param string $channel Canal (compras, pedidos, acceso, sistema...)
     * @param array  $context Datos utiles para diagnosticar (se redactan)
     */
    public static function log(string $level, string $channel, string $message, array $context = []): void
    {
        try {
            self::write($level, $channel, $message, $context);
        } catch (Throwable) {
            // Un fallo del log jamas puede romper la peticion del cliente.
        }
    }

    // -------------------------------------------------------------------------
    // UBICACION DE LOS FICHEROS
    // -------------------------------------------------------------------------

    /** Carpeta raiz de los logs (config, o el override de pruebas). */
    public static function path(): string
    {
        if (self::$pathOverride !== null) {
            return self::$pathOverride;
        }

        $path = (string) Config::get('log.path', TIENDA_BASE . '/storage/logs');
        if (trim($path) === '') {
            $path = TIENDA_BASE . '/storage/logs';
        }

        return rtrim($path, '/\\');
    }

    /**
     * Ruta del fichero de un canal, un dia y una tienda.
     *
     * El nombre del fichero es `<id>_<slug>.log`; sin tienda (plataforma, CLI,
     * errores antes de resolver el hostname) es `_plataforma.log`. El id delante
     * permite identificar (y borrar) el log de una tienda aunque cambie de slug.
     */
    public static function filePath(
        string $channel,
        ?string $day = null,
        ?int $storeId = null,
        ?string $storeSlug = null
    ): string {
        $day = $day !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1
            ? $day
            : date('Y-m-d');

        if ($storeId === null && $storeSlug === null) {
            [$storeId, $storeSlug] = self::resolveStore();
        }

        if ($storeId !== null && $storeId > 0) {
            $slug = self::cleanSlug((string) $storeSlug);
            $file = $storeId . '_' . ($slug !== '' ? $slug : 'tienda');
        } else {
            $file = '_plataforma';
        }

        return self::path() . '/' . self::cleanChannel($channel) . '/' . $day . '/' . $file . '.log';
    }

    /** Dias con algun log, del mas reciente al mas antiguo. */
    public static function days(int $limit = 0): array
    {
        $days = [];
        foreach (glob(self::path() . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $day = basename($dir);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
                $days[$day] = true;
            }
        }
        $days = array_keys($days);
        rsort($days);

        return $limit > 0 ? array_slice($days, 0, $limit) : $days;
    }

    /**
     * Borra los ficheros mas antiguos que `log.retention_days`.
     *
     * @return int Numero de ficheros borrados
     */
    public static function gc(bool $force = false): int
    {
        $base = self::path();
        if (!is_dir($base) && !@mkdir($base, 0775, true) && !is_dir($base)) {
            return 0;
        }

        $days = max(1, (int) Config::get('log.retention_days', 30));
        $limit = strtotime('today 00:00:00') - ($days * 86400);
        $deleted = 0;

        foreach (glob($base . '/*/*', GLOB_ONLYDIR) ?: [] as $dayDir) {
            $day = basename($dayDir);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                continue;
            }
            $ts = strtotime($day . ' 00:00:00');
            if ($ts === false || $ts > $limit) {
                continue;
            }

            foreach (glob($dayDir . '/*.log') ?: [] as $file) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
            @rmdir($dayDir);
            @rmdir(dirname($dayDir)); // el canal, si se queda sin dias
        }

        @touch($base . '/.gc');

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // ERRORES FATALES
    // -------------------------------------------------------------------------

    /**
     * Deja registrados los errores fatales de PHP (los que no se pueden capturar
     * como excepcion: memoria agotada, error de compilacion...). El resto de la
     * peticion (excepciones) los registra `index.php`.
     */
    public static function registerHandlers(): void
    {
        if (self::$handlersReady) {
            return;
        }
        self::$handlersReady = true;
        register_shutdown_function([self::class, 'captureShutdown']);
    }

    /** Vuelca el ultimo error fatal, si lo hubo, al log de sistema. */
    public static function captureShutdown(): void
    {
        try {
            $error = error_get_last();
            if (!is_array($error)) {
                return;
            }

            $fatals = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
            if (((int) ($error['type'] ?? 0) & $fatals) === 0) {
                return;
            }

            self::critical(self::DEFAULT_CHANNEL, 'Error fatal: ' . (string) ($error['message'] ?? ''), [
                'file' => (string) ($error['file'] ?? '') . ':' . (int) ($error['line'] ?? 0),
                'type' => (int) ($error['type'] ?? 0),
            ]);
        } catch (Throwable) {
            // Nada que hacer: ya estamos en el apagado.
        }
    }

    // -------------------------------------------------------------------------
    // INTERNOS
    // -------------------------------------------------------------------------

    private static function write(string $level, string $channel, string $message, array $context): void
    {
        if (!self::enabled()) {
            return;
        }

        $level = strtolower(trim($level));
        if (!isset(self::LEVELS[$level])) {
            $level = 'info';
        }
        if (self::LEVELS[$level] < self::minLevel()) {
            return;
        }

        $context = self::redact($context);
        [$storeId, $storeSlug] = self::resolveStore($context);

        $entry = [
            'ts'         => date('c'),
            'level'      => $level,
            'channel'    => self::cleanChannel($channel),
            'store_id'   => $storeId,
            'store'      => $storeSlug,
            'request_id' => self::requestId(),
            'message'    => self::cleanMessage($message),
            'context'    => $context,
        ];

        if (PHP_SAPI !== 'cli') {
            $entry['http'] = [
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                'url'    => self::requestUrl(),
                'ip'     => self::clientIp(),
            ];
            $user = self::userTag();
            if ($user !== null) {
                $entry['user'] = $user;
            }
        }

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"level":"error","channel":"' . self::cleanChannel($channel)
                . '","message":"entrada de log no serializable"}';
        }

        $file = self::filePath($channel, null, $storeId, $storeSlug);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            @error_log('[tienda] ' . $json);

            return;
        }

        if (@file_put_contents($file, $json . "\n", FILE_APPEND | LOCK_EX) === false) {
            // Ultimo recurso: al log de PHP, para no perder el rastro.
            @error_log('[tienda] ' . $json);
        }

        self::maybeGc();
    }

    /** Nivel minimo configurado (por debajo no se escribe nada). */
    private static function minLevel(): int
    {
        $name = strtolower(trim((string) Config::get('log.level', 'info')));

        return self::LEVELS[$name] ?? self::LEVELS['info'];
    }

    /**
     * Tienda del evento. Prioridad:
     *
     *  1. El `store_id` que el propio evento declara en su contexto (es el dato
     *     mas fiable: una compra sabe de que tienda es).
     *  2. La sesion del panel (el usuario pertenece a una tienda concreta).
     *  3. La tienda resuelta por hostname (la fija `index.php`).
     *
     * @param array<string,mixed> $context Contexto ya redactado
     * @return array{0:?int,1:?string}
     */
    private static function resolveStore(array $context = []): array
    {
        $explicit = $context['store_id'] ?? null;
        if (is_int($explicit) || (is_string($explicit) && ctype_digit($explicit))) {
            $id = (int) $explicit;
            if ($id > 0) {
                if ($id === self::$storeId && self::$storeSlug !== null) {
                    return [$id, self::$storeSlug];
                }

                return [$id, self::slugFor($id)];
            }
        }

        $authId = Auth::storeId();
        if ($authId !== null && $authId > 0) {
            return [$authId, self::slugFor($authId)];
        }

        if (self::$storeId !== null) {
            return [self::$storeId, self::$storeSlug ?? self::slugFor(self::$storeId)];
        }

        return [null, null];
    }

    /** Slug de la tienda, con memo por peticion. Si la BD falla, se usa ''. */
    private static function slugFor(int $storeId): string
    {
        if (array_key_exists($storeId, self::$slugCache)) {
            return self::$slugCache[$storeId];
        }

        $slug = '';
        try {
            $slug = (string) (Database::scalar(
                'SELECT slug FROM mt_stores WHERE id = :id',
                ['id' => $storeId]
            ) ?? '');
        } catch (Throwable) {
            $slug = '';
        }

        return self::$slugCache[$storeId] = self::cleanSlug($slug);
    }

    /** Canal seguro para el nombre de carpeta. */
    private static function cleanChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        $channel = preg_replace('/[^a-z0-9_-]+/', '-', $channel) ?? '';
        $channel = trim($channel, '-');

        return $channel !== '' ? mb_substr($channel, 0, 40) : self::DEFAULT_CHANNEL;
    }

    /** Slug seguro para el nombre de fichero. */
    private static function cleanSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return mb_substr($slug, 0, 60);
    }

    private static function cleanMessage(string $message): string
    {
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);

        return mb_substr($message, 0, 500);
    }

    /**
     * Sustituye por `***` cualquier dato sensible (contrasenas, tokens, tarjetas).
     * Se aplica a las claves del contexto, en cualquier nivel de anidamiento.
     */
    private static function redact(array $data, int $depth = 0): array
    {
        if ($depth > 3) {
            return ['aviso' => 'contexto demasiado profundo'];
        }

        $out = [];
        foreach ($data as $key => $value) {
            $name = strtolower((string) $key);

            if (preg_match('/(pass|clave|token|csrf|secret|authorization|cookie|cvv|tarjeta|card|iban|firma)/', $name) === 1) {
                $out[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $out[$key] = self::redact($value, $depth + 1);
            } elseif (is_object($value)) {
                $out[$key] = 'objeto:' . get_class($value);
            } elseif (is_string($value)) {
                $out[$key] = mb_substr($value, 0, 500);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $out[$key] = $value;
            } else {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    /** URL de la peticion, con los parametros sensibles tapados. */
    private static function requestUrl(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $uri = preg_replace('/((?:password|pass|token|_token|csrf|clave|secret)=)[^&]*/i', '$1***', $uri) ?? $uri;

        return mb_substr($uri, 0, 300);
    }

    private static function clientIp(): string
    {
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if ($first !== '') {
                return mb_substr($first, 0, 45);
            }
        }

        return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    /** Quien hace la peticion: `panel:<id>` o null (cliente final/anónimo). */
    private static function userTag(): ?string
    {
        $user = Auth::user();
        if (is_array($user) && !empty($user['id'])) {
            return 'panel:' . (int) $user['id'];
        }

        return null;
    }

    /**
     * Limpieza si toca: borra los dias mas antiguos que la retencion, como
     * muchisimo una vez cada `log.gc_interval`.
     *
     * @return int Ficheros borrados (0 si todavia no toca)
     */
    public static function gcIfDue(): int
    {
        $interval = (int) Config::get('log.gc_interval', 86400);
        if ($interval <= 0) {
            return 0;
        }

        $stamp = self::path() . '/.gc';
        $last = is_file($stamp) ? (int) @filemtime($stamp) : 0;
        if ($last > 0 && (time() - $last) < $interval) {
            return 0;
        }

        return self::gc();
    }

    /**
     * Limpieza de vez en cuando: con probabilidad baja (no en cada peticion),
     * para no pagar el coste de `glob` cada vez que se escribe una linea.
     */
    private static function maybeGc(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }

        self::gcIfDue();
    }
}
