<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Emprendedor',
            'slug' => $this->faker->unique()->slug(2),
            'cuota_mensual' => 100,
            'limite_por_minuto' => 60,
        ];
    }

    public function conCuota(int $cuotaMensual): static
    {
        return $this->state(['cuota_mensual' => $cuotaMensual]);
    }
}
