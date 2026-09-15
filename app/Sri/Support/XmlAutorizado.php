<?php

namespace App\Sri\Support;

use App\Models\Comprobante;
use DOMDocument;
use RuntimeException;

/**
 * Envuelve el XML firmado en el nodo <autorizacion> del SRI.
 *
 * Es el documento que el emisor está obligado a entregar al receptor y el
 * que devuelve la consulta pública: el XML firmado por sí solo no acredita
 * nada, porque le faltan el número y la fecha de autorización que otorga el
 * SRI (aquí viven en columnas del registro, no dentro del XML).
 *
 * El comprobante viaja en CDATA con su propia declaración XML, tal como lo
 * emite el servicio de autorización del SRI: el software receptor espera
 * encontrarlo así para poder extraerlo y validar la firma.
 */
final class XmlAutorizado
{
    public static function render(Comprobante $comprobante, string $xmlFirmado): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $raiz = $dom->createElement('autorizacion');
        $dom->appendChild($raiz);

        $raiz->appendChild($dom->createElement('estado', 'AUTORIZADO'));
        $raiz->appendChild($dom->createElement('numeroAutorizacion', self::numeroAutorizacion($comprobante)));
        $raiz->appendChild($dom->createElement(
            'fechaAutorizacion',
            $comprobante->autorizado_en?->toIso8601String() ?? '',
        ));
        $raiz->appendChild($dom->createElement('ambiente', $comprobante->ambiente->etiqueta()));

        $nodo = $dom->createElement('comprobante');
        $nodo->appendChild($dom->createCDATASection($xmlFirmado));
        $raiz->appendChild($nodo);

        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new RuntimeException('No se pudo serializar el XML autorizado.');
        }

        return $xml;
    }

    /**
     * En el esquema offline el número de autorización es la propia clave de
     * acceso; se conserva el que devolvió el SRI cuando existe.
     */
    private static function numeroAutorizacion(Comprobante $comprobante): string
    {
        return $comprobante->numero_autorizacion
            ?? $comprobante->clave_acceso
            ?? '';
    }
}
