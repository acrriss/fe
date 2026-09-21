<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;

/*
 * El SRI valida el XML contra sus esquemas (.xsd, ficha §5.1), no byte a
 * byte: lo que se protege aquí son las invariantes estructurales que la
 * ficha fija (raíz, versión, orden de bloques, tags de infoTributaria con
 * codDoc y clave derivados) y que los importes viajen literales.
 */
dataset('estructura por tipo', [
    'factura' => ['factura', 'infoFactura', 'detalles', '01', '1.1.0'],
    'notaCredito' => ['notaCredito', 'infoNotaCredito', 'detalles', '04', '1.1.0'],
    'comprobanteRetencion' => ['comprobanteRetencion', 'infoCompRetencion', 'impuestos', '07', '1.0.0'],
    'notaDebito' => ['notaDebito', 'infoNotaDebito', 'motivos', '05', '1.0.0'],
    'guiaRemision' => ['guiaRemision', 'infoGuiaRemision', 'destinatarios', '06', '1.0.0'],
    'liquidacionCompra' => ['liquidacionCompra', 'infoLiquidacionCompra', 'detalles', '03', '1.1.0'],
]);

it('construye el XML de :dataset con la raíz, la versión y el orden de bloques de la ficha', function (
    string $root,
    string $bloqueInfo,
    string $bloqueCuerpo,
    string $codDoc,
    string $version,
) {
    $xml = xml_de_prueba($root);
    $dom = simplexml_load_string($xml);

    expect($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($dom)->not->toBeFalse()
        ->and($dom->getName())->toBe($root)
        ->and((string) $dom['id'])->toBe('comprobante')
        ->and((string) $dom['version'])->toBe($version);

    $bloques = array_map(fn (SimpleXMLElement $hijo): string => $hijo->getName(), iterator_to_array($dom->children(), false));

    expect(array_slice($bloques, 0, 3))->toBe(['infoTributaria', $bloqueInfo, $bloqueCuerpo]);
})->with('estructura por tipo');

it('deriva codDoc y clave de acceso en infoTributaria de :dataset', function (string $root, string $bloqueInfo, string $bloqueCuerpo, string $codDoc) {
    $dom = simplexml_load_string(xml_de_prueba($root));
    $clave = (string) clave_acceso_de_prueba($root);

    $tags = array_map(fn (SimpleXMLElement $hijo): string => $hijo->getName(), iterator_to_array($dom->infoTributaria->children(), false));

    expect($tags)->toBe([
        'ambiente', 'tipoEmision', 'razonSocial', 'nombreComercial', 'ruc',
        'claveAcceso', 'codDoc', 'estab', 'ptoEmi', 'secuencial', 'dirMatriz',
    ])
        ->and((string) $dom->infoTributaria->codDoc)->toBe($codDoc)
        ->and((string) $dom->infoTributaria->claveAcceso)->toBe($clave)
        ->and(substr($clave, 8, 2))->toBe($codDoc);
})->with('estructura por tipo');

it('escribe los importes y fechas literales, sin pasar por float', function () {
    $xml = xml_de_prueba('factura');

    expect($xml)
        ->toContain('<fechaEmision>10/07/2026</fechaEmision>')
        ->toContain('<totalSinImpuestos>100.00</totalSinImpuestos>')
        ->toContain('<totalDescuento>0.00</totalDescuento>')
        ->toContain('<tarifa>15.00</tarifa>')
        ->toContain('<importeTotal>115.00</importeTotal>')
        ->not->toContain('<importeTotal>115</importeTotal>');
});

it('representa las colecciones con el wrapper del SRI aunque tengan un solo elemento', function () {
    $factura = simplexml_load_string(xml_de_prueba('factura'));
    $nota = simplexml_load_string(xml_de_prueba('notaCredito'));

    expect($factura->detalles->detalle)->toHaveCount(1)
        ->and($factura->detalles->detalle[0]->impuestos->impuesto)->toHaveCount(1)
        ->and($factura->infoFactura->totalConImpuestos->totalImpuesto)->toHaveCount(1)
        ->and($nota->detalles->detalle)->toHaveCount(2)
        ->and($nota->infoNotaCredito->totalConImpuestos->totalImpuesto)->toHaveCount(2);
});

it('omite los campos opcionales que no vienen en el payload', function () {
    $payload = payload_factura();
    unset($payload['infoFactura']['dirEstablecimiento'], $payload['infoFactura']['propina']);

    $factura = FacturaData::from($payload);
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');

    $xml = ConstruirXml::render($factura);

    expect($xml)->not->toContain('<dirEstablecimiento')
        ->not->toContain('<propina')
        ->not->toContain('<infoAdicional');
});

it('exige la clave de acceso antes de construir el XML', function () {
    ConstruirXml::render(FacturaData::from(payload_factura()));
})->throws(LogicException::class);

it('usa fechaEmision() polimórfica según el tipo', function () {
    expect(FacturaData::from(payload_factura())->fechaEmision()->format('d/m/Y'))->toBe('10/07/2026')
        ->and(NotaCreditoData::from(payload_nota_credito())->fechaEmision()->format('d/m/Y'))->toBe('10/07/2026')
        ->and(ComprobanteRetencionData::from(payload_retencion())->fechaEmision()->format('d/m/Y'))->toBe('10/07/2026');
});
