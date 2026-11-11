<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Models\Domain;
use Tienda\Models\Store;

/**
 * Resuelve que tienda debe mostrarse a partir del hostname de la peticion.
 *
 * Orden:
 *   1. Override por query string (?tienda=slug) solo fuera de produccion.
 *   2. Dominio principal/apex o www: portada estatica de IDIRECTO, sin tienda.
 *   3. Subdominio de un dominio base: <slug>.<base_domain>.
 *   4. Dominio propio verificado (mt_domains.status = 1).
 *   5. Host sin tienda: portada de plataforma (o 404 si la ruta no es publica).
 *
 * Ya no se cae a la tienda demo ni a la primera tienda activa: entrar por el
 * dominio principal nunca debe mostrar el nombre, logo o catalogo de una tienda.
 */
final class TenantResolver
{
    private static ?Tenant $cache = null;

    public static function resolve(?string $path = null): Tenant
    {
        if (self::$cache instanceof Tenant) {
            return self::$cache;
        }

        $path ??= self::requestPath();

        // 1. Override para desarrollo
        if (Config::get('tenant.allow_query_override', false)) {
            $slug = isset($_GET['tienda']) ? strtolower(trim((string) $_GET['tienda'])) : '';
            if ($slug !== '') {
                $store = self::storeForSlug($slug);
                if ($store) {
                    return self::$cache = new Tenant($store);
                }
            }
        }

        $host = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');

        // 2. Dominio principal o www: nunca se resuelve una tienda.
        if ($host === '' || PlatformLanding::isApexHost($host)) {
            return self::$cache = self::platformContext($path);
        }

        // 3. Subdominio de tienda
        $sub = self::extractSubdomain($host, (array) Config::get('tenant.base_domains', []));
        if ($sub !== null) {
            $store = self::storeForSlug($sub);
            if ($store) {
                return self::$cache = new Tenant($store);
            }

            // Subdominio inexistente: portada de plataforma, sin datos de tienda.
            return self::$cache = self::platformContext($path);
        }

        // 4. Dominio propio verificado
        if ($host !== '') {
            $domain = Domain::findVerified($host);
            if ($domain) {
                $store = Store::findWithPlan((int) $domain['store_id']);
                if ($store !== null) {
                    self::assertPubliclyActive($store);
                    $store['primary_domain'] = $domain['domain'];
                    return self::$cache = new Tenant($store);
                }
            }
        }

        // 5. Host desconocido: solo las rutas de plataforma; el resto, 404.
        return self::$cache = self::platformContext($path);
    }

    /**
     * Contexto para el dominio principal o un host sin tienda.
     *
     * La portada se sirve mediante PlatformLandingException para que el front
     * controller la pinte sin pasar por ningun controlador de tienda.
     */
    private static function platformContext(string $path): Tenant
    {
        if (PlatformLanding::isLandingPath($path)) {
            throw new PlatformLandingException('Portada de plataforma.');
        }
        if (PlatformLanding::isPlatformRoute($path)) {
            return Tenant::platform();
        }

        throw new TenantUnavailableException('No hay una tienda en este dominio.');
    }

    private static function requestPath(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return '/' . trim($path, '/');
    }

    /**
     * Tienda de un slug solo si puede atender trafico publico.
     *
     * Si el slug existe pero esta pendiente o suspendido, se corta con una
     * excepcion propia para que index.php responda 404. No se cae a la tienda
     * demo porque eso mezclaria el host inactivo con otra tienda.
     */
    private static function storeForSlug(string $slug): ?array
    {
        $store = Store::findBySlugWithPlan($slug);
        if ($store === null) {
            return null;
        }

        self::assertPubliclyActive($store);

        return $store;
    }

    /** @param array<string,mixed> $store */
    private static function assertPubliclyActive(array $store): void
    {
        if (!Store::statusAllowsPublicAccess($store['status'] ?? null)) {
            throw new TenantUnavailableException('La tienda no esta disponible.');
        }
    }

    /** Solo para pruebas: permite resolver otro host en la misma ejecucion. */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    public static function normalizeHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host) ?? '';
        return trim($host, '.');
    }

    /** Dominios base configurados (subdominios de tienda). */
    public static function getBaseDomains(): array
    {
        $out = [];
        foreach ((array) Config::get('tenant.base_domains', []) as $d) {
            $d = self::normalizeHost((string) $d);
            if ($d !== '') {
                $out[] = $d;
            }
        }
        return $out;
    }

    /**
     * Dominio base al que pertenece un host, si es de la plataforma.
     *
     * Devuelve el dominio base tanto para el apex (ecom-era.com), como para
     * www.ecom-era.com y para un subdominio de un nivel
     * (prueba.ecom-era.com). Para un dominio propio devuelve null.
     */
    public static function baseDomainForHost(string $host): ?string
    {
        $host = self::normalizeHost($host);
        if ($host === '') {
            return null;
        }

        $bases = self::getBaseDomains();
        usort($bases, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($bases as $base) {
            if ($host === $base || $host === 'www.' . $base) {
                return $base;
            }
            if (str_ends_with($host, '.' . $base)) {
                $sub = substr($host, 0, strlen($host) - strlen('.' . $base));
                if ($sub !== '' && !str_contains($sub, '.') && $sub !== 'www') {
                    return $base;
                }
            }
        }

        return null;
    }

    /** ¿El host pertenece al dominio de la plataforma (apex, www o subdominio)? */
    public static function isPlatformHost(string $host): bool
    {
        return self::baseDomainForHost($host) !== null;
    }

    /**
     * Devuelve la etiqueta de subdominio si el host cuelga de un dominio base.
     */
    public static function extractSubdomain(string $host, array $baseDomains): ?string
    {
        if ($host === '' || $baseDomains === []) {
            return null;
        }

        usort($baseDomains, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($baseDomains as $base) {
            $base = self::normalizeHost((string) $base);
            if ($base === '') {
                continue;
            }
            if ($host === $base || $host === 'www.' . $base) {
                return null;
            }
            if (str_ends_with($host, '.' . $base)) {
                $sub = substr($host, 0, strlen($host) - strlen('.' . $base));
                if ($sub !== '' && !str_contains($sub, '.') && $sub !== 'www') {
                    return $sub;
                }
                return null;
            }
        }

        return null;
    }
}
