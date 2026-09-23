<?php

namespace App\Sri\Data;

use App\Sri\Catalogos\FormasPago;
use App\Sri\Data\Concerns\RechazaClavesDesconocidas;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Support\Payload;
use Spatie\LaravelData\Data;

/**
 * Forma de pago (<pagos><pago>), común a nota de débito y liquidación de
 * compra. `formaPago` sigue la tabla 24 de la ficha del SRI.
 */
final class PagoData extends Data
{
    use RechazaClavesDesconocidas;

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        /*
         * La Tabla 24 es una lista cerrada, así que el servicio la valida:
         * un código retirado (02-14) o inventado lo rechaza el SRI al
         * autorizar, y ahí el error llega tarde y sin explicación. Aquí es
         * un 422 que nombra el campo y el valor.
         */
        $formaPago = data_get($properties, 'formaPago');

        if (is_scalar($formaPago) && ! FormasPago::existe((string) $formaPago)) {
            throw DatoInvalido::porFormato(
                'formaPago',
                'un código de la Tabla 24 de la ficha ('.implode(', ', FormasPago::todos()).')',
                (string) $formaPago,
            );
        }

        return self::soloClavesConocidas($properties);
    }

    public function __construct(
        public string $formaPago,
        public string $total,
        public ?string $plazo = null,
        public ?string $unidadTiempo = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function xmlArray(): array
    {
        return Payload::sinNulos([
            'formaPago' => $this->formaPago,
            'total' => $this->total,
            'plazo' => $this->plazo,
            'unidadTiempo' => $this->unidadTiempo,
        ]);
    }
}
