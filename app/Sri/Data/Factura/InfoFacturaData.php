<?php

namespace App\Sri\Data\Factura;

use App\Sri\Data\BloqueInfoData;
use App\Sri\Data\Casts\ValueObjectCast;
use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Data\PagoData;
use App\Sri\Data\TotalImpuestoData;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Exceptions\DatoInvalido;
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
    use RechazaClavesDesconocidas;

    /**
     * @param  array<int, TotalImpuestoData>  $totalConImpuestos
     * @param  array<int, PagoData>  $pagos
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
        /**
         * Formas de pago (Tabla 24). La ficha las marca *Obligatorio* en
         * factura y el servicio las exige: sin ellas el comprobante que se
         * emitiría no cumpliría el formato.
         */
        #[DataCollectionOf(PagoData::class)]
        public array $pagos,
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

        $properties['pagos'] = Payload::lista(data_get($properties, 'pagos.pago'));

        if ($properties['pagos'] === []) {
            throw DatoInvalido::porFormato(
                'pagos',
                'al menos una forma de pago (<pagos><pago>), que la ficha exige en toda factura',
                'vacío',
            );
        }

        return self::soloClavesConocidas($properties);
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
            'pagos' => [
                'pago' => array_map(fn (PagoData $p): array => $p->xmlArray(), $this->pagos),
            ],
        ]);
    }
}
