<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
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
        Gate::define('viewHorizon', function ($user = null) {
            dd($user);
            return in_array(optional($user)->email, [
                'jago86@gmail.com',
            ]);
        });
    }

    protected function authorization(): void                                                                                                             {                                                                                                                                                        $this->gate();

        Horizon::auth(function (Request $request): bool {
            if (app()->environment('local')) {
                return true;
            }

            $usuario = $request->user('partner-web') ?? $request->user();

            return $usuario !== null && Gate::forUser($usuario)->allows('viewHorizon');
        });
    }
}
