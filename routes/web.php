<?php

use App\Http\Controllers\Api\V1\DescargarRideController;
use App\Http\Controllers\CertificadoHospedadoController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Panel;
use App\Http\Controllers\PartnerPanel;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'panel.inicio' : 'login'));

// Documentación pública de la API (Scalar).
Route::get('docs', [DocsController::class, 'page'])->name('docs');
Route::get('docs/openapi.yaml', [DocsController::class, 'spec'])->name('docs.spec');

// Carga hospedada del certificado (§11, 7d): página pública protegida por
// URL firmada temporal que genera el partner. La firma cubre GET y POST.
Route::get('certificado/{contribuyente:uuid}', [CertificadoHospedadoController::class, 'mostrar'])
    ->middleware('signed')
    ->name('certificado.hospedado');
Route::post('certificado/{contribuyente:uuid}', [CertificadoHospedadoController::class, 'guardar'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('certificado.hospedado.guardar');

Route::middleware('guest')->group(function () {
    Route::get('login', [Panel\Auth\LoginController::class, 'create'])->name('login');
    Route::post('login', [Panel\Auth\LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');

    Route::get('registro', [Panel\Auth\RegistroController::class, 'create'])->name('registro');
    Route::post('registro', [Panel\Auth\RegistroController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('registro.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [Panel\Auth\LoginController::class, 'destroy'])->name('logout');

    Route::prefix('panel')->name('panel.')->group(function () {
        Route::get('/', Panel\InicioController::class)->name('inicio');

        Route::get('comprobantes', Panel\ComprobantesController::class)->name('comprobantes');
        Route::get('comprobantes/{comprobante}/ride', DescargarRideController::class)
            ->name('comprobantes.ride');
        Route::get('comprobantes/{comprobante}/xml', [Panel\ComprobantesController::class, 'descargarXml'])
            ->name('comprobantes.xml');

        Route::get('tokens', [Panel\TokensController::class, 'index'])->name('tokens');
        Route::post('tokens', [Panel\TokensController::class, 'store'])->name('tokens.store');
        Route::delete('tokens/{tokenId}', [Panel\TokensController::class, 'destroy'])->name('tokens.destroy');

        Route::get('configuracion', [Panel\ConfiguracionController::class, 'show'])->name('configuracion');
        Route::put('configuracion', [Panel\ConfiguracionController::class, 'update'])->name('configuracion.update');
        Route::post('vinculaciones/{uuid}/aprobar', [Panel\VinculacionesController::class, 'aprobar'])
            ->name('vinculaciones.aprobar');
        Route::post('vinculaciones/{uuid}/rechazar', [Panel\VinculacionesController::class, 'rechazar'])
            ->name('vinculaciones.rechazar');
        Route::put('configuracion/certificado', [Panel\ConfiguracionController::class, 'guardarCertificado'])
            ->name('configuracion.certificado');
        Route::post('configuracion/logo', [Panel\ConfiguracionController::class, 'guardarLogo'])
            ->name('configuracion.logo');
        Route::get('configuracion/logo', [Panel\ConfiguracionController::class, 'mostrarLogo'])
            ->name('configuracion.logo.mostrar');
    });
});

// Panel de partner (§11, 7d): sesión propia (guard partner-web).
Route::prefix('partner')->name('partner.')->group(function () {
    Route::middleware('guest:partner-web')->group(function () {
        Route::get('login', [PartnerPanel\LoginController::class, 'create'])->name('login');
        Route::post('login', [PartnerPanel\LoginController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('login.store');
    });

    Route::middleware('auth:partner-web')->group(function () {
        Route::post('logout', [PartnerPanel\LoginController::class, 'destroy'])->name('logout');

        Route::get('/', PartnerPanel\InicioController::class)->name('inicio');

        Route::get('contribuyentes', [PartnerPanel\ContribuyentesController::class, 'index'])
            ->name('contribuyentes');
        Route::post('contribuyentes/{uuid}/enlace-certificado', [PartnerPanel\ContribuyentesController::class, 'enlaceCertificado'])
            ->name('contribuyentes.enlace-certificado');

        Route::get('webhooks', [PartnerPanel\WebhooksController::class, 'index'])->name('webhooks');

        Route::get('vinculaciones', [PartnerPanel\VinculacionesController::class, 'index'])
            ->name('vinculaciones');
        Route::post('vinculaciones', [PartnerPanel\VinculacionesController::class, 'store'])
            ->name('vinculaciones.solicitar');

        Route::get('tokens', [PartnerPanel\TokensController::class, 'index'])->name('tokens');
        Route::post('tokens', [PartnerPanel\TokensController::class, 'store'])->name('tokens.store');
        Route::delete('tokens/{tokenId}', [PartnerPanel\TokensController::class, 'destroy'])->name('tokens.destroy');
    });
});
