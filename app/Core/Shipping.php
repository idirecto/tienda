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
 * El calculo por peso y provincia (como hace puntobyze) queda pendiente: necesita
 * pesos fiables por producto y una tabla de tarifas por zona.
 */
final class Shipping
{
    /** Coste del envio para ese importe de productos (ya con IVA). */
    public static function cost(array $store, float $subtotal): float
    {
        $flat = round((float) ($store['shipping_flat'] ?? 0), 2);
        $free = $store['free_shipping_from'] ?? null;

        if ($flat <= 0) {
            return 0.0;
        }

        if ($free !== null && (float) $free > 0 && $subtotal >= (float) $free) {
            return 0.0;
        }

        return $flat;
    }

    /** Cuanto falta para el envio gratis (0 si ya no aplica). */
    public static function missingForFree(array $store, float $subtotal): float
    {
        $free = $store['free_shipping_from'] ?? null;
        if ($free === null || (float) $free <= 0 || (float) ($store['shipping_flat'] ?? 0) <= 0) {
            return 0.0;
        }

        $falta = round((float) $free - $subtotal, 2);

        return $falta > 0 ? $falta : 0.0;
    }

    /** Hay envio gratis configurado (para poder anunciarlo en el carrito). */
    public static function hasFreeFrom(array $store): bool
    {
        return ($store['free_shipping_from'] ?? null) !== null
            && (float) $store['free_shipping_from'] > 0
            && (float) ($store['shipping_flat'] ?? 0) > 0;
    }
}
