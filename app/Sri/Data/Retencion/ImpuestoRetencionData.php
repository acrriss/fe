<?php

namespace App\Sri\Data\Retencion;

use App\Sri\Enums\TipoComprobante;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Impuesto retenido (<impuestos><impuesto> del comprobante de retención),
 * con la referencia al documento de sustento.
 */
final class ImpuestoRetencionData extends Data
{
    public function __construct(
        public string $codigo,
        public string $codigoRetencion,
        public string $baseImponible,
        public string $porcentajeRetener,
        public string $valorRetenido,
        public TipoComprobante $codDocSustento,
        public string $numDocSustento,
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaEmisionDocSustento,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'codigoRetencion' => $this->codigoRetencion,
            'baseImponible' => $this->baseImponible,
            'porcentajeRetener' => $this->porcentajeRetener,
            'valorRetenido' => $this->valorRetenido,
            'codDocSustento' => $this->codDocSustento->value,
            'numDocSustento' => $this->numDocSustento,
            'fechaEmisionDocSustento' => $this->fechaEmisionDocSustento->format('d/m/Y'),
        ];
    }
}
