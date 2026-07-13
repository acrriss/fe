<?php

namespace Database\Factories;

use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\WebhookEndpoint;
use App\Sri\Enums\EventoWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'suscriptor_type' => Contribuyente::class,
            'suscriptor_id' => Contribuyente::factory(),
            'url' => 'https://integrador.test/webhooks/sri',
            'secreto' => WebhookEndpoint::generarSecreto(),
            'eventos' => EventoWebhook::valores(),
            'activo' => true,
        ];
    }

    public function deContribuyente(Contribuyente $contribuyente): static
    {
        return $this->state([
            'suscriptor_type' => Contribuyente::class,
            'suscriptor_id' => $contribuyente->id,
        ]);
    }

    public function dePartner(Partner $partner): static
    {
        return $this->state([
            'suscriptor_type' => Partner::class,
            'suscriptor_id' => $partner->id,
        ]);
    }

    /**
     * @param  array<int, EventoWebhook>  $eventos
     */
    public function suscritoA(EventoWebhook ...$eventos): static
    {
        return $this->state(['eventos' => array_column($eventos, 'value')]);
    }

    public function inactivo(): static
    {
        return $this->state(['activo' => false]);
    }
}
