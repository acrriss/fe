<?php

use App\Jobs\EnviarWebhookJob;
use App\Jobs\ProcesarComprobanteJob;
use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEntrega;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Enums\EventoWebhook;
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
    Queue::fake();
});

describe('publicación en el ciclo de emisión (§11)', function () {
    it('la emisión autorizada notifica a los endpoints del contribuyente y de su partner', function () {
        $partner = actuar_como_partner();
        $gestionado = contribuyente_gestionado($partner);

        $delContribuyente = WebhookEndpoint::factory()->deContribuyente($gestionado)->create();
        $delPartner = WebhookEndpoint::factory()->dePartner($partner)->create();

        $respuesta = $this->postJson(
            route('api.v1.comprobantes.emitir'),
            golden_payload('factura') + ['external_id' => 'venta-1'],
            ['X-Contribuyente' => $gestionado->uuid],
        );

        $respuesta->assertSuccessful();

        Queue::assertPushed(EnviarWebhookJob::class, 2);

        $entregas = WebhookEntrega::all();
        expect($entregas)->toHaveCount(2)
            ->and($entregas->pluck('webhook_endpoint_id')->sort()->values()->all())
            ->toBe(collect([$delContribuyente->id, $delPartner->id])->sort()->values()->all())
            ->and($entregas->first()->evento)->toBe('comprobante.autorizado');

        $payload = $entregas->first()->payload;
        expect($payload['contribuyente']['id'])->toBe($gestionado->uuid)
            ->and($payload['datos']['id'])->toBe($respuesta->json('id'))
            ->and($payload['datos']['externalId'])->toBe('venta-1')
            ->and($payload['datos'])->not->toHaveKey('xmlFirmado');
    });

    it('la emisión devuelta publica comprobante.devuelto', function () {
        $contribuyente = actuar_como_contribuyente();
        WebhookEndpoint::factory()->deContribuyente($contribuyente)->create();
        $this->gateway->devolverComprobantes();

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertUnprocessable();

        expect(WebhookEntrega::first()->evento)->toBe('comprobante.devuelto');
    });

    it('no notifica a endpoints inactivos ni a no suscritos al evento', function () {
        $contribuyente = actuar_como_contribuyente();
        WebhookEndpoint::factory()->deContribuyente($contribuyente)->inactivo()->create();
        WebhookEndpoint::factory()->deContribuyente($contribuyente)
            ->suscritoA(EventoWebhook::ComprobanteDevuelto)
            ->create();

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertSuccessful();

        Queue::assertNotPushed(EnviarWebhookJob::class);
        expect(WebhookEntrega::count())->toBe(0);
    });

    it('el fallo técnico definitivo del job asíncrono publica comprobante.fallido', function () {
        $contribuyente = Contribuyente::factory()->conCertificado()->create();
        WebhookEndpoint::factory()->deContribuyente($contribuyente)->create();
        $registro = Comprobante::factory()->create(['contribuyente_id' => $contribuyente->id]);

        new ProcesarComprobanteJob(
            registro: $registro,
            dataClass: FacturaData::class,
            payloadComprobante: golden_input('factura'),
        )->failed(new RuntimeException('SRI caído'));

        expect(WebhookEntrega::first()->evento)->toBe('comprobante.fallido')
            ->and(WebhookEntrega::first()->payload['datos']['estado'])->toBe('fallido');
    });

    it('sin endpoints suscritos la emisión no crea entregas', function () {
        actuar_como_contribuyente();

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertSuccessful();

        expect(WebhookEntrega::count())->toBe(0);
    });
});
