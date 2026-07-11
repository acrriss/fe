<?php

namespace App\Sri\Data\Liquidacion;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\DetalleData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class LiquidacionCompraData extends ComprobanteData
{
    /**
     * @param  array<int, DetalleData>  $detalles
     */
    public function __construct(
        public InfoTributariaData $infoTributaria,
        public InfoLiquidacionCompraData $infoLiquidacionCompra,
        #[DataCollectionOf(DetalleData::class)]
        public array $detalles,
    ) {}

    public static function tipo(): TipoComprobante
    {
        return TipoComprobante::LiquidacionCompra;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['detalles'] = Payload::lista(data_get($properties, 'detalles.detalle'));

        return $properties;
    }

    public function fechaEmision(): CarbonImmutable
    {
        return $this->infoLiquidacionCompra->fechaEmision;
    }

    public function importeTotal(): string
    {
        return $this->infoLiquidacionCompra->importeTotal;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return [
            'infoTributaria' => $this->infoTributaria->xmlArray(self::tipo()),
            'infoLiquidacionCompra' => $this->infoLiquidacionCompra->xmlArray(),
            'detalles' => [
                'detalle' => array_map(fn (DetalleData $d): array => $d->xmlArray(), $this->detalles),
            ],
        ];
    }
}
