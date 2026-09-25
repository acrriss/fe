<?php

namespace App\Sri\Catalogos;

/**
 * Tabla 17 de la ficha: los `codigoPorcentaje` del IVA (impuesto con
 * `codigo` 2 en la Tabla 16).
 *
 * Lista cerrada, como {@see FormasPago}, y por el mismo motivo es a la vez
 * la fuente del selector del integrador y la de la validación.
 *
 * **Tres códigos valen cero y NO son lo mismo**: 0 (tarifa 0%), 6 (no
 * objeto de impuesto) y 7 (exento de IVA). El importe del impuesto es 0.00
 * en los tres, así que el SRI autoriza el comprobante aunque se equivoque
 * el código; el error solo aparece en una auditoría o en el anexo
 * transaccional. De ahí que el porcentaje no baste para elegirlo: hay que
 * decidirlo por producto.
 *
 * Fuente única también de la etiqueta del RIDE y de la tabla publicada en
 * docs/openapi.yaml.
 */
final class TarifasIva
{
    /** Código del IVA en la Tabla 16; las demás tablas son otras tarifas. */
    public const string CODIGO_IVA = '2';

    /** Versión de la ficha técnica de la que se transcribió la tabla. */
    public const string FICHA = '2.34';

    /** Cambia cuando cambia el contenido del catálogo (no la ficha entera). */
    public const string VERSION = '2026-09-24';

    /**
     * Tabla 17. `porcentaje` es la tarifa a aplicar, como cadena igual que
     * viaja `<tarifa>` en el XML —JSON publicaría 15.0 como 15 y dejaría el
     * tipo ambiguo—; null en las categorías que no son un porcentaje (no
     * objeto, exento, diferenciado).
     *
     * Ojo con las claves: PHP convierte a entero toda clave numérica, así
     * que aquí dentro son ints. Los accesores las devuelven como cadena,
     * que es como viajan en el XML y en el JSON del catálogo.
     *
     * @var array<array-key, array{nombre: string, porcentaje: string|null}>
     */
    private const array TABLA = [
        '0' => ['nombre' => 'IVA 0%', 'porcentaje' => '0.00'],
        '2' => ['nombre' => 'IVA 12%', 'porcentaje' => '12.00'],
        '3' => ['nombre' => 'IVA 14%', 'porcentaje' => '14.00'],
        '4' => ['nombre' => 'IVA 15%', 'porcentaje' => '15.00'],
        '5' => ['nombre' => 'IVA 5%', 'porcentaje' => '5.00'],
        '6' => ['nombre' => 'No objeto de impuesto', 'porcentaje' => null],
        '7' => ['nombre' => 'Exento de IVA', 'porcentaje' => null],
        '8' => ['nombre' => 'IVA diferenciado', 'porcentaje' => null],
        '10' => ['nombre' => 'IVA 13%', 'porcentaje' => '13.00'],
    ];

    /**
     * @return list<array{codigo: string, nombre: string, porcentaje: string|null}>
     */
    public static function codigos(): array
    {
        $codigos = [];

        foreach (self::TABLA as $codigo => $fila) {
            $codigos[] = [
                'codigo' => (string) $codigo,
                'nombre' => $fila['nombre'],
                'porcentaje' => $fila['porcentaje'],
            ];
        }

        return $codigos;
    }

    /**
     * @return list<string>
     */
    public static function todos(): array
    {
        return array_map(strval(...), array_keys(self::TABLA));
    }

    public static function existe(string $codigo): bool
    {
        return array_key_exists($codigo, self::TABLA);
    }

    public static function nombre(string $codigo): ?string
    {
        return self::TABLA[$codigo]['nombre'] ?? null;
    }
}
