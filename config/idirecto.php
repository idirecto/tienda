<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Puente con el mayorista (idirecto).
 *
 * Al enviar las lineas elegidas de un pedido se escriben filas reales en las
 * tablas `pedidos_addr`, `pedidos` y `pedidos_det` del mayorista, replicando su
 * propio flujo web (`App\Controllers\pedido_controller::guardaPedido()`):
 * `web = 1`, `estado = NULL` (activo), `referencia` con el pedido de la tienda
 * y `fecha = NOW()`.
 *
 * Cada tienda dice con que cuenta compra en `mt_stores.id_tienda_idirecto` y con
 * que tarifa en `mt_stores.id_margen` (ambos se editan en el panel > Ajustes).
 */
return [
    // Interruptor general. Con `false` el panel muestra el listado de pedidos
    // pero no ofrece enviar nada al mayorista.
    'enabled' => Env::bool('IDIRECTO_ENABLED', true),

    // Tarifa usada si la tienda no tiene `id_margen`. 12 es el margen por
    // defecto con el que trabaja idirecto para sus clientes web.
    'default_id_margen' => Env::int('IDIRECTO_DEFAULT_ID_MARGEN', 12),

    // Descuenta stock y suma reserva en los almacenes externos (tipo 0 y 4),
    // igual que hace idirecto al crear un pedido. Con `false` el pedido se crea
    // pero el almacen del mayorista no reserva nada.
    'reserve_stock' => Env::bool('IDIRECTO_RESERVE_STOCK', true),

    // Comercial y sucursal con los que se registra el pedido si la cuenta de la
    // tienda no trae los suyos propios (`tiendas.id_comercial`, `tiendas.sucursal_id`).
    'comercial_id' => Env::int('IDIRECTO_COMERCIAL_ID', 10),
    'sucursal_id'  => Env::int('IDIRECTO_SUCURSAL_ID', 1),

    // Pais de las direcciones que se crean en `pedidos_addr` (ISO-2).
    'country_code' => (string) Env::get('IDIRECTO_COUNTRY_CODE', 'ES'),
];
