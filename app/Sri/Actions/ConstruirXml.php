<?php

namespace App\Sri\Actions;

use App\Sri\Data\ComprobanteData;
use App\Sri\Pipeline\EmisionEnCurso;
use Closure;
use Spatie\ArrayToXml\ArrayToXml;

/**
 * Construye el XML del comprobante (pre-firma) a partir de su DTO.
 *
 * Reproduce exactamente el formato del legado: raíz con id="comprobante" y
 * versión del esquema, declaración UTF-8 y salida indentada (verificado
 * byte a byte contra fixtures/golden).
 */
final class ConstruirXml
{
    public function __invoke(EmisionEnCurso $emision, Closure $next): mixed
    {
        $emision->xml = self::render($emision->comprobante);

        return $next($emision);
    }

    public static function render(ComprobanteData $comprobante): string
    {
        $tipo = $comprobante::tipo();

        $arrayToXml = new ArrayToXml($comprobante->xmlArray(), [
            'rootElementName' => $tipo->rootElement(),
            '_attributes' => [
                'id' => 'comprobante',
                'version' => $tipo->versionEsquema(),
            ],
        ], true, 'UTF-8');

        $dom = $arrayToXml->toDom();
        $dom->formatOutput = true;

        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new \RuntimeException('No se pudo serializar el XML del comprobante.');
        }

        return $xml;
    }
}
