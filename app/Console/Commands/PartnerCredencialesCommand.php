<?php

namespace App\Console\Commands;

use App\Models\Partner;
use Illuminate\Console\Command;

/**
 * Asigna (o reemplaza) las credenciales del panel de partner (§11, 7d).
 * Sin credenciales, un partner solo opera por API/CLI.
 */
class PartnerCredencialesCommand extends Command
{
    protected $signature = 'partner:credenciales
        {slug : Slug del partner}
        {email : Email de acceso al panel}
        {--password= : Contraseña (si se omite, se pide de forma oculta)}';

    protected $description = 'Asigna las credenciales de acceso al panel de un partner';

    public function handle(): int
    {
        $partner = Partner::where('slug', $this->argument('slug'))->first();

        if ($partner === null) {
            $this->error("No existe un partner con el slug [{$this->argument('slug')}].");

            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Contraseña del panel');

        if (! is_string($password) || strlen($password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $partner->update([
            'email' => $this->argument('email'),
            'password' => $password, // cast hashed
        ]);

        $this->info("Credenciales del panel asignadas a [{$partner->nombre}]. Acceso en: ".route('partner.login'));

        return self::SUCCESS;
    }
}
