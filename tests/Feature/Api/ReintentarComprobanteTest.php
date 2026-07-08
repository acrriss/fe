<?php

use App\Jobs\ProcesarComprobanteJob;
use App\Models\Comprobante;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();
    $this->contribuyente = actuar_como_contribuyente();
});

/**
 * Emite la factura golden contra un SRI que rechaza, dejando un registro
 * no_autorizado con clave persistida (el escenario real de §5.10).
 */
function emision_rechazada(): Comprobante
{
    test()->gateway->rechazarAutorizacion();

    $respuesta = test()->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'));
    $respuesta->assertUnprocessable();

    // el siguiente intento del gateway fake vuelve a autorizar
    test()->gateway->autorizarDeNuevo();

    return Comprobante::where('uuid', $respuesta->json('id'))->firstOrFail();
}

it('reintenta con la MISMA clave de acceso y completa la autorización (§5.10)', function () {
    $registro = emision_rechazada();
    $claveOriginal = $registro->clave_acceso;

    expect($registro->estado)->toBe(EstadoComprobante::NoAutorizado)
        ->and($claveOriginal)->toMatch('/^\d{49}$/');

    $respuesta = $this->postJson(
        route('api.v1.comprobantes.reintentar', $registro),
        golden_payload('factura'), // payload "corregido"
    );

    $respuesta->assertSuccessful()
        ->assertJsonPath('emitido', true)
        ->assertJsonPath('id', $registro->uuid)          // mismo registro
        ->assertJsonPath('claveAcceso', $claveOriginal); // misma clave

    $registro->refresh();
    expect($registro->estado)->toBe(EstadoComprobante::Autorizado)
        ->and($registro->clave_acceso)->toBe($claveOriginal)
        ->and((string) $this->gateway->claveConsultada)->toBe($claveOriginal);

    // no se creó un registro nuevo: el reintento no consume cuota
    expect(Comprobante::count())->toBe(1);
});

it('rechaza reintentar si cambió un componente de la clave (secuencial)', function () {
    $registro = emision_rechazada();

    $payload = golden_payload('factura');
    $payload['factura']['infoTributaria']['secuencial'] = '000009999';

    $this->postJson(route('api.v1.comprobantes.reintentar', $registro), $payload)
        ->assertUnprocessable();

    // el registro no quedó autorizado: el reintento fue rechazado antes de emitir
    expect($registro->refresh()->estado)->not->toBe(EstadoComprobante::Autorizado);
});

it('responde 409 si el comprobante no está en un estado reintentable', function () {
    $registro = Comprobante::factory()->autorizado()->create([
        'contribuyente_id' => $this->contribuyente->id,
    ]);

    $this->postJson(route('api.v1.comprobantes.reintentar', $registro), golden_payload('factura'))
        ->assertStatus(409);
});

it('responde 404 para un comprobante ajeno', function () {
    $ajeno = Comprobante::factory()->create([
        'estado' => EstadoComprobante::Devuelto,
    ]);

    $this->postJson(route('api.v1.comprobantes.reintentar', $ajeno), golden_payload('factura'))
        ->assertNotFound();
});

it('rechaza un payload de tipo distinto al del registro', function () {
    $registro = emision_rechazada(); // es una factura

    $this->postJson(
        route('api.v1.comprobantes.reintentar', $registro),
        golden_payload('notaCredito'),
    )->assertUnprocessable()->assertJsonValidationErrors(['tipo']);
});

it('reintenta en modo asíncrono transportando la clave original', function () {
    $registro = emision_rechazada();
    Queue::fake();

    $this->postJson(
        route('api.v1.comprobantes.reintentar', [$registro, 'async' => 1]),
        golden_payload('factura'),
    )->assertStatus(202)->assertJsonPath('data.estado', 'pendiente');

    Queue::assertPushed(
        ProcesarComprobanteJob::class,
        fn (ProcesarComprobanteJob $job): bool => $job->claveAcceso === $registro->clave_acceso,
    );
});

it('un registro fallido sin clave simplemente recibe una nueva', function () {
    $registro = Comprobante::factory()->create([
        'contribuyente_id' => $this->contribuyente->id,
        'estado' => EstadoComprobante::Fallido,
        'clave_acceso' => null,
    ]);

    $respuesta = $this->postJson(
        route('api.v1.comprobantes.reintentar', $registro),
        golden_payload('factura'),
    );

    $respuesta->assertSuccessful();
    expect($respuesta->json('claveAcceso'))->toMatch('/^\d{49}$/');
});
