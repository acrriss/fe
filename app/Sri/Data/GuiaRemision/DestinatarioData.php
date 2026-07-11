<?php

namespace App\Sri\Data\GuiaRemision;

use App\Sri\Support\Payload;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Destinatario de la mercadería (<destinatarios><destinatario>), con los
 * ítems trasladados y, opcionalmente, el documento que sustenta el traslado.
 */
final class DestinatarioData extends Data
{
    /**
     * @param  array<int, DetalleGuiaData>  $detalles
     */
    public function __construct(
        public string $identificacionDestinatario,
        public string $razonSocialDestinatario,
        public string $dirDestinatario,
        public string $motivoTraslado,
        #[DataCollectionOf(DetalleGuiaData::class)]
        public array $detalles,
        public ?string $docAduaneroUnico = null,
        public ?string $codEstabDestino = null,
        public ?string $ruta = null,
        public ?string $codDocSustento = null,
        public ?string $numDocSustento = null,
        public ?string $numAutDocSustento = null,
        public ?string $fechaEmisionDocSustento = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['detalles'] = Payload::lista(data_get($properties, 'detalles.detalle'));

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'identificacionDestinatario' => $this->identificacionDestinatario,
            'razonSocialDestinatario' => $this->razonSocialDestinatario,
            'dirDestinatario' => $this->dirDestinatario,
            'motivoTraslado' => $this->motivoTraslado,
            'docAduaneroUnico' => $this->docAduaneroUnico,
            'codEstabDestino' => $this->codEstabDestino,
            'ruta' => $this->ruta,
            'codDocSustento' => $this->codDocSustento,
            'numDocSustento' => $this->numDocSustento,
            'numAutDocSustento' => $this->numAutDocSustento,
            'fechaEmisionDocSustento' => $this->fechaEmisionDocSustento,
            'detalles' => [
                'detalle' => array_map(fn (DetalleGuiaData $d): array => $d->xmlArray(), $this->detalles),
            ],
        ]);
    }
}
