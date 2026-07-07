<?php

namespace App\Sri\Support;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;

/**
 * Reconstruye el DTO tipado desde el XML de un comprobante (p. ej. el XML
 * firmado que quedó almacenado tras la emisión). Es la operación inversa a
 * ConstruirXml y está verificada por roundtrip contra los fixtures golden.
 */
class ComprobanteXmlParser
{
    /**
     * @var array<string, class-string<ComprobanteData>>
     */
    private const array DATA_POR_ROOT = [
        'factura' => FacturaData::class,
        'notaCredito' => NotaCreditoData::class,
        'comprobanteRetencion' => ComprobanteRetencionData::class,
    ];

    public function parse(string $xml): ComprobanteData
    {
        $documento = $this->cargar($xml);
        $root = $documento->getName();
        $dataClass = self::DATA_POR_ROOT[$root]
            ?? throw new \InvalidArgumentException("Tipo de comprobante desconocido: {$root}.");

        // SimpleXML → array. Los valores llegan como string (igual que en el
        // payload JSON) y la firma ds:Signature queda fuera al vivir en otro
        // namespace. El wrapper 1-vs-N lo normaliza prepareForPipeline.
        $datos = json_decode((string) json_encode($documento), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($datos));
        unset($datos['@attributes']);

        return $dataClass::from($datos);
    }

    public function tipoDe(string $xml): TipoComprobante
    {
        return TipoComprobante::fromRootElement($this->cargar($xml)->getName());
    }

    private function cargar(string $xml): \SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);

        try {
            $documento = simplexml_load_string($xml);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        if ($documento === false) {
            throw new \InvalidArgumentException('El XML del comprobante no está bien formado.');
        }

        return $documento;
    }
}
