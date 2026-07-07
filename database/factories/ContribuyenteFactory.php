<?php

namespace Database\Factories;

use App\Models\Contribuyente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contribuyente>
 */
class ContribuyenteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruc' => $this->faker->unique()->numerify('#########0001'),
            'razon_social' => $this->faker->company(),
            'nombre_comercial' => $this->faker->company(),
            'dir_matriz' => $this->faker->address(),
        ];
    }

    public function conCertificado(): static
    {
        return $this->state([
            'certificado_p12' => base64_encode('certificado-dummy'),
            'certificado_clave' => 'secreto',
        ]);
    }
}
