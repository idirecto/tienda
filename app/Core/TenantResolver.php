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
 *   2. Subdominio de un dominio base: <slug>.<base_domain>
 *   3. Dominio propio verificado (mt_domains.status = 1)
 *   4. Tienda demo configurada (DEMO_STORE)
 *   5. Primera tienda activa (para no dejar la web sin contenido)
 */
final class TenantResolver
{
    private static ?Tenant $cache = null;

    public static function resolve(): Tenant
    {
        if (self::$cache instanceof Tenant) {
            return self::$cache;
        }

        // 1. Override para desarrollo
        if (Config::get('tenant.allow_query_override', false)) {
            $slug = isset($_GET['tienda']) ? strtolower(trim((string) $_GET['tienda'])) : '';
            if ($slug !== '') {
                $store = Store::findBySlugWithPlan($slug);
                if ($store) {
                    return self::$cache = new Tenant($store);
                }
            }
        }

        $host = self::normalizeHost($_SERVER['HTTP_HOST'] ?? '');

        // 2. Subdominio
        $sub = self::extractSubdomain($host, (array) Config::get('tenant.base_domains', []));
        if ($sub !== null) {
            $store = Store::findBySlugWithPlan($sub);
            if ($store) {
                return self::$cache = new Tenant($store);
            }
        }

        // 3. Dominio propio verificado
        if ($host !== '') {
            $domain = Domain::findVerified($host);
            if ($domain) {
                $store = Store::findWithPlan((int) $domain['store_id']);
                if ($store) {
                    $store['primary_domain'] = $domain['domain'];
                    return self::$cache = new Tenant($store);
                }
            }
        }

        // 4. Demo
        $demo = (string) Config::get('tenant.demo_store', '');
        if ($demo !== '') {
            $store = Store::findBySlugWithPlan($demo);
            if ($store) {
                return self::$cache = new Tenant($store);
            }
        }

        // 5. Primera tienda activa
        $store = Store::firstActiveWithPlan();
        if ($store) {
            return self::$cache = new Tenant($store);
        }

        throw new \RuntimeException(
            'No hay ninguna tienda disponible. Ejecuta las migraciones y las semillas '
            . '(php database/migrate.php) o define DEMO_STORE en .env.'
        );
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
