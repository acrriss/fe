<?php

namespace App\Sri\Enums;

/**
 * Tipo de emisión según la ficha técnica del SRI (tabla 5).
 * El esquema actual del SRI solo contempla la emisión normal.
 */
enum TipoEmision: string
{
    case Normal = '1';
}
