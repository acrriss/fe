<?php

namespace App\Sri\Data;

use App\Sri\Enums\TipoComprobante;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Base de todos los comprobantes electrónicos. Cada subtipo declara su
 * TipoComprobante, del que se derivan codDoc, elemento raíz del XML y
 * versión del esquema (nunca se confía en el codDoc del payload).
 */
abstract class ComprobanteData extends Data
{
    abstract public static function tipo(): TipoComprobante;

    /**
     * Fecha de emisión del documento (vive en el bloque info* de cada tipo).
     */
    abstract public function fechaEmision(): CarbonImmutable;

    /**
     * Importe del documento, si el tipo lo declara (la retención no tiene).
     */
    public function importeTotal(): ?string
    {
        return null;
    }

    /**
     * Representación como array listo para ArrayToXml, en el orden que exige
     * la ficha técnica del SRI. Requiere que la claveAcceso ya esté asignada.
     *
     * @return array<string, mixed>
     */
    abstract public function xmlArray(): array;

    public InfoTributariaData $infoTributaria;
}
