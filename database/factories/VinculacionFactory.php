<?php

namespace Database\Factories;

use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\Vinculacion;
use App\Sri\Enums\EstadoVinculacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vinculacion>
 */
class VinculacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'contribuyente_id' => Contribuyente::factory(),
            'estado' => EstadoVinculacion::Pendiente,
        ];
    }

    public function aprobada(): static
    {
        return $this->state(['estado' => EstadoVinculacion::Aprobada, 'resuelta_en' => now()]);
    }

    public function rechazada(): static
    {
        return $this->state(['estado' => EstadoVinculacion::Rechazada, 'resuelta_en' => now()]);
    }
}
