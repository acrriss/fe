<?php

use App\Models\Comprobante;
use App\Sri\Actions\ConstruirXml;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use App\Sri\Support\ComprobanteXmlParser;
use Illuminate\Support\Facades\Storage;

/*
 * Las formas de pago (<pagos>) tienen que sumar exactamente el importe del
 * comprobante: importeTotal en factura y liquidación de compra, valorTotal
 * en nota de débito. La ficha no lo exige y el SRI de pruebas autorizó
 * facturas que no cuadraban (prueba del 2026-09-29), pero es una regla del
 * servicio: un documento que dice cobrar más o menos de lo que vale es un
 * error del integrador. Se rechaza al emitir, no al releer un XML ya
 * autorizado.
 */

beforeEach(function () {
    $this->app->instance(SriGateway::class, new FakeSriGateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();
    actuar_como_contribuyente();
});

/**
 * El payload de emisión del tipo dado con otras formas de pago.
 *
 * @param  list<array<string, string>>  $pagos
 * @return array<string, mixed>
 */
function emision_con_pagos(string $tipo, array $pagos): array
{
    $bloque = [
        'factura' => 'infoFactura',
        'notaDebito' => 'infoNotaDebito',
        'liquidacionCompra' => 'infoLiquidacionCompra',
    ][$tipo];

    $payload = payload_emision($tipo);
    $payload[$tipo][$bloque]['pagos'] = ['pago' => $pagos];

    return $payload;
}

dataset('comprobantes con pagos', [
    // tipo, importe del comprobante en su payload de prueba
    'factura' => ['factura', '115.00'],
    'nota de débito' => ['notaDebito', '57.50'],
    'liquidación de compra' => ['liquidacionCompra', '57.50'],
]);

it('rechaza en :dataset unas formas de pago que suman menos que el importe', function (string $tipo, string $importe) {
    $this->postJson(route('api.v1.comprobantes.emitir'), emision_con_pagos($tipo, [
        ['formaPago' => '01', 'total' => '5.00'],
    ]))->assertInvalid(['comprobante' => "La suma de las formas de pago (5.00) no coincide con el importe total del comprobante ({$importe})"]);

    expect(Comprobante::count())->toBe(0);
})->with('comprobantes con pagos');

it('rechaza en :dataset unas formas de pago que suman más que el importe', function (string $tipo, string $importe) {
    $this->postJson(route('api.v1.comprobantes.emitir'), emision_con_pagos($tipo, [
        ['formaPago' => '01', 'total' => $importe],
        ['formaPago' => '19', 'total' => '5.00'],
    ]))->assertInvalid(['comprobante' => 'no coincide con el importe total del comprobante']);

    expect(Comprobante::count())->toBe(0);
})->with('comprobantes con pagos');

it('acepta en :dataset un pago mixto que suma exactamente el importe', function (string $tipo, string $importe) {
    $resto = bcsub($importe, '5.00', 2);

    $this->postJson(route('api.v1.comprobantes.emitir'), emision_con_pagos($tipo, [
        ['formaPago' => '01', 'total' => $resto],
        ['formaPago' => '19', 'total' => '5.00'],
    ]))->assertSuccessful()->assertJsonPath('emitido', true);
})->with('comprobantes con pagos');

it('compara importes como decimales, no como texto', function () {
    $this->postJson(route('api.v1.comprobantes.emitir'), emision_con_pagos('factura', [
        ['formaPago' => '01', 'total' => '100'],
        ['formaPago' => '19', 'total' => '15.0'],
    ]))->assertSuccessful();
});

it('también lo exige al reintentar con un payload nuevo', function () {
    $registro = Comprobante::factory()->create([
        'contribuyente_id' => auth()->user()->contribuyente_id,
        'estado' => 'devuelto',
    ]);

    $this->postJson(
        route('api.v1.comprobantes.reintentar', $registro),
        emision_con_pagos('factura', [['formaPago' => '01', 'total' => '5.00']]),
    )->assertInvalid(['comprobante' => 'no coincide con el importe total']);
});

/*
 * Antes de la regla se autorizaron comprobantes que no cuadraban: su XML
 * se sigue leyendo y su RIDE se sigue generando.
 */
it('relee y dibuja un XML ya emitido cuyas formas de pago no cuadran', function () {
    $payload = payload_factura(pagos: [['formaPago' => '01', 'total' => '5.00']]);
    $factura = FacturaData::from($payload);
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');

    $releida = (new ComprobanteXmlParser)->parse(ConstruirXml::render($factura));

    $html = view('ride.factura', [
        'registro' => Comprobante::factory()->autorizado()->make(),
        'comprobante' => $releida,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    expect($releida->formasDePago()[0]->total)->toBe('5.00')
        ->and($html)->toContain('5.00');
});
