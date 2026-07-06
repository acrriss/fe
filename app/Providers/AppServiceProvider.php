<?php

namespace App\Providers;

use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\JarXmlSigner;
use App\Sri\Gateways\SoapSriGateway;
use Illuminate\Cache\RateLimiting\Limit;
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
        $this->app->bind(XmlSigner::class, JarXmlSigner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // La emisión llama a servicios externos lentos (SOAP del SRI):
        // el límite por minuto protege tanto al servicio como al SRI.
        // En la fase 6 el límite dependerá del plan del usuario.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()->id ?? $request->ip());
        });
    }
}
