<?php

namespace App\Console\Commands;

use App\Models\Partner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Alta de un partner/plataforma integradora (§11). No hay autoservicio:
 * un partner es una relación comercial que se abre a mano. Imprime el
 * token inicial (solo visible aquí, Sanctum guarda el hash).
 */
class PartnerCrearCommand extends Command
{
    protected $signature = 'partner:crear
        {nombre : Nombre del partner}
        {--slug= : Identificador corto (por defecto, el nombre en kebab-case)}
        {--cuota= : Cuota mensual pool de comprobantes (sin valor = ilimitada)}
        {--limite=60 : Límite de peticiones por minuto}';

    protected $description = 'Crea un partner integrador y emite su token de API inicial';

    public function handle(): int
    {
        $nombre = $this->argument('nombre');
        $slug = $this->option('slug') ?? Str::slug($nombre);

        if (Partner::where('slug', $slug)->exists()) {
            $this->error("Ya existe un partner con el slug [{$slug}].");

            return self::FAILURE;
        }

        $partner = Partner::create([
            'nombre' => $nombre,
            'slug' => $slug,
            'cuota_mensual' => $this->option('cuota') !== null ? (int) $this->option('cuota') : null,
            'limite_por_minuto' => (int) $this->option('limite'),
        ]);

        $token = $partner->createToken('inicial')->plainTextToken;

        $this->info("Partner [{$partner->nombre}] creado.");
        $this->table(
            ['slug', 'uuid', 'cuota mensual', 'límite/min'],
            [[$partner->slug, $partner->uuid, $partner->cuota_mensual ?? 'ilimitada', $partner->limite_por_minuto]],
        );
        $this->line('Token de API (guárdalo ahora, no se puede volver a mostrar):');
        $this->line($token);

        return self::SUCCESS;
    }
}
