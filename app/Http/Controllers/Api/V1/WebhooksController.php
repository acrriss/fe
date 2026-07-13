<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\GestionaWebhooks;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Models\Contribuyente;
use Illuminate\Http\Request;

/**
 * Webhooks del contribuyente actual (§11): recibe solo sus propios
 * eventos. Funciona con token de usuario directo o con token de partner
 * actuando on-behalf (cabecera X-Contribuyente).
 */
class WebhooksController extends Controller
{
    use GestionaWebhooks;

    protected function suscriptor(Request $request): Contribuyente
    {
        return ResolverContribuyente::de($request)
            ?? abort(403, 'El usuario no pertenece a ningún contribuyente.');
    }
}
