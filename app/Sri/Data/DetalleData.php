<?php

namespace App\Sri\Data;

use App\Sri\Support\Payload;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Línea de detalle (<detalles><detalle>).
 *
 * La factura identifica el ítem con `codigoPrincipal` y la nota de crédito
 * con `codigoInterno`; ambos son opcionales aquí.
 */
final class DetalleData extends Data
{
    /**
     * @param  array<int, ImpuestoData>  $impuestos
     */
    public function __construct(
        public string $descripcion,
        public string $cantidad,
        public string $precioUnitario,
        public string $descuento,
        public string $precioTotalSinImpuesto,
        #[DataCollectionOf(ImpuestoData::class)]
        public array $impuestos,
        public ?string $codigoPrincipal = null,
        public ?string $codigoInterno = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['impuestos'] = Payload::lista(data_get($properties, 'impuestos.impuesto'));

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'codigoPrincipal' => $this->codigoPrincipal,
            'codigoInterno' => $this->codigoInterno,
            'descripcion' => $this->descripcion,
            'cantidad' => $this->cantidad,
            'precioUnitario' => $this->precioUnitario,
            'descuento' => $this->descuento,
            'precioTotalSinImpuesto' => $this->precioTotalSinImpuesto,
            'impuestos' => [
                'impuesto' => array_map(
                    fn (ImpuestoData $impuesto): array => $impuesto->xmlArray(),
                    $this->impuestos,
                ),
            ],
        ]);
    }
}
