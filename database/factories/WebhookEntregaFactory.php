<?php

namespace Database\Factories;

use App\Models\WebhookEndpoint;
use App\Models\WebhookEntrega;
use App\Sri\Enums\EstadoEntregaWebhook;
use App\Sri\Enums\EventoWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEntrega>
 */
class WebhookEntregaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'evento' => EventoWebhook::ComprobanteAutorizado->value,
            'payload' => [
                'evento' => EventoWebhook::ComprobanteAutorizado->value,
                'publicadoEn' => now()->toIso8601String(),
                'contribuyente' => ['id' => fake()->uuid(), 'ruc' => '0922596788001'],
                'datos' => ['id' => fake()->uuid(), 'estado' => 'autorizado'],
            ],
            'estado' => EstadoEntregaWebhook::Pendiente,
            'intentos' => 0,
        ];
    }

    public function entregada(): static
    {
        return $this->state([
            'estado' => EstadoEntregaWebhook::Entregada,
            'intentos' => 1,
            'codigo_http' => 200,
            'entregado_en' => now(),
        ]);
    }
}
