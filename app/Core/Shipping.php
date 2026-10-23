<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Gastos de envio de la tienda.
 *
 * Cada tienda los configura en Ajustes: una tarifa fija y, si quiere, envio
 * gratis a partir de un importe. Se calculan sobre el importe que paga el cliente
 * (con IVA), que es como lo piensa el tendero cuando dice "gratis desde 50 €".
 *
 * La configuracion puede ser general o por zona comercial. Si la zona no tiene
 * coste/umbral propios, se hereda la tarifa general de la tienda. No se calcula
 * por peso: el catalogo no ofrece pesos fiables.
 */
final class Shipping
{
    /**
     * Coste del envio para ese importe de productos (ya con IVA).
     *
     * @param array<string,mixed>|null $zone Configuracion de ShippingZone::storeConfig()
     */
    public static function cost(array $store, float $subtotal, ?array $zone = null): float
    {
        [$flat, $free] = self::effectiveConfig($store, $zone);

        if ($flat <= 0) {
            return 0.0;
        }

        if ($free !== null && (float) $free > 0 && $subtotal >= (float) $free) {
            return 0.0;
        }

        return $flat;
    }

    /**
     * Cuanto falta para el envio gratis (0 si ya no aplica).
     *
     * @param array<string,mixed>|null $zone Configuracion de la zona
     */
    public static function missingForFree(array $store, float $subtotal, ?array $zone = null): float
    {
        [$flat, $free] = self::effectiveConfig($store, $zone);
        if ($free === null || (float) $free <= 0 || $flat <= 0) {
            return 0.0;
        }

        $falta = round((float) $free - $subtotal, 2);

        return $falta > 0 ? $falta : 0.0;
    }

    /**
     * Hay envio gratis configurado (para poder anunciarlo en el carrito).
     *
     * @param array<string,mixed>|null $zone Configuracion de la zona
     */
    public static function hasFreeFrom(array $store, ?array $zone = null): bool
    {
        [$flat, $free] = self::effectiveConfig($store, $zone);

        return $free !== null
            && (float) $free > 0
            && $flat > 0;
    }

    /**
     * Coste y umbral efectivos: la zona manda; si el campo esta a NULL se
     * hereda la configuracion general de la tienda.
     *
     * @param array<string,mixed>|null $zone
     * @return array{0:float,1:float|int|null}
     */
    private static function effectiveConfig(array $store, ?array $zone): array
    {
        $flat = $zone !== null && array_key_exists('cost', $zone) && $zone['cost'] !== null
            ? (float) $zone['cost']
            : (float) ($store['shipping_flat'] ?? 0);
        $free = $zone !== null
            && array_key_exists('free_from', $zone)
            && $zone['free_from'] !== null
            ? $zone['free_from']
            : ($store['free_shipping_from'] ?? null);

        return [round($flat, 2), $free];
    }
}
