<?php

namespace App\Providers;

use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\JarXmlSigner;
use App\Sri\Gateways\SoapSriGateway;
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
        //
    }
}
