<?php

declare(strict_types=1);

namespace Tienda\Core\Contract;

/**
 * Falta configuracion obligatoria del contrato.
 *
 * El mensaje puede mostrarse al operador, pero nunca incluye datos del cliente.
 */
final class ContractConfigurationException extends \RuntimeException
{
    /** @var list<string> */
    private array $problems;

    /** @param list<string> $problems Nombres de los ajustes que faltan o no son validos. */
    public function __construct(array $problems)
    {
        $this->problems = array_values($problems);
        parent::__construct(
            'No se puede generar el contrato: falta configuracion obligatoria ('
            . implode(', ', $this->problems) . ').'
        );
    }

    /** @return list<string> */
    public function problems(): array
    {
        return $this->problems;
    }
}
