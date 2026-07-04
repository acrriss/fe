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
}
