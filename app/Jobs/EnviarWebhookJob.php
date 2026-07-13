<?php

namespace App\Jobs;

use App\Models\WebhookEntrega;
use App\Sri\Enums\EstadoEntregaWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Envío de una entrega de webhook (§11): POST JSON firmado con el secreto
 * del endpoint. La firma cubre `{timestamp}.{cuerpo}` (HMAC-SHA256) para
 * que el receptor verifique autenticidad y descarte reenvíos viejos:
 *
 *   X-Firma: v1={hmac_sha256(secreto, timestamp + "." + cuerpo)}
 *   X-Firma-Timestamp: {unix}
 *
 * Cada intento queda registrado en la entrega; agotados los reintentos
 * (backoff exponencial), la entrega se marca fallida.
 */
class EnviarWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 1800, 7200];

    public int $timeout = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public WebhookEntrega $entrega) {}

    public function handle(): void
    {
        $endpoint = $this->entrega->endpoint;

        if ($endpoint === null || ! $endpoint->activo) {
            $this->entrega->update([
                'estado' => EstadoEntregaWebhook::Fallida,
                'error' => 'El endpoint ya no existe o está inactivo.',
            ]);

            return;
        }

        $cuerpo = (string) json_encode($this->entrega->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();
        $firma = hash_hmac('sha256', "{$timestamp}.{$cuerpo}", $endpoint->secreto);

        $this->entrega->increment('intentos');

        try {
            $respuesta = Http::timeout(config()->integer('sri.webhooks.timeout', 10))
                ->withHeaders([
                    'X-Evento' => $this->entrega->evento,
                    'X-Entrega' => $this->entrega->uuid,
                    'X-Firma' => "v1={$firma}",
                    'X-Firma-Timestamp' => (string) $timestamp,
                ])
                ->withBody($cuerpo, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException $excepcion) {
            $this->entrega->update(['codigo_http' => null, 'error' => $excepcion->getMessage()]);

            throw $excepcion; // reintento con backoff
        }

        if ($respuesta->successful()) {
            $this->entrega->update([
                'estado' => EstadoEntregaWebhook::Entregada,
                'codigo_http' => $respuesta->status(),
                'error' => null,
                'entregado_en' => now(),
            ]);

            return;
        }

        $this->entrega->update([
            'codigo_http' => $respuesta->status(),
            'error' => "El receptor respondió {$respuesta->status()}.",
        ]);

        throw new RuntimeException("Webhook no aceptado (HTTP {$respuesta->status()}); se reintentará.");
    }

    /**
     * Agotados los reintentos: la entrega queda fallida (consultable en el
     * registro de entregas).
     */
    public function failed(?Throwable $excepcion): void
    {
        $this->entrega->update(['estado' => EstadoEntregaWebhook::Fallida]);
    }
}
