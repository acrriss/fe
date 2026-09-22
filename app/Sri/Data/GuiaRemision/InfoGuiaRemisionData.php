<?php

namespace App\Sri\Data\GuiaRemision;

use App\Sri\Data\BloqueInfoData;
use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\Payload;
use App\Sri\Support\ValidadorIdentificacion;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;

/**
 * Bloque <infoGuiaRemision>: datos del transporte de la mercadería.
 */
final class InfoGuiaRemisionData extends BloqueInfoData
{
    use RechazaClavesDesconocidas;

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
     * Pese al nombre del campo, `rucTransportista` lleva la identificación
     * que declare `tipoIdentificacionTransportista`: puede ser una cédula o
     * un pasaporte, no solo un RUC.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        ValidadorIdentificacion::validarEnPayload(
            $properties,
            'tipoIdentificacionTransportista',
            'rucTransportista',
        );

        return self::soloClavesConocidas($properties);
    }

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
            'contribuyenteEspecial' => $this->contribuyenteEspecial,
            'fechaIniTransporte' => $this->fechaIniTransporte->format('d/m/Y'),
            'fechaFinTransporte' => $this->fechaFinTransporte->format('d/m/Y'),
            'placa' => $this->placa,
        ]);
    }
}
