<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\ComprobanteXmlParser;

/*
 * El parser es la inversa de ConstruirXml: parsear un XML generado por el
 * sistema y volverlo a renderizar debe reproducirlo byte a byte
 * (roundtrip). Es lo que garantiza que el RIDE y las descargas, que se
 * generan releyendo el XML almacenado, vean exactamente lo que se emitió.
 */
it('hace roundtrip byte a byte XML → DTO → XML de :dataset', function (string $tipo) {
    $xml = xml_de_prueba($tipo);

    $comprobante = new ComprobanteXmlParser()->parse($xml);

    expect($comprobante)->toBeInstanceOf(data_class_de($tipo))
        ->and((string) $comprobante->infoTributaria->claveAcceso)->toBe((string) clave_acceso_de_prueba($tipo))
        ->and(ConstruirXml::render($comprobante))->toBe($xml);
})->with(['factura', 'notaCredito', 'comprobanteRetencion', 'notaDebito', 'guiaRemision', 'liquidacionCompra']);

it('parsea el XML aunque esté firmado (la firma vive en otro namespace)', function () {
    $xmlFirmado = xml_de_prueba('factura').'<!--firma-fake-->';

    $comprobante = new ComprobanteXmlParser()->parse($xmlFirmado);

    expect($comprobante)->toBeInstanceOf(FacturaData::class)
        ->and($comprobante->infoFactura->importeTotal)->toBe('115.00')
        ->and($comprobante->detalles)->toHaveCount(1);
});

it('detecta el tipo desde el elemento raíz', function () {
    expect(new ComprobanteXmlParser()->tipoDe(xml_de_prueba('notaCredito')))->toBe(TipoComprobante::NotaCredito);
});

it('rechaza XML malformado o de tipo desconocido', function (string $xml) {
    new ComprobanteXmlParser()->parse($xml);
})->with([
    'malformado' => 'esto no es xml',
    'tipo desconocido' => '<recibo><infoTributaria/></recibo>',
])->throws(InvalidArgumentException::class);
