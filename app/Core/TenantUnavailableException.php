<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * La tienda existe, pero su estado no permite atender peticiones publicas.
 *
 * Se usa para responder 404 sin revelar si la tienda esta pendiente,
 * suspendida o cerrada. No incluye datos de la tienda en el mensaje.
 */
final class TenantUnavailableException extends \RuntimeException
{
}
