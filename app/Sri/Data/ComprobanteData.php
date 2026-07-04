<?php

namespace App\Sri\Data;

use App\Sri\Enums\TipoComprobante;
use Spatie\LaravelData\Data;

/**
 * Base de todos los comprobantes electrónicos. Cada subtipo declara su
 * TipoComprobante, del que se derivan codDoc, elemento raíz del XML y
 * versión del esquema (nunca se confía en el codDoc del payload).
 */
abstract class ComprobanteData extends Data
{
    abstract public static function tipo(): TipoComprobante;

    public InfoTributariaData $infoTributaria;
}
