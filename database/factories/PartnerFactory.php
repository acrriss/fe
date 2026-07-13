<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->company();

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'cuota_mensual' => null,
            'limite_por_minuto' => 60,
        ];
    }

    public function conCuota(int $cuotaMensual): static
    {
        return $this->state(['cuota_mensual' => $cuotaMensual]);
    }
}
