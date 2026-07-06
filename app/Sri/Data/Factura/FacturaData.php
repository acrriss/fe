<?php

namespace App\Sri\Data\Factura;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\DetalleData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class FacturaData extends ComprobanteData
{
    /**
     * @param  array<int, DetalleData>  $detalles
     */
    public function __construct(
        public InfoTributariaData $infoTributaria,
        public InfoFacturaData $infoFactura,
        #[DataCollectionOf(DetalleData::class)]
        public array $detalles,
    ) {}

    public static function tipo(): TipoComprobante
    {
        return TipoComprobante::Factura;
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
        return $this->infoFactura->fechaEmision;
    }

    public function importeTotal(): string
    {
        return $this->infoFactura->importeTotal;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return [
            'infoTributaria' => $this->infoTributaria->xmlArray(self::tipo()),
            'infoFactura' => $this->infoFactura->xmlArray(),
            'detalles' => [
                'detalle' => array_map(
                    fn (DetalleData $detalle): array => $detalle->xmlArray(),
                    $this->detalles,
                ),
            ],
        ];
    }
}
