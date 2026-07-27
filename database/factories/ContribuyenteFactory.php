<?php

namespace Database\Factories;

use App\Models\Contribuyente;
use App\Sri\Support\ValidadorIdentificacion;
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
            'ruc' => $this->rucValido(),
            'razon_social' => $this->faker->company(),
            'nombre_comercial' => $this->faker->company(),
            'dir_matriz' => $this->faker->address(),
        ];
    }

    /**
     * RUC de sociedad privada con dígito verificador correcto: desde que
     * `Ruc::fromString` valida módulo 11, un RUC al azar ya no sirve.
     */
    private function rucValido(): string
    {
        do {
            $base = sprintf('%02d9%06d', $this->faker->numberBetween(1, 24), $this->faker->unique()->numberBetween(0, 999_999));
            $digito = ValidadorIdentificacion::digitoVerificadorModulo11($base, [4, 3, 2, 7, 6, 5, 4, 3, 2]);
        } while ($digito === null);

        return $base.$digito.'001';
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
