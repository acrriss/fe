<?php

use App\Http\Controllers\Api\V1\EmitirComprobanteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('comprobantes', EmitirComprobanteController::class)
        ->name('api.v1.comprobantes.emitir');
});
