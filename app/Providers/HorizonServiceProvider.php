<?php

namespace App\Providers;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Quién puede abrir Horizon fuera de local.
     *
     * @var list<string>
     */
    private const CORREOS_AUTORIZADOS = [
        'jago86@gmail.com',
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Los dos guards que desembocan aquí: el panel (User) y el panel de
        // partners (Partner). Tipar el closure es además lo que le dice al
        // Gate que un invitado no llega a evaluarse.
        Gate::define('viewHorizon', function (User|Partner $usuario): bool {
            return in_array($usuario->email, self::CORREOS_AUTORIZADOS, true);
        });
    }

    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(function (Request $request): bool {
            if (app()->environment('local')) {
                return true;
            }

            $usuario = $request->user('partner-web') ?? $request->user();

            return $usuario !== null && Gate::forUser($usuario)->allows('viewHorizon');
        });
    }
}
