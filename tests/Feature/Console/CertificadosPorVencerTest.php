<?php

use App\Jobs\EnviarWebhookJob;
use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEntrega;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

it('publica certificado.por_vencer en los umbrales configurados', function () {
    $contribuyente = Contribuyente::factory()
        ->conCertificado()
        ->create(['certificado_valido_hasta' => today()->addDays(7)->setTime(14, 30)]);
    WebhookEndpoint::factory()->deContribuyente($contribuyente)->create();

    $this->artisan('webhooks:certificados-por-vencer')
        ->expectsOutputToContain('notificados: 1')
        ->assertSuccessful();

    Queue::assertPushed(EnviarWebhookJob::class, 1);

    $entrega = WebhookEntrega::first();
    expect($entrega->evento)->toBe('certificado.por_vencer')
        ->and($entrega->payload['datos']['diasRestantes'])->toBe(7)
        ->and($entrega->payload['contribuyente']['id'])->toBe($contribuyente->uuid);
});

it('no notifica certificados fuera de los umbrales', function () {
    $contribuyente = Contribuyente::factory()
        ->conCertificado()
        ->create(['certificado_valido_hasta' => today()->addDays(20)]);
    WebhookEndpoint::factory()->deContribuyente($contribuyente)->create();

    $this->artisan('webhooks:certificados-por-vencer')
        ->expectsOutputToContain('notificados: 0')
        ->assertSuccessful();

    expect(WebhookEntrega::count())->toBe(0);
});

it('el partner del contribuyente también recibe el aviso', function () {
    $partner = Partner::factory()->create();
    $contribuyente = contribuyente_gestionado($partner, [
        'certificado_valido_hasta' => today()->addDays(30),
    ]);
    WebhookEndpoint::factory()->dePartner($partner)->create();

    $this->artisan('webhooks:certificados-por-vencer')->assertSuccessful();

    expect(WebhookEntrega::count())->toBe(1);
});
