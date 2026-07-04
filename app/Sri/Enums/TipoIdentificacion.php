<?php

namespace App\Sri\Enums;

/**
 * Tipo de identificación del comprador / sujeto retenido
 * según la ficha técnica del SRI (tabla 6).
 */
enum TipoIdentificacion: string
{
    case Ruc = '04';
    case Cedula = '05';
    case Pasaporte = '06';
    case ConsumidorFinal = '07';
    case IdentificacionExterior = '08';
}
