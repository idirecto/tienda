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

    public static function isTestMode(): bool
    {
        return (bool) Config::get('shop.test_mode', true);
    }

    /** Aviso unico para todas las acciones bloqueadas. */
    public static function message(): string
    {
        return self::TEST_MESSAGE;
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
