<?php

use App\Http\Controllers\Api\V1\ConsultarComprobanteController;
use App\Http\Controllers\Api\V1\DescargarRideController;
use App\Http\Controllers\Api\V1\EmitirComprobanteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('comprobantes', EmitirComprobanteController::class)
        ->name('api.v1.comprobantes.emitir');

    Route::get('comprobantes/{comprobante}', ConsultarComprobanteController::class)
        ->name('api.v1.comprobantes.mostrar');

    Route::get('comprobantes/{comprobante}/ride', DescargarRideController::class)
        ->name('api.v1.comprobantes.ride');
});
