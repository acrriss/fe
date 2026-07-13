<?php

use App\Jobs\EnviarWebhookJob;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEntrega;
use App\Sri\Enums\EstadoEntregaWebhook;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

it('entrega el evento firmado y marca la entrega como entregada', function () {
    Http::fake(['integrador.test/*' => Http::response('', 200)]);

    $entrega = WebhookEntrega::factory()->create();
    $secreto = $entrega->endpoint->secreto;

    new EnviarWebhookJob($entrega)->handle();

    Http::assertSent(function (ClientRequest $request) use ($entrega, $secreto): bool {
        $timestamp = $request->header('X-Firma-Timestamp')[0];
        $firmaEsperada = 'v1='.hash_hmac('sha256', "{$timestamp}.{$request->body()}", $secreto);

        return $request->url() === $entrega->endpoint->url
            && $request->header('X-Evento')[0] === $entrega->evento
            && $request->header('X-Entrega')[0] === $entrega->uuid
            && $request->header('X-Firma')[0] === $firmaEsperada
            && $request->header('Content-Type')[0] === 'application/json';
    });

    $entrega->refresh();
    expect($entrega->estado)->toBe(EstadoEntregaWebhook::Entregada)
        ->and($entrega->codigo_http)->toBe(200)
        ->and($entrega->intentos)->toBe(1)
        ->and($entrega->entregado_en)->not->toBeNull();
});

it('registra el intento y relanza cuando el receptor no acepta (para reintentar)', function () {
    Http::fake(['integrador.test/*' => Http::response('error', 500)]);

    $entrega = WebhookEntrega::factory()->create();

    expect(fn () => new EnviarWebhookJob($entrega)->handle())
        ->toThrow(RuntimeException::class);

    $entrega->refresh();
    expect($entrega->estado)->toBe(EstadoEntregaWebhook::Pendiente) // aún reintentable
        ->and($entrega->codigo_http)->toBe(500)
        ->and($entrega->intentos)->toBe(1)
        ->and($entrega->error)->toContain('500');
});

it('failed() marca la entrega como fallida al agotar los reintentos', function () {
    $entrega = WebhookEntrega::factory()->create();

    new EnviarWebhookJob($entrega)->failed(new RuntimeException('agotado'));

    expect($entrega->refresh()->estado)->toBe(EstadoEntregaWebhook::Fallida);
});

it('no llama al receptor si el endpoint fue desactivado', function () {
    Http::fake();

    $endpoint = WebhookEndpoint::factory()->inactivo()->create();
    $entrega = WebhookEntrega::factory()->create(['webhook_endpoint_id' => $endpoint->id]);

    new EnviarWebhookJob($entrega)->handle();

    Http::assertNothingSent();
    expect($entrega->refresh()->estado)->toBe(EstadoEntregaWebhook::Fallida);
});
