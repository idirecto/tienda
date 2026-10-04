<?php

declare(strict_types=1);

namespace Tienda\Core\Cache;

/**
 * Driver de fichero: un JSON por clave dentro de `storage/cache`.
 *
 * Es el que ya usaba el proyecto, pero con tres arreglos:
 *
 *  - **Escritura atomica** (fichero temporal + `rename`): un lector nunca ve
 *    medio JSON por coincidir con el momento en que otro escribe.
 *  - **Caducidad dentro del fichero** (`e`): antes el TTL vivia en el
 *    `filemtime`, asi que no se podia distinguir una entrada viva de una
 *    caducada sin conocer su TTL.
 *  - **Limpieza**: las entradas caducadas y los ficheros viejos se borran poco a
 *    poco (nunca se borraba nada y la carpeta crecia sin fin).
 *
 * El formato antiguo (`{value, ts}` o el valor crudo) se trata como si no
 * existiera: se recalcula y se reescribe con el formato nuevo, de modo que una
 * entrada vieja jamas se sirve como si estuviera fresca.
 */
final class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $dir,
        private readonly int $gcMaxAge = 172800,
        private readonly int $gcMaxFiles = 2000,
        private readonly int $gcInterval = 300
    ) {
    }

    public function name(): string
    {
        return 'file';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return $default;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return $default;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('v', $data)) {
            // Formato antiguo: se ignora (mejor recalcular que servir algo
            // viejo). La limpieza acabara borrandolo.
            return $default;
        }

        if ((int) ($data['e'] ?? 0) < time()) {
            return $default;
        }

        return $data['v'];
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        $file = $this->file($key);
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return;
        }

        $payload = json_encode(
            ['v' => $value, 'e' => time() + max(1, $ttl), 't' => time()],
            JSON_UNESCAPED_UNICODE
        );
        if ($payload === false) {
            return;
        }

        // Temporal + rename: el rename es atomico en el mismo sistema de ficheros.
        $tmp = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return;
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return;
        }

        $this->maybeGc();
    }

    public function delete(string $key): bool
    {
        $file = $this->file($key);

        return is_file($file) && @unlink($file);
    }

    public function flush(): void
    {
        foreach ($this->jsonFiles() as $file) {
            @unlink($file);
        }
        foreach ($this->lockFiles() as $file) {
            @unlink($file);
        }
    }

    /**
     * Borra las claves que encajen con el patron. Los caracteres se sanean igual
     * que en `file()`, pero **se conservan los comodines** (`*`), que es lo que
     * distingue un patron de una clave.
     */
    public function forgetPattern(string $pattern): int
    {
        $safe = $this->sanitizePattern($pattern);
        $borrados = 0;
        foreach (glob($this->dir . '/' . $safe . '.json') ?: [] as $file) {
            if (is_file($file) && @unlink($file)) {
                $borrados++;
            }
        }

        return $borrados;
    }

    /**
     * Limpieza: borra caducados, ficheros demasiado viejos y, si hace falta,
     * los mas antiguos hasta bajar del tope. Se ejecuta como mucho una vez por
     * `gcInterval` para no penalizar cada escritura.
     *
     * @param bool $force Ignora el intervalo (pruebas y herramientas)
     * @return int Numero de ficheros borrados
     */
    public function gc(bool $force = false): int
    {
        if (!is_dir($this->dir)) {
            return 0;
        }

        $stamp = $this->dir . '/.gc';
        if (!$force) {
            $last = is_file($stamp) ? (int) @filemtime($stamp) : 0;
            if (time() - $last < max(1, $this->gcInterval)) {
                return 0;
            }
        }
        @touch($stamp);

        $ahora = time();
        $borrados = 0;
        $vivos = [];

        foreach ($this->jsonFiles() as $file) {
            $mtime = (int) @filemtime($file);
            if ($mtime < $ahora - $this->gcMaxAge) {
                if (@unlink($file)) {
                    $borrados++;
                }
                continue;
            }
            $vivos[$file] = $mtime;
        }

        // Tope de tamano: fuera los mas antiguos.
        if ($this->gcMaxFiles > 0 && count($vivos) > $this->gcMaxFiles) {
            asort($vivos);
            $sobran = count($vivos) - $this->gcMaxFiles;
            foreach (array_keys($vivos) as $file) {
                if ($sobran-- <= 0) {
                    break;
                }
                if (@unlink($file)) {
                    $borrados++;
                }
            }
        }

        // Los ficheros de bloqueo no llevan datos: se limpian si son viejos.
        foreach ($this->lockFiles() as $file) {
            if ((int) @filemtime($file) < $ahora - max(3600, $this->gcInterval * 4)) {
                @unlink($file);
            }
        }

        return $borrados;
    }

    /** Ruta del fichero de una clave. */
    public function file(string $key): string
    {
        return $this->dir . '/' . $this->sanitize($key) . '.json';
    }

    private function sanitize(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/i', '_', $key) ?? md5($key);
    }

    /** Igual que `sanitize()`, pero deja pasar los comodines (`*`). */
    private function sanitizePattern(string $pattern): string
    {
        return preg_replace('/[^a-z0-9_\-\*]/i', '_', $pattern) ?? md5($pattern);
    }

    /** @return array<int,string> */
    private function jsonFiles(): array
    {
        return array_values(array_filter(
            glob($this->dir . '/*.json') ?: [],
            static fn (string $f): bool => is_file($f)
        ));
    }

    /** @return array<int,string> */
    private function lockFiles(): array
    {
        return array_values(array_filter(
            glob($this->dir . '/.lock_*') ?: [],
            static fn (string $f): bool => is_file($f)
        ));
    }

    private function maybeGc(): void
    {
        $this->gc(false);
    }
}
