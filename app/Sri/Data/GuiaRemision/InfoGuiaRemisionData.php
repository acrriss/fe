<?php

namespace App\Sri\Data\GuiaRemision;

use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Bloque <infoGuiaRemision>: datos del transporte de la mercadería.
 */
final class InfoGuiaRemisionData extends Data
{
    public function __construct(
        public string $dirPartida,
        public string $razonSocialTransportista,
        public TipoIdentificacion $tipoIdentificacionTransportista,
        public string $rucTransportista,
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaIniTransporte,
        #[WithCast(DateTimeInterfaceCast::class, format: 'd/m/Y')]
        public CarbonImmutable $fechaFinTransporte,
        public string $placa,
        public ?string $dirEstablecimiento = null,
        public ?string $obligadoContabilidad = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'dirEstablecimiento' => $this->dirEstablecimiento,
            'dirPartida' => $this->dirPartida,
            'razonSocialTransportista' => $this->razonSocialTransportista,
            'tipoIdentificacionTransportista' => $this->tipoIdentificacionTransportista->value,
            'rucTransportista' => $this->rucTransportista,
            'obligadoContabilidad' => $this->obligadoContabilidad,
            'fechaIniTransporte' => $this->fechaIniTransporte->format('d/m/Y'),
            'fechaFinTransporte' => $this->fechaFinTransporte->format('d/m/Y'),
            'placa' => $this->placa,
        ]);
    }
}
