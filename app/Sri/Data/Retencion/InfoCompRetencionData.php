<?php

namespace App\Sri\Data\Retencion;

use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Bloque <infoCompRetencion>.
 */
final class InfoCompRetencionData extends Data
{
    public function __construct(
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaEmision,
        public string $obligadoContabilidad,
        public TipoIdentificacion $tipoIdentificacionSujetoRetenido,
        public string $razonSocialSujetoRetenido,
        public string $identificacionSujetoRetenido,
        public string $periodoFiscal,
        public ?string $dirEstablecimiento = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'fechaEmision' => $this->fechaEmision->format('d/m/Y'),
            'dirEstablecimiento' => $this->dirEstablecimiento,
            'obligadoContabilidad' => $this->obligadoContabilidad,
            'tipoIdentificacionSujetoRetenido' => $this->tipoIdentificacionSujetoRetenido->value,
            'razonSocialSujetoRetenido' => $this->razonSocialSujetoRetenido,
            'identificacionSujetoRetenido' => $this->identificacionSujetoRetenido,
            'periodoFiscal' => $this->periodoFiscal,
        ]);
    }
}
