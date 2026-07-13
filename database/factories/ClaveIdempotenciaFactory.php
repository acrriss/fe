<?php

namespace Database\Factories;

use App\Models\ClaveIdempotencia;
use App\Models\Contribuyente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaveIdempotencia>
 */
class ClaveIdempotenciaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contribuyente_id' => Contribuyente::factory(),
            'clave' => $this->faker->uuid(),
            'huella' => hash('sha256', $this->faker->sentence()),
            'codigo_http' => null,
            'respuesta' => null,
        ];
    }

    public function respondida(int $codigoHttp = 200): static
    {
        return $this->state([
            'codigo_http' => $codigoHttp,
            'respuesta' => (string) json_encode(['emitido' => true, 'id' => $this->faker->uuid()]),
        ]);
    }
}
