<?php

namespace App\Sri\Data\GuiaRemision;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class GuiaRemisionData extends ComprobanteData
{
    /**
     * @param  array<int, DestinatarioData>  $destinatarios
     */
    public function __construct(
        public InfoTributariaData $infoTributaria,
        public InfoGuiaRemisionData $infoGuiaRemision,
        #[DataCollectionOf(DestinatarioData::class)]
        public array $destinatarios,
    ) {}

    public static function tipo(): TipoComprobante
    {
        return TipoComprobante::GuiaRemision;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['destinatarios'] = Payload::lista(data_get($properties, 'destinatarios.destinatario'));

        return $properties;
    }

    /**
     * En la guía de remisión la fecha de la clave de acceso es la del
     * inicio del transporte (no hay fechaEmision propia).
     */
    public function fechaEmision(): CarbonImmutable
    {
        return $this->infoGuiaRemision->fechaIniTransporte;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return [
            'infoTributaria' => $this->infoTributaria->xmlArray(self::tipo()),
            'infoGuiaRemision' => $this->infoGuiaRemision->xmlArray(),
            'destinatarios' => [
                'destinatario' => array_map(fn (DestinatarioData $d): array => $d->xmlArray(), $this->destinatarios),
            ],
        ];
    }
}
