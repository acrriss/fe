<?php

namespace App\Sri\Catalogos;

/**
 * Tabla 24 de la ficha: formas de pago del bloque <pagos><pago>.
 *
 * Lista cerrada, a diferencia de {@see CodigosAuxiliares}: el campo
 * `formaPago` solo admite estos códigos, así que aquí el catálogo SÍ es la
 * fuente de la validación además de la del selector del integrador.
 *
 * Los códigos 02 a 14 existieron y fueron retirados; la ficha 2.34 ya no
 * los lista, así que enviarlos es un error.
 *
 * Fuente única también de la tabla publicada en docs/openapi.yaml.
 */
final class FormasPago
{
    /** Versión de la ficha técnica de la que se transcribió la tabla. */
    public const string FICHA = '2.34';

    /** Cambia cuando cambia el contenido del catálogo (no la ficha entera). */
    public const string VERSION = '2026-09-23';

    /**
     * Tabla 24, con la fecha desde la que el SRI admite cada código.
     *
     * Ojo con las claves: PHP convierte a entero toda clave numérica, así
     * que '15'…'21' son ints aquí dentro ('01' sobrevive por el cero). Los
     * accesores las devuelven siempre como cadena, que es como viajan en el
     * XML y en el JSON del catálogo.
     *
     * @var array<array-key, array{nombre: string, desde: string}>
     */
    private const array TABLA = [
        '01' => ['nombre' => 'Sin utilización del sistema financiero', 'desde' => '2013-01-01'],
        '15' => ['nombre' => 'Compensación de deudas', 'desde' => '2013-01-01'],
        '16' => ['nombre' => 'Tarjeta de débito', 'desde' => '2016-06-01'],
        '17' => ['nombre' => 'Dinero electrónico', 'desde' => '2016-06-01'],
        '18' => ['nombre' => 'Tarjeta prepago', 'desde' => '2016-06-01'],
        '19' => ['nombre' => 'Tarjeta de crédito', 'desde' => '2016-06-01'],
        '20' => ['nombre' => 'Otros con utilización del sistema financiero', 'desde' => '2016-06-01'],
        '21' => ['nombre' => 'Endoso de títulos', 'desde' => '2016-06-01'],
    ];

    /**
     * @return list<array{codigo: string, nombre: string, desde: string}>
     */
    public static function codigos(): array
    {
        $codigos = [];

        foreach (self::TABLA as $codigo => $fila) {
            $codigos[] = [
                'codigo' => (string) $codigo,
                'nombre' => $fila['nombre'],
                'desde' => $fila['desde'],
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
