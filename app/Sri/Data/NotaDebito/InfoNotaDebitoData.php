<?php

namespace App\Sri\Data\NotaDebito;

use App\Sri\Data\ImpuestoData;
use App\Sri\Data\PagoData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Bloque <infoNotaDebito>. Referencia al documento que modifica y detalla
 * los impuestos y las formas de pago del débito.
 */
final class InfoNotaDebitoData extends Data
{
    /**
     * @param  array<int, ImpuestoData>  $impuestos
     * @param  array<int, PagoData>  $pagos
     */
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaEmision,
        public TipoIdentificacion $tipoIdentificacionComprador,
        public string $razonSocialComprador,
        public string $identificacionComprador,
        public TipoComprobante $codDocModificado,
        public string $numDocModificado,
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaEmisionDocSustento,
        public string $totalSinImpuestos,
        #[DataCollectionOf(ImpuestoData::class)]
        public array $impuestos,
        public string $valorTotal,
        #[DataCollectionOf(PagoData::class)]
        public array $pagos,
        public ?string $dirEstablecimiento = null,
        public ?string $obligadoContabilidad = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['impuestos'] = Payload::lista(data_get($properties, 'impuestos.impuesto'));
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
            'tipoIdentificacionComprador' => $this->tipoIdentificacionComprador->value,
            'razonSocialComprador' => $this->razonSocialComprador,
            'identificacionComprador' => $this->identificacionComprador,
            'obligadoContabilidad' => $this->obligadoContabilidad,
            'codDocModificado' => $this->codDocModificado->value,
            'numDocModificado' => $this->numDocModificado,
            'fechaEmisionDocSustento' => $this->fechaEmisionDocSustento->format('d/m/Y'),
            'totalSinImpuestos' => $this->totalSinImpuestos,
            'impuestos' => [
                'impuesto' => array_map(fn (ImpuestoData $i): array => $i->xmlArray(), $this->impuestos),
            ],
            'valorTotal' => $this->valorTotal,
            'pagos' => [
                'pago' => array_map(fn (PagoData $p): array => $p->xmlArray(), $this->pagos),
            ],
        ]);
    }
}
