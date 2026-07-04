<?php

namespace App\Sri\Data\Factura;

use App\Sri\Data\TotalImpuestoData;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Bloque <infoFactura>.
 */
final class InfoFacturaData extends Data
{
    /**
     * @param  array<int, TotalImpuestoData>  $totalConImpuestos
     */
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaEmision,
        public string $obligadoContabilidad,
        public TipoIdentificacion $tipoIdentificacionComprador,
        public string $razonSocialComprador,
        public string $identificacionComprador,
        public string $totalSinImpuestos,
        public string $totalDescuento,
        #[DataCollectionOf(TotalImpuestoData::class)]
        public array $totalConImpuestos,
        public string $importeTotal,
        public string $moneda,
        public ?string $dirEstablecimiento = null,
        public ?string $propina = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['totalConImpuestos'] = Payload::lista(
            data_get($properties, 'totalConImpuestos.totalImpuesto'),
        );

        return $properties;
    }
}
