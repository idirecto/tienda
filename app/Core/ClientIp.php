<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * IP real de la peticion, con proxies de confianza.
 *
 * `REMOTE_ADDR` es la unica fuente fiable si no hay proxy delante. Detras de un
 * reverse proxy/CDN, `X-Forwarded-For` solo se acepta cuando la conexion directa
 * viene de una IP/CIDR incluida en `TRUSTED_PROXIES`. Asi un cliente no puede
 * falsificar su IP enviando una cabecera.
 */
final class ClientIp
{
    public static function fromGlobals(): string
    {
        return self::resolve(
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
            (array) Config::get('app.trusted_proxies', [])
        );
    }

    /**
     * @param list<string> $trustedProxies IPs o CIDR de proxies de confianza
     */
    public static function resolve(
        string $remoteAddr,
        string $forwardedFor = '',
        string $realIp = '',
        array $trustedProxies = []
    ): string {
        $remote = self::normalizeIp($remoteAddr) ?? '0.0.0.0';
        $trusted = self::normalizeRules($trustedProxies);

        if ($trusted === [] || !self::isTrusted($remote, $trusted)) {
            return $remote;
        }

        $chain = [];
        foreach (explode(',', $forwardedFor) as $part) {
            $ip = self::normalizeIp($part);
            if ($ip !== null) {
                $chain[] = $ip;
            }
        }

        if ($chain === []) {
            $real = self::normalizeIp($realIp);

            return $real ?? $remote;
        }

        // Se recorre de derecha a izquierda: el primer salto no confiable es la
        // IP del cliente; los saltos de la derecha son proxies nuestros.
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!self::isTrusted($chain[$i], $trusted)) {
                return $chain[$i];
            }
        }

        return $chain[0];
    }

    /** @param list<string> $rules */
    public static function isTrusted(string $ip, array $rules): bool
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        foreach (self::normalizeRules($rules) as $rule) {
            if (self::matchesRule($packed, $rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reglas que no son ni una IP ni un CIDR validos.
     *
     * @param list<string> $rules
     * @return list<string>
     */
    public static function invalidRules(array $rules): array
    {
        $invalid = [];
        foreach (self::normalizeRules($rules) as $rule) {
            if (!self::ruleIsValid($rule)) {
                $invalid[] = $rule;
            }
        }

        return $invalid;
    }

    /** @param list<string> $rules @return list<string> */
    public static function normalizeRules(array $rules): array
    {
        $out = [];
        foreach ($rules as $rule) {
            $rule = trim((string) $rule);
            if ($rule !== '') {
                $out[] = $rule;
            }
        }

        return array_values(array_unique($out));
    }

    private static function normalizeIp(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return null;
        }
        if (str_starts_with($ip, '[') && str_ends_with($ip, ']')) {
            $ip = substr($ip, 1, -1);
        }
        if (preg_match('/^\[([0-9a-f:.]+)\]:\d+$/i', $ip, $m) === 1) {
            $ip = $m[1];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    private static function ruleIsValid(string $rule): bool
    {
        $parts = explode('/', $rule, 2);
        if (filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (!isset($parts[1])) {
            return true;
        }
        if (!ctype_digit($parts[1])) {
            return false;
        }
        $max = str_contains($parts[0], ':') ? 128 : 32;

        return (int) $parts[1] >= 0 && (int) $parts[1] <= $max;
    }

    private static function matchesRule(string $packedIp, string $rule): bool
    {
        $parts = explode('/', $rule, 2);
        $network = inet_pton($parts[0]);
        if ($network === false || strlen($network) !== strlen($packedIp)) {
            return false;
        }

        if (!isset($parts[1])) {
            return $packedIp === $network;
        }

        $bits = (int) $parts[1];
        $mask = '';
        $length = strlen($network);
        for ($i = 0; $i < $length; $i++) {
            if ($bits >= 8) {
                $mask .= "\xff";
                $bits -= 8;
            } elseif ($bits > 0) {
                $mask .= chr((0xff << (8 - $bits)) & 0xff);
                $bits = 0;
            } else {
                $mask .= "\x00";
            }
        }

        return ($packedIp & $mask) === ($network & $mask);
    }
}
