<?php

declare(strict_types=1);

use Tienda\Core\Env;

return [
    /*
     * Modo de pruebas comercial.
     *
     * Se activa por defecto para que una instalacion sin configurar no pueda
     * vender por error. Con esto encendido, el carrito y el checkout rechazan
     * cualquier compra y muestran el aviso de `Core\ShopMode`. Para abrir la
     * venta real hay que poner SHOP_TEST_MODE=false en el .env.
     */
    'test_mode' => Env::bool('SHOP_TEST_MODE', true),
];
