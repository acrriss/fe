<?php

namespace App\Sri\Data;

use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Support\Payload;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Línea de detalle (<detalles><detalle>).
 *
 * La factura y la liquidación identifican el ítem con `codigoPrincipal` y
 * su segundo código es `codigoAuxiliar`; la nota de crédito usa
 * `codigoInterno` y `codigoAdicional`. Los cuatro son opcionales aquí y
 * cada tipo emite solo su par.
 *
 * El segundo código es donde el SRI exige los códigos de actividad
 * regulada: materiales de construcción (Anexo 23, Tabla 31) y transporte
 * comercial (Anexo 25, Tabla 32). El contenido no se valida: lo fija el
 * cliente por ítem y el SRI lo audita a posteriori.
 */
final class DetalleData extends Data
{
    use RechazaClavesDesconocidas;

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
        public ?string $codigoAuxiliar = null,
        public ?string $codigoAdicional = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['impuestos'] = Payload::lista(data_get($properties, 'impuestos.impuesto'));

        return self::soloClavesConocidas($properties);
    }

    /**
     * @return array<string, mixed>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'codigoPrincipal' => $this->codigoPrincipal,
            'codigoInterno' => $this->codigoInterno,
            'codigoAuxiliar' => $this->codigoAuxiliar,
            'codigoAdicional' => $this->codigoAdicional,
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
