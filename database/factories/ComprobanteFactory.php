<?php

namespace Database\Factories;

use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Sri\Enums\Ambiente;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Enums\TipoComprobante;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comprobante>
 */
class ComprobanteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contribuyente_id' => Contribuyente::factory(),
            'tipo' => TipoComprobante::Factura,
            'estado' => EstadoComprobante::Pendiente,
            'ambiente' => Ambiente::Pruebas,
            'ruc' => '0922596788001',
            'razon_social' => $this->faker->company(),
            'secuencial' => str_pad((string) $this->faker->unique()->numberBetween(1, 999_999_999), 9, '0', STR_PAD_LEFT),
            'emitido_en' => CarbonImmutable::now()->toDateString(),
        ];
    }

    public function autorizado(): static
    {
        return $this->state(function (array $attributes): array {
            $clave = (string) ClaveAcceso::generar(
                fechaEmision: CarbonImmutable::now(),
                tipoComprobante: $attributes['tipo'] ?? TipoComprobante::Factura,
                ruc: Ruc::fromString($attributes['ruc'] ?? '0922596788001'),
                ambiente: $attributes['ambiente'] ?? Ambiente::Pruebas,
                establecimiento: '001',
                puntoEmision: '001',
                secuencial: Secuencial::fromString($attributes['secuencial'] ?? '000000001'),
                codigoNumerico: CodigoNumerico::aleatorio(),
            );

            return [
                'estado' => EstadoComprobante::Autorizado,
                'clave_acceso' => $clave,
                'numero_autorizacion' => $clave,
                'autorizado_en' => now(),
            ];
        });
    }
}
