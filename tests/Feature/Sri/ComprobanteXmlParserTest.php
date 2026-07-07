<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Support\ComprobanteXmlParser;

/*
 * El parser es la inversa de ConstruirXml: parsear el XML golden y volverlo
 * a renderizar debe reproducirlo byte a byte (roundtrip).
 */
it('hace roundtrip byte a byte del XML golden de la factura', function () {
    $xmlGolden = file_get_contents(golden_path('factura/comprobante.xml'));

    $comprobante = new ComprobanteXmlParser()->parse($xmlGolden);

    expect($comprobante)->toBeInstanceOf(FacturaData::class)
        ->and((string) $comprobante->infoTributaria->claveAcceso)
        ->toBe(trim(file_get_contents(golden_path('factura/claveAcceso.txt'))))
        ->and(ConstruirXml::render($comprobante))->toBe($xmlGolden);
});

it('parsea el XML aunque esté firmado (la firma vive en otro namespace)', function () {
    $xmlFirmado = file_get_contents(golden_path('factura/comprobante.xml')).'<!--firma-fake-->';

    $comprobante = new ComprobanteXmlParser()->parse($xmlFirmado);

    expect($comprobante->infoFactura->importeTotal)->toBe('11.20')
        ->and($comprobante->detalles)->toHaveCount(1);
});

it('detecta el tipo desde el elemento raíz', function () {
    $xml = file_get_contents(golden_path('notaCredito/comprobante.xml'));

    expect(new ComprobanteXmlParser()->tipoDe($xml))->toBe(TipoComprobante::NotaCredito);
});

it('rechaza XML malformado o de tipo desconocido', function (string $xml) {
    new ComprobanteXmlParser()->parse($xml);
})->with([
    'malformado' => 'esto no es xml',
    'tipo desconocido' => '<recibo><infoTributaria/></recibo>',
])->throws(InvalidArgumentException::class);
