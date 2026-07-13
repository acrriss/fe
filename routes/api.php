<?php

use App\Http\Controllers\Api\Partner\V1\AprovisionarContribuyenteController;
use App\Http\Controllers\Api\Partner\V1\ListarContribuyentesController;
use App\Http\Controllers\Api\Partner\V1\WebhooksController as PartnerWebhooksController;
use App\Http\Controllers\Api\V1\ConsultarComprobanteController;
use App\Http\Controllers\Api\V1\DescargarRideController;
use App\Http\Controllers\Api\V1\EmitirComprobanteController;
use App\Http\Controllers\Api\V1\EmitirTokenController;
use App\Http\Controllers\Api\V1\GuardarCertificadoController;
use App\Http\Controllers\Api\V1\ListarComprobantesController;
use App\Http\Controllers\Api\V1\ReintentarComprobanteController;
use App\Http\Controllers\Api\V1\WebhooksController;
use App\Http\Middleware\ResolverContribuyente;
use App\Http\Middleware\SoloPartners;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('tokens', EmitirTokenController::class)
        ->name('api.v1.tokens.emitir');

    // Plano de emisión: acepta tokens de usuario directo y de partner
    // (este último actúa on-behalf con la cabecera X-Contribuyente, §11).
    Route::middleware(['auth:sanctum', ResolverContribuyente::class])->group(function () {
        Route::post('comprobantes', EmitirComprobanteController::class)
            ->name('api.v1.comprobantes.emitir');

        Route::get('comprobantes', ListarComprobantesController::class)
            ->name('api.v1.comprobantes.index');

        Route::get('comprobantes/{comprobante}', ConsultarComprobanteController::class)
            ->name('api.v1.comprobantes.mostrar');

        Route::post('comprobantes/{comprobante}/reintentar', ReintentarComprobanteController::class)
            ->name('api.v1.comprobantes.reintentar');

        Route::get('comprobantes/{comprobante}/ride', DescargarRideController::class)
            ->name('api.v1.comprobantes.ride');

        Route::put('contribuyente/certificado', GuardarCertificadoController::class)
            ->name('api.v1.contribuyente.certificado');

        // Webhooks del contribuyente actual (§11)
        Route::get('webhooks', [WebhooksController::class, 'index'])
            ->name('api.v1.webhooks.index');
        Route::post('webhooks', [WebhooksController::class, 'store'])
            ->name('api.v1.webhooks.crear');
        Route::delete('webhooks/{uuid}', [WebhooksController::class, 'destroy'])
            ->name('api.v1.webhooks.eliminar');
        Route::get('webhooks/{uuid}/entregas', [WebhooksController::class, 'entregas'])
            ->name('api.v1.webhooks.entregas');
    });
});

// Plano de gestión de partners (§11): aprovisionar y listar contribuyentes
// gestionados. Solo tokens de partner.
Route::prefix('partner/v1')->middleware(['auth:sanctum', SoloPartners::class])->group(function () {
    Route::post('contribuyentes', AprovisionarContribuyenteController::class)
        ->name('api.partner.v1.contribuyentes.aprovisionar');

    Route::get('contribuyentes', ListarContribuyentesController::class)
        ->name('api.partner.v1.contribuyentes.index');

    // Webhooks del partner: reciben los eventos de todos sus gestionados
    Route::get('webhooks', [PartnerWebhooksController::class, 'index'])
        ->name('api.partner.v1.webhooks.index');
    Route::post('webhooks', [PartnerWebhooksController::class, 'store'])
        ->name('api.partner.v1.webhooks.crear');
    Route::delete('webhooks/{uuid}', [PartnerWebhooksController::class, 'destroy'])
        ->name('api.partner.v1.webhooks.eliminar');
    Route::get('webhooks/{uuid}/entregas', [PartnerWebhooksController::class, 'entregas'])
        ->name('api.partner.v1.webhooks.entregas');
});
