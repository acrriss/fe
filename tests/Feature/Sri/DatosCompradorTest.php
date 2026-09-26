<?php

use App\Models\Comprobante;
use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Support\ComprobanteXmlParser;

/*
 * Datos de contacto del comprador en la factura y la nota de crédito
 * (Anexo 3 y Anexo 2):
 *
 * - La dirección es un campo del XML, <direccionComprador>, «obligatorio
 *   cuando corresponda», entre <identificacionComprador> y
 *   <totalSinImpuestos>. El RIDE la imprime en el bloque del comprador.
 * - El correo y el teléfono NO son campos del XML: viajan como
 *   <campoAdicional> y el RIDE los imprime en «Información adicional».
 * - La nota de crédito no tiene <direccionComprador>: su RIDE del Anexo 2
 *   imprime la dirección y el correo en «Información adicional», así que
 *   los tres datos le llegan como <campoAdicional>.
 */

function factura_con_direccion(?string $direccion = 'Daniel Reyes 9-116, Ibarra'): FacturaData
{
    $factura = FacturaData::from(payload_factura(direccionComprador: $direccion));
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');

    return $factura;
}

function ride_factura_html(FacturaData $factura): string
{
    return view('ride.factura', [
        'registro' => Comprobante::factory()->autorizado()->make(),
        'comprobante' => $factura,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();
}

it('emite la dirección del comprador entre identificacionComprador y totalSinImpuestos (Anexo 3)', function () {
    $dom = simplexml_load_string(ConstruirXml::render(factura_con_direccion()));
    $tags = array_map(fn (SimpleXMLElement $hijo): string => $hijo->getName(), iterator_to_array($dom->infoFactura->children(), false));
    $posicion = array_search('direccionComprador', $tags, true);

    expect($posicion)->not->toBeFalse()
        ->and(array_slice($tags, (int) $posicion - 1, 3))->toBe(['identificacionComprador', 'direccionComprador', 'totalSinImpuestos'])
        ->and((string) $dom->infoFactura->direccionComprador)->toBe('Daniel Reyes 9-116, Ibarra');
});

it('sin dirección la factura no lleva el tag', function () {
    expect(xml_de_prueba('factura'))->not->toContain('<direccionComprador');
});

it('la dirección sobrevive al roundtrip XML → DTO → XML', function () {
    $xml = ConstruirXml::render(factura_con_direccion());

    $releida = (new ComprobanteXmlParser)->parse($xml);

    expect($releida)->toBeInstanceOf(FacturaData::class)
        ->and($releida->infoFactura->direccionComprador)->toBe('Daniel Reyes 9-116, Ibarra')
        ->and(ConstruirXml::render($releida))->toBe($xml);
});

it('imprime la dirección del comprador en el RIDE', function () {
    expect(ride_factura_html(factura_con_direccion()))
        ->toContain('Dirección:')
        ->toContain('Daniel Reyes 9-116, Ibarra');
});

it('no dibuja la dirección en el RIDE si la factura no la trae', function () {
    expect(ride_factura_html(factura_con_direccion(null)))->not->toContain('Dirección:');
});

it('imprime el correo y el teléfono del comprador en la información adicional del RIDE', function () {
    $factura = factura_con_direccion();
    $factura->agregarCampoAdicional('Email', 'cliente@example.com');
    $factura->agregarCampoAdicional('Teléfono', '0985512191');

    $html = ride_factura_html($factura);

    expect($html)->toContain('INFORMACIÓN ADICIONAL')
        ->toContain('cliente@example.com')
        ->toContain('0985512191');
});

function nota_credito_con_contacto(): NotaCreditoData
{
    $payload = payload_nota_credito();
    $payload['infoAdicional'] = ['campoAdicional' => [
        ['nombre' => 'Dirección', 'valor' => 'Daniel Reyes 9-116, Ibarra'],
        ['nombre' => 'Email', 'valor' => 'cliente@example.com'],
        ['nombre' => 'Teléfono', 'valor' => '0985512191'],
    ]];

    $notaCredito = NotaCreditoData::from($payload);
    $notaCredito->infoTributaria->claveAcceso = clave_acceso_de_prueba('notaCredito');

    return $notaCredito;
}

it('la nota de crédito rechaza direccionComprador, que su XML no tiene (Anexo 3)', function () {
    $payload = payload_nota_credito();
    $payload['infoNotaCredito']['direccionComprador'] = 'Daniel Reyes 9-116, Ibarra';

    expect(fn (): NotaCreditoData => NotaCreditoData::from($payload))->toThrow(DatoInvalido::class, 'direccionComprador');
});

it('la nota de crédito lleva dirección, correo y teléfono como campos adicionales tras el roundtrip', function () {
    $xml = ConstruirXml::render(nota_credito_con_contacto());

    $releida = (new ComprobanteXmlParser)->parse($xml);

    expect($xml)->toContain('<campoAdicional nombre="Dirección">Daniel Reyes 9-116, Ibarra</campoAdicional>')
        ->toContain('<campoAdicional nombre="Email">cliente@example.com</campoAdicional>')
        ->toContain('<campoAdicional nombre="Teléfono">0985512191</campoAdicional>')
        ->and(ConstruirXml::render($releida))->toBe($xml);
});

it('imprime la dirección, el correo y el teléfono en la información adicional del RIDE de la nota de crédito', function () {
    $html = view('ride.nota-credito', [
        'registro' => Comprobante::factory()->autorizado()->make(),
        'comprobante' => nota_credito_con_contacto(),
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    expect($html)->toContain('INFORMACIÓN ADICIONAL')
        ->toContain('Daniel Reyes 9-116, Ibarra')
        ->toContain('cliente@example.com')
        ->toContain('0985512191');
});
