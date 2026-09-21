<?php

namespace App\Sri\Support;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\GuiaRemision\GuiaRemisionData;
use App\Sri\Data\Liquidacion\LiquidacionCompraData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\NotaDebito\NotaDebitoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;

/**
 * Reconstruye el DTO tipado desde el XML de un comprobante (p. ej. el XML
 * firmado que quedó almacenado tras la emisión). Es la operación inversa a
 * ConstruirXml y está verificada por roundtrip (parse → render reproduce
 * byte a byte el XML generado) para todos los tipos.
 */
class ComprobanteXmlParser
{
    /**
     * @var array<string, class-string<ComprobanteData>>
     */
    private const array DATA_POR_ROOT = [
        'factura' => FacturaData::class,
        'notaCredito' => NotaCreditoData::class,
        'notaDebito' => NotaDebitoData::class,
        'comprobanteRetencion' => ComprobanteRetencionData::class,
        'guiaRemision' => GuiaRemisionData::class,
        'liquidacionCompra' => LiquidacionCompraData::class,
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

        // json_encode() sobre SimpleXML DESCARTA los atributos de un elemento
        // que además tiene texto: `<campoAdicional nombre="X">v</campoAdicional>`
        // llega como "v" a secas y el nombre se pierde. Hay que extraerlo a mano.
        $datos['infoAdicional'] = $this->infoAdicional($documento);

        return $dataClass::from($datos);
    }

    /**
     * @return array{campoAdicional: array<int, array{nombre: string, valor: string}>}|null
     */
    private function infoAdicional(\SimpleXMLElement $documento): ?array
    {
        if (! isset($documento->infoAdicional->campoAdicional)) {
            return null;
        }

        $campos = [];

        foreach ($documento->infoAdicional->campoAdicional as $campo) {
            $campos[] = [
                'nombre' => (string) $campo->attributes()->nombre,
                'valor' => (string) $campo,
            ];
        }

        return ['campoAdicional' => $campos];
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
