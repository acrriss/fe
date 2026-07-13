<?php

namespace App\Http\Controllers\PartnerPanel;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEntrega;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Webhooks del partner en el panel: endpoints registrados y las últimas
 * entregas con su resultado (la herramienta de depuración de la
 * integración). La gestión (crear/eliminar) vive en la API.
 */
class WebhooksController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        $endpoints = WebhookEndpoint::query()
            ->whereMorphedTo('suscriptor', $partner)
            ->latest()
            ->get();

        $entregas = WebhookEntrega::query()
            ->whereIn('webhook_endpoint_id', $endpoints->pluck('id'))
            ->with('endpoint')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (WebhookEntrega $entrega): array => [
                'id' => $entrega->uuid,
                'evento' => $entrega->evento,
                'estado' => $entrega->estado->value,
                'intentos' => $entrega->intentos,
                'codigoHttp' => $entrega->codigo_http,
                'error' => $entrega->error,
                'url' => $entrega->endpoint?->url,
                'creadoEn' => $entrega->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('PartnerPanel/Webhooks', [
            'endpoints' => $endpoints->map(fn (WebhookEndpoint $endpoint): array => [
                'id' => $endpoint->uuid,
                'url' => $endpoint->url,
                'eventos' => $endpoint->eventos,
                'activo' => $endpoint->activo,
            ]),
            'entregas' => $entregas,
        ]);
    }
}
