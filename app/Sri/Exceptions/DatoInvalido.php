<?php

namespace App\Sri\Exceptions;

use InvalidArgumentException;

/**
 * Un valor no cumple el formato exigido por la ficha técnica del SRI.
 */
final class DatoInvalido extends InvalidArgumentException
{
    public static function porFormato(string $campo, string $esperado, string $valor): self
    {
        return new self("El campo {$campo} debe ser {$esperado}; se recibió «{$valor}».");
    }
}
