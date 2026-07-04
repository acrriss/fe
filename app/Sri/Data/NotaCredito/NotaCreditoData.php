<?php

namespace App\Sri\Data\NotaCredito;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\DetalleData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class NotaCreditoData extends ComprobanteData
{
    /**
     * @param  array<int, DetalleData>  $detalles
     */
    public function __construct(
        public InfoTributariaData $infoTributaria,
        public InfoNotaCreditoData $infoNotaCredito,
        #[DataCollectionOf(DetalleData::class)]
        public array $detalles,
    ) {}

    public static function tipo(): TipoComprobante
    {
        return TipoComprobante::NotaCredito;
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
}
