<?php

namespace App\Sri\Data\Factura;

use App\Sri\Data\BloqueInfoData;
use App\Sri\Data\Casts\ValueObjectCast;
use App\Sri\Data\TotalImpuestoData;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use App\Sri\Support\ValidadorIdentificacion;
use App\Sri\ValueObjects\Placa;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

/**
 * Bloque <infoFactura>.
 */
final class InfoFacturaData extends BloqueInfoData
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
        /**
         * Placa del vehículo, obligatoria en las facturas de las operadoras
         * de transporte comercial excepto taxis (Anexo 25 §2). Dato de la
         * transacción: lo manda el cliente, a diferencia de las leyendas
         * del emisor.
         */
        #[WithCast(ValueObjectCast::class, Placa::class)]
        public ?Placa $placa = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        ValidadorIdentificacion::validarEnPayload(
            $properties,
            'tipoIdentificacionComprador',
            'identificacionComprador',
        );

        $properties['totalConImpuestos'] = Payload::lista(
            data_get($properties, 'totalConImpuestos.totalImpuesto'),
        );

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'fechaEmision' => $this->fechaEmision->format('d/m/Y'),
            'dirEstablecimiento' => $this->dirEstablecimiento,
            'contribuyenteEspecial' => $this->contribuyenteEspecial,
            'obligadoContabilidad' => $this->obligadoContabilidad,
            'tipoIdentificacionComprador' => $this->tipoIdentificacionComprador->value,
            'razonSocialComprador' => $this->razonSocialComprador,
            'identificacionComprador' => $this->identificacionComprador,
            'totalSinImpuestos' => $this->totalSinImpuestos,
            'totalDescuento' => $this->totalDescuento,
            'totalConImpuestos' => [
                'totalImpuesto' => array_map(
                    fn (TotalImpuestoData $impuesto): array => $impuesto->xmlArray(),
                    $this->totalConImpuestos,
                ),
            ],
            'propina' => $this->propina,
            'importeTotal' => $this->importeTotal,
            'moneda' => $this->moneda,
            // la ficha la ubica entre <moneda> y <pagos> (Anexo 25 §2)
            'placa' => $this->placa?->value,
        ]);
    }
}
