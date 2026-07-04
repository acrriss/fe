<?php

namespace App\Sri\Enums;

/**
 * Ambiente de emisión según la ficha técnica del SRI (tabla 4).
 */
enum Ambiente: string
{
    case Pruebas = '1';
    case Produccion = '2';

    public function esProduccion(): bool
    {
        return $this === self::Produccion;
    }
}
