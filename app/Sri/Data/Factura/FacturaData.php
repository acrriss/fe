<?php

namespace App\Sri\Data\Factura;

use App\Sri\Data\BloqueInfoData;
use App\Sri\Data\CampoAdicionalData;
use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Data\DetalleData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class FacturaData extends ComprobanteData
{
    use RechazaClavesDesconocidas;

    /**
     * @param  array<int, DetalleData>  $detalles
     */
    public function __construct(
        public InfoTributariaData $infoTributaria,
        public InfoFacturaData $infoFactura,
        #[DataCollectionOf(DetalleData::class)]
        public array $detalles,
        /** @var array<int, CampoAdicionalData> */
        #[DataCollectionOf(CampoAdicionalData::class)]
        public array $infoAdicional = [],
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
        $properties = parent::prepareForPipeline($properties);

        $properties['detalles'] = Payload::lista(data_get($properties, 'detalles.detalle'));

        return self::soloClavesConocidas($properties);
    }

    public function fechaEmision(): CarbonImmutable
    {
        return $this->infoFactura->fechaEmision;
    }

    public function bloqueInfo(): BloqueInfoData
    {
        return $this->infoFactura;
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
        return array_merge([
            'infoTributaria' => $this->infoTributaria->xmlArray(self::tipo()),
            'infoFactura' => $this->infoFactura->xmlArray(),
            'detalles' => [
                'detalle' => array_map(
                    fn (DetalleData $detalle): array => $detalle->xmlArray(),
                    $this->detalles,
                ),
            ],
        ], $this->infoAdicionalXml());
    }
}
