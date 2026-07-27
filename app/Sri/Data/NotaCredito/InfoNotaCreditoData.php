<?php

namespace App\Sri\Data\NotaCredito;

use App\Sri\Data\TotalImpuestoData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use App\Sri\Support\ValidadorIdentificacion;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Bloque <infoNotaCredito>. Referencia al documento que modifica
 * (codDocModificado + numDocModificado + fechaEmisionDocSustento).
 */
final class InfoNotaCreditoData extends Data
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
        public TipoComprobante $codDocModificado,
        public string $numDocModificado,
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaEmisionDocSustento,
        public string $totalSinImpuestos,
        public string $valorModificacion,
        public string $moneda,
        #[DataCollectionOf(TotalImpuestoData::class)]
        public array $totalConImpuestos,
        public string $motivo,
        public ?string $dirEstablecimiento = null,
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
            'tipoIdentificacionComprador' => $this->tipoIdentificacionComprador->value,
            'razonSocialComprador' => $this->razonSocialComprador,
            'identificacionComprador' => $this->identificacionComprador,
            'obligadoContabilidad' => $this->obligadoContabilidad,
            'codDocModificado' => $this->codDocModificado->value,
            'numDocModificado' => $this->numDocModificado,
            'fechaEmisionDocSustento' => $this->fechaEmisionDocSustento->format('d/m/Y'),
            'totalSinImpuestos' => $this->totalSinImpuestos,
            'valorModificacion' => $this->valorModificacion,
            'moneda' => $this->moneda,
            'totalConImpuestos' => [
                'totalImpuesto' => array_map(
                    fn (TotalImpuestoData $impuesto): array => $impuesto->xmlArray(),
                    $this->totalConImpuestos,
                ),
            ],
            'motivo' => $this->motivo,
        ]);
    }
}
