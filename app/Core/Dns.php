<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Models\DnsLog;
use Tienda\Models\Domain;

/**
 * Verificacion DNS de dominios propios (modelo Tiendanube: el cliente apunta su
 * dominio a nuestra infraestructura y aqui comprobamos que la resolucion es
 * correcta).
 */
final class Dns
{
    public const PENDING    = 0;
    public const VERIFIED   = 1;
    public const ERROR      = 2;

    public const METHOD_A     = 'A';
    public const METHOD_CNAME = 'CNAME';
    public const METHOD_BOTH  = 'BOTH';

    public static function platformIp(): string
    {
        return (string) Config::get('tenant.platform_ip', '');
    }

    public static function platformCname(): string
    {
        return (string) Config::get('tenant.platform_cname', '');
    }

    /** Instrucciones DNS que el panel muestra al cliente. */
    public static function instructions(string $domain): array
    {
        return [
            'records' => [
                ['type' => 'A',     'host' => '@',   'value' => self::platformIp(),    'ttl' => 3600],
                ['type' => 'CNAME', 'host' => 'www', 'value' => self::platformCname(), 'ttl' => 3600],
            ],
            'notes' => [
                'El dominio se compra y se gestiona en tu registrador; aqui solo se enlaza por DNS.',
                'Los cambios pueden tardar hasta 24-48 h en propagarse (lo normal: 5-30 min).',
                'No cambies los servidores DNS (NS); basta con el registro A y/o el CNAME.',
            ],
        ];
    }

    /**
     * Comprueba un dominio y actualiza su estado en BD.
     * @param callable|null $lookup fn(string $type, string $host): array  (inyectable en tests)
     */
    public static function verify(int $domainId, ?callable $lookup = null): array
    {
        $domain = Domain::find($domainId);
        if (!$domain) {
            return ['status' => self::ERROR, 'message' => 'Dominio no encontrado'];
        }

        $result = self::evaluate(
            (string) $domain['domain'],
            (string) ($domain['expected_a'] ?: self::platformIp()),
            (string) ($domain['expected_cname'] ?: self::platformCname()),
            (string) ($domain['method'] ?: self::METHOD_A),
            $lookup
        );

        Domain::updateById($domainId, [
            'status'       => $result['status'],
            'last_check'   => date('Y-m-d H:i:s'),
            'last_result'  => mb_substr($result['message'], 0, 500),
            'verified_at'  => $result['status'] === self::VERIFIED
                ? ($domain['verified_at'] ?: date('Y-m-d H:i:s'))
                : $domain['verified_at'],
        ]);

        DnsLog::create([
            'domain_id'    => $domainId,
            'status'       => $result['status'],
            'records_json' => json_encode($result['records'], JSON_UNESCAPED_UNICODE),
            'message'      => mb_substr($result['message'], 0, 500),
        ]);

        return $result;
    }

    /** Evaluacion pura (testeable sin red). */
    public static function evaluate(
        string $domain,
        string $expectedIp,
        string $expectedCname,
        string $method = self::METHOD_A,
        ?callable $lookup = null
    ): array {
        $domain = TenantResolver::normalizeHost($domain);
        $lookup ??= [self::class, 'lookupReal'];

        $a     = self::safeLookup($lookup, 'A', $domain);
        $cname = self::safeLookup($lookup, 'CNAME', $domain);

        $aOk     = self::contains($a, $expectedIp);
        $cnameOk = self::contains($cname, $expectedCname);

        // Si el CNAME apunta al host de plataforma, vale tambien como A implicito.
        if (!$aOk && $cnameOk && $expectedIp !== '') {
            $aOk = self::contains(self::safeLookup($lookup, 'A', $expectedCname), $expectedIp);
        }

        $ok = match ($method) {
            self::METHOD_CNAME => $cnameOk,
            self::METHOD_BOTH  => $aOk && $cnameOk,
            default            => $aOk,
        };

        if ($ok) {
            return [
                'status'  => self::VERIFIED,
                'message' => 'Registros DNS correctos. Dominio verificado.',
                'records' => ['a' => $a, 'cname' => $cname],
            ];
        }

        $message = ($a === [] && $cname === [])
            ? 'El dominio todavia no resuelve. Revisa los registros DNS y espera la propagacion.'
            : 'Los registros existen pero no apuntan al valor esperado.';

        return [
            'status'  => self::ERROR,
            'message' => $message,
            'records' => ['a' => $a, 'cname' => $cname],
        ];
    }

    /** Lookup real por DNS. Callback por defecto de evaluate(). */
    public static function lookupReal(string $type, string $host): array
    {
        $out = [];
        $const = match ($type) {
            'A'     => DNS_A,
            'CNAME' => DNS_CNAME,
            default => null,
        };
        if ($const === null) {
            return $out;
        }

        $records = @dns_get_record($host, $const);
        foreach (is_array($records) ? $records : [] as $r) {
            if ($type === 'A' && !empty($r['ip'])) {
                $out[] = $r['ip'];
            } elseif ($type === 'CNAME' && !empty($r['target'])) {
                $out[] = rtrim((string) $r['target'], '.');
            }
        }

        if ($type === 'A' && $out === []) {
            $ip = @gethostbyname($host);
            if ($ip && $ip !== $host) {
                $out[] = $ip;
            }
        }

        return array_values(array_unique($out));
    }

    private static function safeLookup(callable $lookup, string $type, string $host): array
    {
        try {
            $r = $lookup($type, $host);
            return is_array($r) ? $r : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function contains(array $values, string $expected): bool
    {
        $expected = TenantResolver::normalizeHost($expected);
        if ($expected === '') {
            return false;
        }
        foreach ($values as $v) {
            if (TenantResolver::normalizeHost((string) $v) === $expected) {
                return true;
            }
        }
        return false;
    }
}
