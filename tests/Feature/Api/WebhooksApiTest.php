<?php

use App\Models\Contribuyente;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEntrega;

describe('gestión de webhooks del contribuyente (§11)', function () {
    it('crea un endpoint y muestra el secreto una sola vez', function () {
        $contribuyente = actuar_como_contribuyente();

        $respuesta = $this->postJson(route('api.v1.webhooks.crear'), [
            'url' => 'https://mi-sistema.test/webhooks',
            'eventos' => ['comprobante.autorizado', 'comprobante.devuelto'],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.url', 'https://mi-sistema.test/webhooks')
            ->assertJsonPath('data.eventos', ['comprobante.autorizado', 'comprobante.devuelto'])
            ->assertJsonPath('data.activo', true);

        expect($respuesta->json('secreto'))->toStartWith('whsec_');

        $endpoint = WebhookEndpoint::where('uuid', $respuesta->json('data.id'))->first();
        expect($endpoint->suscriptor)->toBeInstanceOf(Contribuyente::class)
            ->and($endpoint->suscriptor->id)->toBe($contribuyente->id)
            ->and($endpoint->secreto)->toBe($respuesta->json('secreto'));

        // el listado nunca vuelve a mostrar el secreto
        $this->getJson(route('api.v1.webhooks.index'))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.secreto')
            ->assertJsonMissingPath('secreto');
    });

    it('valida el payload: :dataset', function (array $payload) {
        actuar_como_contribuyente();

        $this->postJson(route('api.v1.webhooks.crear'), $payload)
            ->assertUnprocessable();
    })->with([
        'sin url' => [['eventos' => ['comprobante.autorizado']]],
        'url no http(s)' => [['url' => 'ftp://x.test', 'eventos' => ['comprobante.autorizado']]],
        'sin eventos' => [['url' => 'https://x.test', 'eventos' => []]],
        'evento desconocido' => [['url' => 'https://x.test', 'eventos' => ['comprobante.magico']]],
    ]);

    it('no lista ni borra endpoints ajenos', function () {
        $ajeno = WebhookEndpoint::factory()->create(); // de otro contribuyente
        actuar_como_contribuyente();

        $this->getJson(route('api.v1.webhooks.index'))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data');

        $this->deleteJson(route('api.v1.webhooks.eliminar', $ajeno->uuid))
            ->assertNotFound();

        expect(WebhookEndpoint::count())->toBe(1);
    });

    it('elimina un endpoint propio', function () {
        $contribuyente = actuar_como_contribuyente();
        $endpoint = WebhookEndpoint::factory()->deContribuyente($contribuyente)->create();

        $this->deleteJson(route('api.v1.webhooks.eliminar', $endpoint->uuid))
            ->assertNoContent();

        expect(WebhookEndpoint::count())->toBe(0);
    });

    it('lista las entregas de un endpoint propio', function () {
        $contribuyente = actuar_como_contribuyente();
        $endpoint = WebhookEndpoint::factory()->deContribuyente($contribuyente)->create();
        WebhookEntrega::factory()->entregada()->create(['webhook_endpoint_id' => $endpoint->id]);
        WebhookEntrega::factory()->create(['webhook_endpoint_id' => $endpoint->id]);

        $this->getJson(route('api.v1.webhooks.entregas', $endpoint->uuid))
            ->assertSuccessful()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'evento', 'estado', 'intentos', 'payload']]]);
    });

    it('un partner gestiona webhooks de su contribuyente con X-Contribuyente', function () {
        $partner = actuar_como_partner();
        $gestionado = contribuyente_gestionado($partner);

        $respuesta = $this->postJson(
            route('api.v1.webhooks.crear'),
            ['url' => 'https://pos.test/webhooks', 'eventos' => ['comprobante.autorizado']],
            ['X-Contribuyente' => $gestionado->uuid],
        );

        $respuesta->assertCreated();

        $endpoint = WebhookEndpoint::where('uuid', $respuesta->json('data.id'))->first();
        expect($endpoint->suscriptor)->toBeInstanceOf(Contribuyente::class)
            ->and($endpoint->suscriptor->id)->toBe($gestionado->id);
    });
});

describe('gestión de webhooks del partner', function () {
    it('crea y lista endpoints a nivel partner', function () {
        $partner = actuar_como_partner();

        $respuesta = $this->postJson(route('api.partner.v1.webhooks.crear'), [
            'url' => 'https://pos.test/webhooks',
            'eventos' => ['comprobante.autorizado', 'certificado.por_vencer'],
        ]);

        $respuesta->assertCreated();
        expect($respuesta->json('secreto'))->toStartWith('whsec_')
            ->and(WebhookEndpoint::where('uuid', $respuesta->json('data.id'))->first()->suscriptor->id)
            ->toBe($partner->id);

        $this->getJson(route('api.partner.v1.webhooks.index'))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');
    });

    it('rechaza tokens de usuario directo (403)', function () {
        actuar_como_contribuyente();

        $this->postJson(route('api.partner.v1.webhooks.crear'), [
            'url' => 'https://x.test',
            'eventos' => ['comprobante.autorizado'],
        ])->assertForbidden();
    });

    it('no ve endpoints de otros partners ni de contribuyentes', function () {
        WebhookEndpoint::factory()->create(); // de un contribuyente
        actuar_como_partner();

        $this->getJson(route('api.partner.v1.webhooks.index'))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data');
    });
});
