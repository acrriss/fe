<?php

namespace App\Providers;

use App\Sri\Contracts\RideGenerator;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\JarXmlSigner;
use App\Sri\Firma\XadesXmlSigner;
use App\Sri\Gateways\SoapSriGateway;
use App\Sri\Ride\DompdfRideGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SriGateway::class, SoapSriGateway::class);
        $this->app->bind(RideGenerator::class, DompdfRideGenerator::class);

        $this->app->bind(XmlSigner::class, function (Application $app): XmlSigner {
            return match (config()->string('sri.firmador.driver')) {
                'nativo' => $app->make(XadesXmlSigner::class),
                default => $app->make(JarXmlSigner::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La emisión llama a servicios externos lentos (SOAP del SRI): el
        // límite por minuto viene del plan del contribuyente (60 por
        // defecto) y se aplica por contribuyente, no por usuario.
        RateLimiter::for('api', function (Request $request) {
            $contribuyente = $request->user()?->contribuyente;

            return Limit::perMinute($contribuyente->plan->limite_por_minuto ?? 60)
                ->by((string) ($contribuyente->id ?? $request->ip()));
        });
    }
}
