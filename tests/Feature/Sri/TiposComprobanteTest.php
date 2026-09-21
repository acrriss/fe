<?php

use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\GuiaRemision\GuiaRemisionData;
use App\Sri\Data\Liquidacion\LiquidacionCompraData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\NotaDebito\NotaDebitoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;

/*
 * Mapa tipo ↔ DTO ↔ codDoc (Tabla 3 de la ficha) ↔ versión de esquema. La
 * construcción del XML, el roundtrip y la emisión de cada tipo se prueban
 * en ConstruirXmlTest, ComprobanteXmlParserTest y PayloadsDePruebaTest.
 */
it('deriva tipo, codDoc y versión de esquema de :dataset', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version) {
    expect($clase::tipo())->toBe($tipo)
        ->and($tipo->value)->toBe($codDoc)
        ->and($tipo->rootElement())->toBe($root)
        ->and(TipoComprobante::fromRootElement($root))->toBe($tipo)
        ->and($tipo->versionEsquema())->toBe($version);
})->with([
    'factura' => ['factura', FacturaData::class, TipoComprobante::Factura, '01', '1.1.0'],
    'liquidacionCompra' => ['liquidacionCompra', LiquidacionCompraData::class, TipoComprobante::LiquidacionCompra, '03', '1.1.0'],
    'notaCredito' => ['notaCredito', NotaCreditoData::class, TipoComprobante::NotaCredito, '04', '1.1.0'],
    'notaDebito' => ['notaDebito', NotaDebitoData::class, TipoComprobante::NotaDebito, '05', '1.0.0'],
    'guiaRemision' => ['guiaRemision', GuiaRemisionData::class, TipoComprobante::GuiaRemision, '06', '1.0.0'],
    'comprobanteRetencion' => ['comprobanteRetencion', ComprobanteRetencionData::class, TipoComprobante::ComprobanteRetencion, '07', '1.0.0'],
]);
