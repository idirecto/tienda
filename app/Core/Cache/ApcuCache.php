<?php

declare(strict_types=1);

namespace Tienda\Core\Cache;

/**
 * Driver APCu: cache en memoria compartida del propio servidor web.
 *
 * Es el salto de rendimiento mas rentable cuando la web corre en una sola
 * maquina: no hay ficheros, ni red, ni serializacion, y el valor lo comparten
 * todos los procesos de PHP (`php-fpm`/`mod_php`). No requiere instalar ningun
 * servicio: solo la extension `apcu` (`php8.4-apcu`).
 *
 * APCu guarda el TTL y libera memoria por su cuenta, asi que este driver no
 * necesita la limpieza del de fichero.
 */
final class ApcuCache implements CacheInterface
{
    public function __construct(private readonly string $prefix = 'tienda:')
    {
    }

    public function name(): string
    {
        return 'apcu';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $ok = false;
        $value = apcu_fetch($this->prefix . $key, $ok);

        return $ok ? $value : $default;
    }

    public function set(string $key, mixed $value, int $ttl): void
    {
        @apcu_store($this->prefix . $key, $value, max(1, $ttl));
    }

    public function delete(string $key): bool
    {
        return (bool) @apcu_delete($this->prefix . $key);
    }

    public function flush(): void
    {
        @apcu_clear_cache();
    }

    public function forgetPattern(string $pattern): int
    {
        if (!class_exists(\APCuIterator::class)) {
            return 0;
        }

        $iterator = new \APCuIterator($this->regex($pattern), \APC_ITER_KEY);
        $borrados = 0;
        foreach ($iterator as $item) {
            if (isset($item['key']) && @apcu_delete((string) $item['key'])) {
                $borrados++;
            }
        }

        return $borrados;
    }

    /** Convierte un patron glob (`*`) en la expresion regular que espera APCu. */
    private function regex(string $pattern): string
    {
        $cuerpo = '';
        foreach (str_split($pattern) as $char) {
            $cuerpo .= $char === '*' ? '.*' : preg_quote($char, '/');
        }

        return '/^' . preg_quote($this->prefix, '/') . $cuerpo . '$/';
    }
}
