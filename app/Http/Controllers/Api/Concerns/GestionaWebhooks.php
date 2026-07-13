<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Resources\WebhookEndpointResource;
use App\Http\Resources\WebhookEntregaResource;
use App\Models\WebhookEndpoint;
use App\Sri\Enums\EventoWebhook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Gestión de endpoints de webhook, compartida por ambos planos de la API:
 * en /api/v1 el suscriptor es el contribuyente actual; en /api/partner/v1,
 * el partner autenticado. El secreto de firma solo es visible al crear.
 */
trait GestionaWebhooks
{
    /**
     * El dueño de los endpoints que gestiona este plano.
     */
    abstract protected function suscriptor(Request $request): Model;

    public function index(Request $request): AnonymousResourceCollection
    {
        return WebhookEndpointResource::collection(
            $this->endpointsDelSuscriptor($request)->latest()->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url:http,https', 'max:255'],
            'eventos' => ['required', 'array', 'min:1'],
            'eventos.*' => ['string', Rule::in(EventoWebhook::valores())],
        ]);

        $endpoint = new WebhookEndpoint([
            'url' => $request->string('url')->toString(),
            'secreto' => $secreto = WebhookEndpoint::generarSecreto(),
            'eventos' => $request->array('eventos'),
            'activo' => true,
        ]);
        $endpoint->suscriptor()->associate($this->suscriptor($request));
        $endpoint->save();

        // el secreto viaja UNA sola vez: el receptor lo necesita para
        // verificar la firma de cada entrega
        return (new WebhookEndpointResource($endpoint))
            ->additional(['secreto' => $secreto])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, string $uuid): Response
    {
        $this->endpointDelSuscriptor($request, $uuid)->delete();

        return response()->noContent();
    }

    public function entregas(Request $request, string $uuid): AnonymousResourceCollection
    {
        $endpoint = $this->endpointDelSuscriptor($request, $uuid);

        return WebhookEntregaResource::collection(
            $endpoint->entregas()->latest('id')->paginate(25),
        );
    }

    /**
     * @return Builder<WebhookEndpoint>
     */
    private function endpointsDelSuscriptor(Request $request): Builder
    {
        return WebhookEndpoint::query()
            ->whereMorphedTo('suscriptor', $this->suscriptor($request));
    }

    private function endpointDelSuscriptor(Request $request, string $uuid): WebhookEndpoint
    {
        // un endpoint ajeno "no existe", igual que los comprobantes
        return $this->endpointsDelSuscriptor($request)->where('uuid', $uuid)->first()
            ?? abort(404);
    }
}
