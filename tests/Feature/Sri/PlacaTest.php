<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Support\ComprobanteXmlParser;
use App\Sri\ValueObjects\Placa;

/*
 * Ficha 2.34, Anexo 25 §2 (Resolución NAC-DGERCGC26-00000024): las facturas
 * de las operadoras de transporte comercial, excepto taxis, llevan la placa
 * del vehículo entre <moneda> y <pagos>. Tabla 33: tres letras y cuatro
 * dígitos, sin espacios; con tres dígitos se rellena con un cero delante.
 */

function factura_con_placa(string $placa): FacturaData
{
    $factura = FacturaData::from(payload_factura(placa: $placa));
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');

    return $factura;
}

it('emite la placa como último tag de infoFactura, tras moneda', function () {
    $dom = simplexml_load_string(ConstruirXml::render(factura_con_placa('PCM4567')));
    $tags = array_map(fn (SimpleXMLElement $hijo): string => $hijo->getName(), iterator_to_array($dom->infoFactura->children(), false));

    expect(array_slice($tags, -2))->toBe(['moneda', 'placa'])
        ->and((string) $dom->infoFactura->placa)->toBe('PCM4567');
});

it('normaliza «:dataset» al formato de la Tabla 33', function (string $entrada, string $esperada) {
    expect((string) Placa::fromString($entrada))->toBe($esperada);
})->with([
    'ya canónica' => ['PCM4567', 'PCM4567'],
    'minúsculas' => ['pcm4567', 'PCM4567'],
    'tres dígitos: rellena con cero' => ['ABC123', 'ABC0123'],
    'con guion' => ['ABC-1234', 'ABC1234'],
    'con espacios' => ['ABC 1234', 'ABC1234'],
    'tres dígitos con guion y minúsculas' => ['abc-123', 'ABC0123'],
]);

it('rechaza «:dataset» con un mensaje que cita el formato', function (string $invalida) {
    expect(fn () => Placa::fromString($invalida))
        ->toThrow(DatoInvalido::class, 'tres letras y tres o cuatro dígitos');
})->with([
    'dos letras' => 'AB1234',
    'cuatro letras' => 'ABCD1234',
    'cinco dígitos' => 'ABC12345',
    'dos dígitos' => 'ABC12',
    'letras al final' => 'AB023C',
    'vacía tras limpiar' => '---',
]);

it('sin placa la factura no lleva el tag', function () {
    expect(xml_de_prueba('factura'))->not->toContain('<placa');
});

it('sobrevive al roundtrip XML → DTO → XML', function () {
    $xml = ConstruirXml::render(factura_con_placa('abc-123'));

    $releida = (new ComprobanteXmlParser)->parse($xml);

    expect($xml)->toContain('<placa>ABC0123</placa>')
        ->and((string) $releida->infoFactura->placa)->toBe('ABC0123')
        ->and(ConstruirXml::render($releida))->toBe($xml);
});

/*
 * La placa de la guía de remisión es otro campo, con otro formato (texto
 * libre hasta 20): no se toca.
 */
it('no cambia la placa de la guía de remisión', function () {
    expect(xml_de_prueba('guiaRemision'))->toContain('<placa>MCL0827</placa>');
});
