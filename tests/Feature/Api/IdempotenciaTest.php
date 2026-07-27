<?php

use App\Models\ClaveIdempotencia;
use App\Models\Comprobante;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();
    $this->contribuyente = actuar_como_contribuyente();
});

function emitir_con_clave(string $clave, ?array $payload = null)
{
    return test()->postJson(
        route('api.v1.comprobantes.emitir'),
        $payload ?? golden_payload('factura'),
        ['Idempotency-Key' => $clave],
    );
}

describe('idempotencia de emisión (§11)', function () {
    it('reproduce la respuesta original ante la misma clave, sin emitir dos veces', function () {
        $primera = emitir_con_clave('venta-8842');
        $segunda = emitir_con_clave('venta-8842');

        $primera->assertSuccessful();
        $segunda->assertSuccessful()
            ->assertHeader('Idempotency-Replayed', 'true');

        // respuesta idéntica byte a byte (mismo id, misma clave de acceso)
        expect($segunda->getContent())->toBe($primera->getContent())
            ->and(Comprobante::count())->toBe(1);
    });

    it('responde 409 si la clave se reutiliza con otro payload', function () {
        emitir_con_clave('venta-8842')->assertSuccessful();

        $otroPayload = golden_payload('factura');
        $otroPayload['factura']['infoTributaria']['secuencial'] = '000000099';

        emitir_con_clave('venta-8842', $otroPayload)->assertStatus(409);

        expect(Comprobante::count())->toBe(1);
    });

    it('claves distintas emiten por separado', function () {
        emitir_con_clave('venta-1')->assertSuccessful();
        emitir_con_clave('venta-2')->assertSuccessful();

        expect(Comprobante::count())->toBe(2);
    });

    it('también reproduce desenlaces de negocio fallidos (422 devuelto)', function () {
        $this->gateway->devolverComprobantes();

        $primera = emitir_con_clave('venta-8842');
        $segunda = emitir_con_clave('venta-8842');

        $primera->assertUnprocessable();
        $segunda->assertUnprocessable()
            ->assertHeader('Idempotency-Replayed', 'true');

        expect($segunda->getContent())->toBe($primera->getContent())
            ->and(Comprobante::count())->toBe(1); // el reintento no creó otro registro
    });

    it('la modalidad asíncrona reproduce el mismo 202 (misma emisión)', function () {
        $ruta = route('api.v1.comprobantes.emitir', ['async' => 1]);

        $primera = $this->postJson($ruta, golden_payload('factura'), ['Idempotency-Key' => 'venta-8842']);
        $segunda = $this->postJson($ruta, golden_payload('factura'), ['Idempotency-Key' => 'venta-8842']);

        $primera->assertStatus(202);
        $segunda->assertStatus(202)->assertHeader('Idempotency-Replayed', 'true');

        expect($segunda->json('data.id'))->toBe($primera->json('data.id'))
            ->and(Comprobante::count())->toBe(1);
    });

    it('la huella incluye la URI: la misma clave con ?async=1 distinto es conflicto', function () {
        emitir_con_clave('venta-8842')->assertSuccessful();

        $this->postJson(
            route('api.v1.comprobantes.emitir', ['async' => 1]),
            golden_payload('factura'),
            ['Idempotency-Key' => 'venta-8842'],
        )->assertStatus(409);
    });

    it('la clave es por contribuyente: otro contribuyente puede usar la misma', function () {
        emitir_con_clave('venta-8842')->assertSuccessful();

        // otro contribuyente con su propio RUC y payload
        $this->contribuyente = actuar_como_contribuyente(atributos: ['ruc' => '0992479248001']);
        $payload = golden_payload('factura');
        $payload['factura']['infoTributaria']['ruc'] = '0992479248001';

        emitir_con_clave('venta-8842', $payload)->assertSuccessful();

        expect(Comprobante::count())->toBe(2);
    });

    it('responde 409 mientras la petición original sigue en curso', function () {
        ClaveIdempotencia::factory()->create([
            'contribuyente_id' => $this->contribuyente->id,
            'clave' => 'venta-8842',
            'huella' => 'x', // ni siquiera se compara: en curso gana
        ]);

        emitir_con_clave('venta-8842')->assertStatus(409);
    });

    it('una clave en curso huérfana (proceso muerto) se libera pasada la ventana', function () {
        ClaveIdempotencia::factory()->create([
            'contribuyente_id' => $this->contribuyente->id,
            'clave' => 'venta-8842',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        emitir_con_clave('venta-8842')->assertSuccessful();

        expect(Comprobante::count())->toBe(1);
    });

    it('una clave expirada puede reutilizarse con otro payload', function () {
        ClaveIdempotencia::factory()->respondida()->create([
            'contribuyente_id' => $this->contribuyente->id,
            'clave' => 'venta-8842',
            'huella' => 'huella-vieja',
            'created_at' => now()->subHours(25),
        ]);

        emitir_con_clave('venta-8842')->assertSuccessful();
    });

    it('sin cabecera no interviene: dos POST iguales emiten dos veces', function () {
        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))->assertSuccessful();
        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))->assertSuccessful();

        expect(Comprobante::count())->toBe(2)
            ->and(ClaveIdempotencia::count())->toBe(0);
    });

    it('rechaza claves de más de 255 caracteres', function () {
        emitir_con_clave(str_repeat('x', 256))->assertUnprocessable();
    });

    it('el reintento de comprobantes también acepta la cabecera', function () {
        $this->gateway->devolverComprobantes();
        $emision = emitir_con_clave('venta-1');
        $emision->assertUnprocessable();

        $this->gateway = new FakeSriGateway;
        $this->app->instance(SriGateway::class, $this->gateway);

        $ruta = route('api.v1.comprobantes.reintentar', $emision->json('id'));

        $primera = $this->postJson($ruta, golden_payload('factura'), ['Idempotency-Key' => 'reintento-1']);
        $segunda = $this->postJson($ruta, golden_payload('factura'), ['Idempotency-Key' => 'reintento-1']);

        $primera->assertSuccessful();
        $segunda->assertSuccessful()->assertHeader('Idempotency-Replayed', 'true');

        expect($segunda->getContent())->toBe($primera->getContent());
    });

    it('model:prune poda solo las claves expiradas', function () {
        ClaveIdempotencia::factory()->respondida()->create(['created_at' => now()->subHours(25)]);
        ClaveIdempotencia::factory()->respondida()->create(['created_at' => now()->subHours(1)]);

        $this->artisan('model:prune', ['--model' => ClaveIdempotencia::class])
            ->assertSuccessful();

        expect(ClaveIdempotencia::count())->toBe(1);
    });
});
