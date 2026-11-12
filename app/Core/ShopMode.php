<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Estado comercial global de la tienda.
 *
 * En modo de pruebas no se puede anadir al carrito ni registrar un pedido. La
 * comprobacion vive en el servidor para que no dependa de JavaScript ni de que
 * el navegador pinte o desactive un boton.
 */
final class ShopMode
{
    public const TEST_MESSAGE = 'Esta pagina web se encuentra en pruebas y ninguno de los articulos puede ser comprado, los precios y cantidades mostradas son ficticias';

    /**
     * Aviso permanente de fase de prueba que se pinta en el storefront.
     *
     * La fecha es informativa y se escribe de forma fija: no se calcula con el
     * reloj del servidor ni cambia el estado de la tienda. Tampoco depende de
     * `SHOP_TEST_MODE`: se mantiene hasta retirarlo manualmente, tal como pidio
     * el propietario.
     */
    public const NOTICE_MESSAGE = 'Web de prueba. Los productos y precios mostrados son de prueba. Fecha prevista de habilitación: 1 de diciembre de 2026.';

    public static function isTestMode(): bool
    {
        return (bool) Config::get('shop.test_mode', true);
    }

    /** Aviso unico para todas las acciones bloqueadas. */
    public static function message(): string
    {
        return self::TEST_MESSAGE;
    }

    /** Texto fijo del aviso permanente del storefront. */
    public static function notice(): string
    {
        return self::NOTICE_MESSAGE;
    }

    /**
     * Impide registrar un pedido si la tienda sigue en pruebas.
     *
     * @throws ValidationException
     */
    public static function assertCanSell(): void
    {
        if (self::isTestMode()) {
            throw new ValidationException(self::message());
        }
    }
}
