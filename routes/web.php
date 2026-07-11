<?php

use App\Http\Controllers\Api\V1\DescargarRideController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\Panel;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'panel.inicio' : 'login'));

// Documentación pública de la API (Scalar).
Route::get('docs', [DocsController::class, 'page'])->name('docs');
Route::get('docs/openapi.yaml', [DocsController::class, 'spec'])->name('docs.spec');

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
        Route::put('configuracion/certificado', [Panel\ConfiguracionController::class, 'guardarCertificado'])
            ->name('configuracion.certificado');
        Route::post('configuracion/logo', [Panel\ConfiguracionController::class, 'guardarLogo'])
            ->name('configuracion.logo');
        Route::get('configuracion/logo', [Panel\ConfiguracionController::class, 'mostrarLogo'])
            ->name('configuracion.logo.mostrar');
    });
});
