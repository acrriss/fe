<?php

namespace App\Sri\Data\NotaDebito;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\Payload;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;

final class NotaDebitoData extends ComprobanteData
{
    /**
     * @param  array<int, MotivoData>  $motivos
     */
    public function __construct(
        public InfoTributariaData $infoTributaria,
        public InfoNotaDebitoData $infoNotaDebito,
        #[DataCollectionOf(MotivoData::class)]
        public array $motivos,
    ) {}

    public static function tipo(): TipoComprobante
    {
        return TipoComprobante::NotaDebito;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['motivos'] = Payload::lista(data_get($properties, 'motivos.motivo'));

        return $properties;
    }

    public function fechaEmision(): CarbonImmutable
    {
        return $this->infoNotaDebito->fechaEmision;
    }

    public function importeTotal(): string
    {
        return $this->infoNotaDebito->valorTotal;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return [
            'infoTributaria' => $this->infoTributaria->xmlArray(self::tipo()),
            'infoNotaDebito' => $this->infoNotaDebito->xmlArray(),
            'motivos' => [
                'motivo' => array_map(fn (MotivoData $m): array => $m->xmlArray(), $this->motivos),
            ],
        ];
    }
}
