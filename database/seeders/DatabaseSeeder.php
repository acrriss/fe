<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $planes = [
            ['slug' => 'gratis', 'nombre' => 'Gratis', 'cuota_mensual' => 20, 'limite_por_minuto' => 10],
            ['slug' => 'emprendedor', 'nombre' => 'Emprendedor', 'cuota_mensual' => 300, 'limite_por_minuto' => 60],
            ['slug' => 'empresa', 'nombre' => 'Empresa', 'cuota_mensual' => 5000, 'limite_por_minuto' => 300],
        ];

        foreach ($planes as $plan) {
            Plan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
