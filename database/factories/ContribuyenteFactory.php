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
            // el .p12 real de prueba del repo (clave: clave-prueba)
            'certificado_p12' => base64_encode((string) file_get_contents(base_path('tests/Fixtures/certificado-prueba.p12'))),
            'certificado_clave' => 'clave-prueba',
            'certificado_titular' => 'CERTIFICADO DE PRUEBA',
            'certificado_emisor' => 'CERTIFICADO DE PRUEBA',
            'certificado_valido_hasta' => now()->addYears(5),
        ]);
    }
}
