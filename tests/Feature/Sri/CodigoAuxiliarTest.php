<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\Liquidacion\LiquidacionCompraData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Support\ComprobanteXmlParser;

/*
 * Ficha 2.34, Anexo 23 (materiales de construcción, Tabla 31) y Anexo 25 §1
 * (transporte comercial, Tabla 32): el código de la actividad regulada va
 * en el segundo código de cada ítem. En factura y liquidación ese tag es
 * <codigoAuxiliar>; en la nota de crédito, <codigoAdicional>. Es dato de
 * la transacción: lo manda el cliente en el payload y se emite tal cual.
 */

/**
 * @return list<string>
 */
function tags_del_primer_detalle(string $xml): array
{
    $dom = simplexml_load_string($xml);

    return array_map(fn (SimpleXMLElement $hijo): string => $hijo->getName(), iterator_to_array($dom->detalles->detalle[0]->children(), false));
}

it('emite codigoAuxiliar justo tras codigoPrincipal en :dataset', function (string $tipo, string $codigo) {
    $comprobante = comprobante_de_prueba($tipo);
    $comprobante->detalles[0]->codigoAuxiliar = $codigo;

    $xml = ConstruirXml::render($comprobante);

    expect(array_slice(tags_del_primer_detalle($xml), 0, 3))->toBe(['codigoPrincipal', 'codigoAuxiliar', 'descripcion'])
        ->and($xml)->toContain("<codigoAuxiliar>{$codigo}</codigoAuxiliar>");
})->with([
    'factura con material de construcción' => ['factura', 'F010101'],
    'factura de transporte comercial' => ['factura', 'H492001'],
    'liquidación de compra' => ['liquidacionCompra', 'F010202'],
]);

it('en la nota de crédito el segundo código es codigoAdicional, tras codigoInterno', function () {
    $nota = comprobante_de_prueba('notaCredito');
    $nota->detalles[0]->codigoAdicional = 'F010101';

    $xml = ConstruirXml::render($nota);

    expect(array_slice(tags_del_primer_detalle($xml), 0, 3))->toBe(['codigoInterno', 'codigoAdicional', 'descripcion'])
        ->and($xml)->toContain('<codigoAdicional>F010101</codigoAdicional>')
        ->not->toContain('<codigoAuxiliar');
});

it('sin segundo código el detalle no lleva el tag', function (string $tipo) {
    expect(xml_de_prueba($tipo))
        ->not->toContain('<codigoAuxiliar')
        ->not->toContain('<codigoAdicional');
})->with(['factura', 'notaCredito', 'liquidacionCompra']);

it('llega desde el payload y sobrevive al roundtrip XML → DTO → XML', function () {
    $factura = FacturaData::from(payload_factura(codigoAuxiliar: 'H492002'));
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');
    $xml = ConstruirXml::render($factura);

    $releida = (new ComprobanteXmlParser)->parse($xml);

    expect($factura->detalles[0]->codigoAuxiliar)->toBe('H492002')
        ->and($releida->detalles[0]->codigoAuxiliar)->toBe('H492002')
        ->and(ConstruirXml::render($releida))->toBe($xml);
});

it('los builders de prueba aceptan el segundo código en NC y liquidación', function () {
    expect(NotaCreditoData::from(payload_nota_credito(codigoAdicional: 'F010801'))->detalles[0]->codigoAdicional)->toBe('F010801')
        ->and(LiquidacionCompraData::from(payload_liquidacion(codigoAuxiliar: 'F010801'))->detalles[0]->codigoAuxiliar)->toBe('F010801');
});
