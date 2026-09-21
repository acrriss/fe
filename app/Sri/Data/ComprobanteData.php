<?php

namespace App\Sri\Data;

use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
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
     * El bloque info* del tipo (infoFactura, infoNotaCredito…), para lo que
     * el esquema repite en todos ellos, como la leyenda de contribuyente
     * especial.
     */
    abstract public function bloqueInfo(): BloqueInfoData;

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

    /**
     * Bloque `infoAdicional`, opcional y SIEMPRE el último elemento del
     * comprobante según la ficha del SRI.
     *
     * @var array<int, CampoAdicionalData>
     */
    public array $infoAdicional = [];

    /** Tope de campos que admite el esquema del SRI. */
    public const int MAXIMO_CAMPOS_ADICIONALES = 15;

    /**
     * Normaliza el wrapper `{infoAdicional: {campoAdicional: X}}` a lista.
     * Cada subtipo llama a este padre antes de lo suyo.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['infoAdicional'] = Payload::lista(
            data_get($properties, 'infoAdicional.campoAdicional'),
        );

        return $properties;
    }

    /**
     * El bloque listo para ArrayToXml, o vacío si no hay campos: así un
     * comprobante sin información adicional produce el mismo XML que antes
     * de que existiera este soporte.
     *
     * @return array<string, mixed>
     */
    protected function infoAdicionalXml(): array
    {
        if ($this->infoAdicional === []) {
            return [];
        }

        return [
            'infoAdicional' => [
                'campoAdicional' => array_map(
                    fn (CampoAdicionalData $campo): array => $campo->xmlArray(),
                    $this->infoAdicional,
                ),
            ],
        ];
    }
}
