<?php

namespace App\Console\Commands;

use App\Models\Partner;
use Illuminate\Console\Command;

/**
 * Emite un token de API adicional para un partner existente (rotación de
 * credenciales). Con --revocar se invalidan antes todos los anteriores.
 */
class PartnerTokenCommand extends Command
{
    protected $signature = 'partner:token
        {slug : Slug del partner}
        {--nombre=rotacion : Nombre del token}
        {--revocar : Revoca todos los tokens anteriores del partner}';

    protected $description = 'Emite un nuevo token de API para un partner (opcionalmente revocando los anteriores)';

    public function handle(): int
    {
        $partner = Partner::where('slug', $this->argument('slug'))->first();

        if ($partner === null) {
            $this->error("No existe un partner con el slug [{$this->argument('slug')}].");

            return self::FAILURE;
        }

        if ($this->option('revocar')) {
            $revocados = $partner->tokens()->count();
            $partner->tokens()->delete();
            $this->info("Tokens revocados: {$revocados}.");
        }

        $token = $partner->createToken((string) $this->option('nombre'))->plainTextToken;

        $this->line('Token de API (guárdalo ahora, no se puede volver a mostrar):');
        $this->line($token);

        return self::SUCCESS;
    }
}
