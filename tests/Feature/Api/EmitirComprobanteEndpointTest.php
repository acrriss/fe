<?php

use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
});

it('emite una factura vía POST /api/v1/comprobantes', function () {
    $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'));

    $respuesta->assertSuccessful()
        ->assertJsonPath('emitido', true)
        ->assertJsonPath('tipo', 'factura')
        ->assertJsonPath('autorizacion.estado', 'AUTORIZADO')
        ->assertJsonStructure(['claveAcceso', 'autorizacion' => ['numero', 'fecha'], 'xmlFirmado']);

    expect($respuesta->json('claveAcceso'))->toMatch('/^\d{49}$/')
        ->and(base64_decode($respuesta->json('xmlFirmado')))->toContain('<factura id="comprobante"');
});

it('emite los otros tipos de comprobante: :dataset', function (string $tipo) {
    $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload($tipo))
        ->assertSuccessful()
        ->assertJsonPath('emitido', true)
        ->assertJsonPath('tipo', $tipo);
})->with(['notaCredito', 'comprobanteRetencion']);

it('responde 422 con los mensajes del SRI cuando el comprobante es devuelto', function () {
    $this->gateway->devolverComprobantes();

    $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
        ->assertUnprocessable()
        ->assertJsonPath('emitido', false)
        ->assertJsonPath('etapa', 'recepcion');
});

it('responde 422 con la clave de acceso cuando la autorización es rechazada', function () {
    $this->gateway->rechazarAutorizacion();

    $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'));

    $respuesta->assertUnprocessable()
        ->assertJsonPath('emitido', false)
        ->assertJsonPath('etapa', 'autorizacion');

    // la clave viaja en el error: permite consultar el estado después
    expect($respuesta->json('claveAcceso'))->toMatch('/^\d{49}$/');
});

it('valida el payload: :dataset', function (array $payload) {
    $this->postJson(route('api.v1.comprobantes.emitir'), $payload)
        ->assertUnprocessable()
        ->assertJsonStructure(['errors']);
})->with([
    'vacío' => [[]],
    'tipo desconocido' => [fn (): array => ['recibo' => ['infoTributaria' => []], 'info' => ['p12' => 'x', 'clavep12' => 'y']]],
    'sin certificado' => [fn (): array => collect(golden_payload('factura'))->except('info')->all()],
]);

it('rechaza un certificado que no es base64 válido', function () {
    $payload = golden_payload('factura');
    $payload['info']['p12'] = '***no-es-base64***';

    $this->postJson(route('api.v1.comprobantes.emitir'), $payload)
        ->assertStatus(500); // DatoInvalido — se refinará al modelar errores HTTP en fase 4
})->todo('convertir DatoInvalido en respuesta 422 en la fase 4');
