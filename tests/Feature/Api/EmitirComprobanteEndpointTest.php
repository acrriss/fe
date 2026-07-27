<?php

use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();
    $this->contribuyente = actuar_como_contribuyente();
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
    'tipo desconocido' => [fn (): array => ['recibo' => ['infoTributaria' => []]]],
]);

describe('endurecimiento (fase 4)', function () {
    it('reporta como 422 los datos que violan la ficha del SRI: :dataset', function (callable $sabotear) {
        $payload = golden_payload('factura');
        $sabotear($payload);

        $this->postJson(route('api.v1.comprobantes.emitir'), $payload)
            ->assertUnprocessable();
    })->with([
        'ruc inválido' => [function (array &$payload): void {
            $payload['factura']['infoTributaria']['ruc'] = '123';
        }],
        'secuencial inválido' => [function (array &$payload): void {
            $payload['factura']['infoTributaria']['secuencial'] = 'ABC';
        }],
        'fecha malformada' => [function (array &$payload): void {
            $payload['factura']['infoFactura']['fechaEmision'] = '2022-12-07'; // debe ser dd/mm/aaaa
        }],
        'tipo de identificación desconocido' => [function (array &$payload): void {
            $payload['factura']['infoFactura']['tipoIdentificacionComprador'] = '99';
        }],
        'cédula con dígito verificador inválido' => [function (array &$payload): void {
            $payload['factura']['infoFactura']['tipoIdentificacionComprador'] = '05';
            $payload['factura']['infoFactura']['identificacionComprador'] = '1713328505';
        }],
        'RUC de comprador con dígito verificador inválido' => [function (array &$payload): void {
            $payload['factura']['infoFactura']['tipoIdentificacionComprador'] = '04';
            $payload['factura']['infoFactura']['identificacionComprador'] = '0992223334001';
        }],
        'identificación que no corresponde al tipo declarado' => [function (array &$payload): void {
            // dice cédula, pero manda 13 dígitos
            $payload['factura']['infoFactura']['tipoIdentificacionComprador'] = '05';
            $payload['factura']['infoFactura']['identificacionComprador'] = '0992479248001';
        }],
        'consumidor final con identificación distinta de 9999999999999' => [function (array &$payload): void {
            $payload['factura']['infoFactura']['identificacionComprador'] = '0999999999999';
        }],
        'identificación del comprador vacía' => [function (array &$payload): void {
            $payload['factura']['infoFactura']['tipoIdentificacionComprador'] = '06';
            $payload['factura']['infoFactura']['identificacionComprador'] = '';
        }],
        'bloque infoFactura ausente' => [function (array &$payload): void {
            unset($payload['factura']['infoFactura']);
        }],
    ]);

    it('responde JSON en la API aunque el cliente no lo pida', function () {
        // sin Accept: application/json — un form POST clásico
        $this->post(route('api.v1.comprobantes.emitir'), [])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json');
    });

    it('limita la tasa de peticiones', function () {
        RateLimiter::for('api', fn (): Limit => Limit::perMinute(2));

        $this->postJson(route('api.v1.comprobantes.emitir'), [])->assertUnprocessable();
        $this->postJson(route('api.v1.comprobantes.emitir'), [])->assertUnprocessable();
        $this->postJson(route('api.v1.comprobantes.emitir'), [])->assertTooManyRequests();
    });
});

describe('multi-tenancy (fase 6)', function () {
    it('rechaza emitir con el RUC de otro contribuyente', function () {
        $payload = golden_payload('factura');
        $payload['factura']['infoTributaria']['ruc'] = '1791411099001'; // válido, pero no es el del contribuyente

        $this->postJson(route('api.v1.comprobantes.emitir'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comprobante.infoTributaria.ruc']);
    });

    it('responde 409 si el contribuyente no tiene certificado configurado', function () {
        $this->contribuyente->update(['certificado_p12' => null, 'certificado_clave' => null]);

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertStatus(409);
    });
});
