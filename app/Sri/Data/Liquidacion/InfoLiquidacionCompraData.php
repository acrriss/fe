<?php

namespace App\Sri\Data\Liquidacion;

use App\Sri\Data\PagoData;
use App\Sri\Data\TotalImpuestoData;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use App\Sri\Support\ValidadorIdentificacion;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Bloque <infoLiquidacionCompra>. El "comprador" del comprobante es en
 * realidad el proveedor de bienes/servicios que se liquida.
 */
final class InfoLiquidacionCompraData extends Data
{
    /**
     * @param  array<int, TotalImpuestoData>  $totalConImpuestos
     * @param  array<int, PagoData>  $pagos
     */
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaEmision,
        public TipoIdentificacion $tipoIdentificacionProveedor,
        public string $razonSocialProveedor,
        public string $identificacionProveedor,
        public string $totalSinImpuestos,
        public string $totalDescuento,
        #[DataCollectionOf(TotalImpuestoData::class)]
        public array $totalConImpuestos,
        public string $importeTotal,
        public string $moneda,
        #[DataCollectionOf(PagoData::class)]
        public array $pagos,
        public ?string $dirEstablecimiento = null,
        public ?string $obligadoContabilidad = null,
        public ?string $direccionProveedor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        ValidadorIdentificacion::validarEnPayload(
            $properties,
            'tipoIdentificacionProveedor',
            'identificacionProveedor',
        );

        $properties['totalConImpuestos'] = Payload::lista(data_get($properties, 'totalConImpuestos.totalImpuesto'));
        $properties['pagos'] = Payload::lista(data_get($properties, 'pagos.pago'));

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
            'obligadoContabilidad' => $this->obligadoContabilidad,
            'tipoIdentificacionProveedor' => $this->tipoIdentificacionProveedor->value,
            'razonSocialProveedor' => $this->razonSocialProveedor,
            'identificacionProveedor' => $this->identificacionProveedor,
            'direccionProveedor' => $this->direccionProveedor,
            'totalSinImpuestos' => $this->totalSinImpuestos,
            'totalDescuento' => $this->totalDescuento,
            'totalConImpuestos' => [
                'totalImpuesto' => array_map(fn (TotalImpuestoData $i): array => $i->xmlArray(), $this->totalConImpuestos),
            ],
            'importeTotal' => $this->importeTotal,
            'moneda' => $this->moneda,
            'pagos' => [
                'pago' => array_map(fn (PagoData $p): array => $p->xmlArray(), $this->pagos),
            ],
        ]);
    }
}
