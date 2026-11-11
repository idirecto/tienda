<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * La peticion corresponde a la portada estatica de la plataforma.
 *
 * Se usa para no resolver ni consultar ninguna tienda cuando se entra por el
 * dominio principal sin una tienda concreta.
 */
final class PlatformLandingException extends \RuntimeException
{
}
