<?php

use App\Jobs\ProcesarComprobanteJob;
use App\Models\Comprobante;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\Registro\RegistroDeEmision;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();
});

describe('emisión asíncrona', function () {
    it('encola el job y responde 202 con el id para consultar', function () {
        Queue::fake();

        $respuesta = $this->postJson(
            route('api.v1.comprobantes.emitir', ['async' => 1]),
            golden_payload('factura'),
        );

        $respuesta->assertStatus(202)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.tipo', 'factura')
            ->assertJsonStructure(['data' => ['id', 'estado', 'secuencial']]);

        Queue::assertPushed(ProcesarComprobanteJob::class, 1);

        $registro = Comprobante::where('uuid', $respuesta->json('data.id'))->first();
        expect($registro)->not->toBeNull()
            ->and($registro->estado)->toBe(EstadoComprobante::Pendiente);
    });

    it('el job emite y completa el registro con el XML almacenado', function () {
        $registro = Comprobante::factory()->create();

        new ProcesarComprobanteJob(
            registro: $registro,
            dataClass: FacturaData::class,
            payloadComprobante: golden_input('factura'),
            p12Base64: base64_encode('certificado-dummy'),
            claveP12: 'secreto',
        )->handle(app(EmitirComprobante::class), app(RegistroDeEmision::class));

        $registro->refresh();

        expect($registro->estado)->toBe(EstadoComprobante::Autorizado)
            ->and($registro->clave_acceso)->toMatch('/^\d{49}$/')
            ->and($registro->numero_autorizacion)->not->toBeNull()
            ->and($registro->xml_path)->not->toBeNull();

        Storage::assertExists($registro->xml_path);
        expect(Storage::get($registro->xml_path))->toContain('<factura id="comprobante"');
    });

    it('el job registra la devolución como fallo de negocio (sin reintentos)', function () {
        $this->gateway->devolverComprobantes();
        $registro = Comprobante::factory()->create();

        new ProcesarComprobanteJob(
            registro: $registro,
            dataClass: FacturaData::class,
            payloadComprobante: golden_input('factura'),
            p12Base64: base64_encode('certificado-dummy'),
            claveP12: 'secreto',
        )->handle(app(EmitirComprobante::class), app(RegistroDeEmision::class));

        $registro->refresh();

        expect($registro->estado)->toBe(EstadoComprobante::Devuelto)
            ->and($registro->clave_acceso)->toMatch('/^\d{49}$/') // §5.10: la clave se conserva para reintentar
            ->and(implode(' ', $registro->mensajes))->toContain('ERROR SECUENCIAL REGISTRADO');
    });

    it('failed() marca el registro como fallido ante errores técnicos', function () {
        $registro = Comprobante::factory()->create();

        new ProcesarComprobanteJob(
            registro: $registro,
            dataClass: FacturaData::class,
            payloadComprobante: golden_input('factura'),
            p12Base64: base64_encode('certificado-dummy'),
            claveP12: 'secreto',
        )->failed(new RuntimeException('SRI caído'));

        expect($registro->refresh()->estado)->toBe(EstadoComprobante::Fallido);
    });

    it('el job viaja cifrado en la cola (transporta el certificado)', function () {
        expect(ProcesarComprobanteJob::class)->toImplement(ShouldBeEncrypted::class);
    });
});

describe('consulta de estado', function () {
    it('devuelve el estado de una emisión pendiente', function () {
        $registro = Comprobante::factory()->create();

        $this->getJson(route('api.v1.comprobantes.mostrar', $registro))
            ->assertSuccessful()
            ->assertJsonPath('data.id', $registro->uuid)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.estadoFinal', false)
            ->assertJsonMissingPath('data.xmlFirmado');
    });

    it('devuelve autorización y XML cuando está autorizado', function () {
        $registro = Comprobante::factory()->autorizado()->create();
        Storage::put($path = "comprobantes/{$registro->clave_acceso}.xml", '<factura>firmada</factura>');
        $registro->update(['xml_path' => $path]);

        $respuesta = $this->getJson(route('api.v1.comprobantes.mostrar', $registro));

        $respuesta->assertSuccessful()
            ->assertJsonPath('data.estado', 'autorizado')
            ->assertJsonPath('data.estadoFinal', true)
            ->assertJsonPath('data.autorizacion.numero', $registro->numero_autorizacion);

        expect(base64_decode($respuesta->json('data.xmlFirmado')))->toBe('<factura>firmada</factura>');
    });

    it('responde 404 para un id desconocido', function () {
        $this->getJson(route('api.v1.comprobantes.mostrar', 'no-existe'))
            ->assertNotFound();
    });
});

describe('persistencia del flujo síncrono', function () {
    it('registra la emisión autorizada', function () {
        $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'));

        $respuesta->assertSuccessful();

        $registro = Comprobante::where('uuid', $respuesta->json('id'))->first();

        expect($registro->estado)->toBe(EstadoComprobante::Autorizado)
            ->and($registro->clave_acceso)->toBe($respuesta->json('claveAcceso'))
            ->and($registro->importe_total)->toBe('11.20');

        Storage::assertExists($registro->xml_path);
    });

    it('registra la emisión devuelta con sus mensajes', function () {
        $this->gateway->devolverComprobantes();

        $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'));

        $respuesta->assertUnprocessable();

        $registro = Comprobante::where('uuid', $respuesta->json('id'))->first();

        expect($registro->estado)->toBe(EstadoComprobante::Devuelto)
            ->and($registro->mensajes)->not->toBeEmpty();
    });
});
