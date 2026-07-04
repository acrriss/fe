<?php

namespace App\Sri\Data\Retencion;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class ComprobanteRetencionData extends ComprobanteData
{
    /**
     * @param  array<int, ImpuestoRetencionData>  $impuestos
     */
    public function __construct(
        public InfoTributariaData $infoTributaria,
        public InfoCompRetencionData $infoCompRetencion,
        #[DataCollectionOf(ImpuestoRetencionData::class)]
        public array $impuestos,
    ) {}

    public static function tipo(): TipoComprobante
    {
        return TipoComprobante::ComprobanteRetencion;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['impuestos'] = Payload::lista(data_get($properties, 'impuestos.impuesto'));

        return $properties;
    }

    public function fechaEmision(): CarbonImmutable
    {
        return $this->infoCompRetencion->fechaEmision;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return [
            'infoTributaria' => $this->infoTributaria->xmlArray(self::tipo()),
            'infoCompRetencion' => $this->infoCompRetencion->xmlArray(),
            'impuestos' => [
                'impuesto' => array_map(
                    fn (ImpuestoRetencionData $impuesto): array => $impuesto->xmlArray(),
                    $this->impuestos,
                ),
            ],
        ];
    }
}
