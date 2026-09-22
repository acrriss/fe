<?php

namespace App\Sri\Catalogos;

use App\Sri\Enums\TipoComprobante;

/**
 * Códigos que el SRI exige en el segundo código del ítem cuando la venta
 * pertenece a una actividad regulada: materiales de construcción (Anexo 23,
 * Tabla 31) y transporte comercial excepto taxis (Anexo 25 §1, Tabla 32).
 *
 * El campo del XML es de uso general (código auxiliar propio del emisor:
 * barras, SKU…), así que el servicio NO valida que un ítem traiga uno de
 * estos valores: la obligación es por ítem y solo el emisor sabe cuándo
 * aplica. Este catálogo existe para que el integrador construya su
 * selector desde una fuente única y no transcriba la tabla a mano.
 *
 * Fuente única también de la tabla publicada en docs/openapi.yaml.
 */
final class CodigosAuxiliares
{
    /** Versión de la ficha técnica de la que se transcribieron las tablas. */
    public const string FICHA = '2.34';

    /** Cambia cuando cambia el contenido del catálogo (no la ficha entera). */
    public const string VERSION = '2026-09-22';

    /**
     * Tabla 31 — Resolución NAC-DGERCGC24-00000013.
     *
     * Se corrigen dos erratas evidentes del PDF de la ficha: "PÉTROS" y
     * "MORTERS" (los códigos, que son lo normativo, van literales).
     *
     * @var array<string, string>
     */
    private const array MATERIALES_CONSTRUCCION = [
        'F010101' => 'Varilla laminada corrugada AS42 de 8mm, 10mm y 12mm de diámetro',
        'F010201' => 'Arcilla',
        'F010202' => 'Arena',
        'F010203' => 'Cal',
        'F010204' => 'Caliza',
        'F010205' => 'Pétreos',
        'F010301' => 'Hormigón premezclado',
        'F010401' => 'Cemento y sus derivados',
        'F010402' => 'Residuo cemento',
        'F010501' => 'Chatarra ferrosa',
        'F010601' => 'Morteros',
        'F010701' => 'Clinker',
        'F010702' => 'Puzolana',
        'F010703' => 'Yeso',
        'F010801' => 'Adoquín',
        'F010802' => 'Bloques',
        'F010803' => 'Ladrillos',
        'F010804' => 'Productos de hormigón prefabricado',
    ];

    /**
     * Tabla 32 — Anexo 25 §1. La ficha no cita resolución para estos
     * códigos (la NAC-DGERCGC26-00000024 respalda solo el §2, la placa);
     * fija únicamente la fecha desde la que son obligatorios.
     *
     * @var array<string, string>
     */
    private const array TRANSPORTE_COMERCIAL = [
        'H492001' => 'Factura emitida por la operadora al cliente',
        'H492002' => 'Factura emitida por el socio o accionista a la operadora de transporte',
    ];

    /**
     * El catálogo completo, listo para serializar.
     *
     * @return array<int, array{clave: string, nombre: string, anexo: int, tabla: int, baseLegal: string|null, obligatorioDesde: string|null, tagXml: array<string, string>, codigos: array<int, array{codigo: string, descripcion: string}>}>
     */
    public static function grupos(): array
    {
        return [
            [
                'clave' => 'materiales_construccion',
                'nombre' => 'Materiales de construcción',
                'anexo' => 23,
                'tabla' => 31,
                'baseLegal' => 'NAC-DGERCGC24-00000013',
                'obligatorioDesde' => null,
                'tagXml' => self::tagXmlPorTipo(),
                'codigos' => self::codigos(self::MATERIALES_CONSTRUCCION),
            ],
            [
                'clave' => 'transporte_comercial',
                'nombre' => 'Transporte comercial (excepto taxis)',
                'anexo' => 25,
                'tabla' => 32,
                'baseLegal' => null,
                'obligatorioDesde' => '2025-11-01',
                'tagXml' => self::tagXmlPorTipo(),
                'codigos' => self::codigos(self::TRANSPORTE_COMERCIAL),
            ],
        ];
    }

    /**
     * Todos los códigos del catálogo, sin agrupar (para tests y validaciones
     * del lado del integrador).
     *
     * @return list<string>
     */
    public static function todos(): array
    {
        return [
            ...array_keys(self::MATERIALES_CONSTRUCCION),
            ...array_keys(self::TRANSPORTE_COMERCIAL),
        ];
    }

    /**
     * Nombre del tag donde va el código según el tipo: el formato XML de la
     * nota de crédito llama `codigoAdicional` a lo que la factura llama
     * `codigoAuxiliar` (la ficha lo nombra solo por el segundo en el Anexo 23).
     *
     * @return array<string, string>
     */
    public static function tagXmlPorTipo(): array
    {
        return [
            TipoComprobante::Factura->rootElement() => 'codigoAuxiliar',
            TipoComprobante::LiquidacionCompra->rootElement() => 'codigoAuxiliar',
            TipoComprobante::NotaCredito->rootElement() => 'codigoAdicional',
        ];
    }

    /**
     * @param  array<string, string>  $tabla
     * @return array<int, array{codigo: string, descripcion: string}>
     */
    private static function codigos(array $tabla): array
    {
        $codigos = [];

        foreach ($tabla as $codigo => $descripcion) {
            $codigos[] = ['codigo' => $codigo, 'descripcion' => $descripcion];
        }

        return $codigos;
    }
}
