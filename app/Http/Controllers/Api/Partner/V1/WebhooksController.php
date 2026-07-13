<?php

namespace App\Http\Controllers\Api\Partner\V1;

use App\Http\Controllers\Api\Concerns\GestionaWebhooks;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

/**
 * Webhooks del partner (§11): un solo endpoint recibe los eventos de
 * TODOS sus contribuyentes gestionados (el payload identifica a cuál
 * pertenece cada evento).
 */
class WebhooksController extends Controller
{
    use GestionaWebhooks;

    protected function suscriptor(Request $request): Partner
    {
        /** @var Partner $partner el middleware SoloPartners lo garantiza */
        $partner = $request->user();

        return $partner;
    }
}
