<?php

namespace App\Http\Controllers\Api\Partner\V1;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Genera el enlace hospedado de carga de certificado (§11, 7d): una URL
 * firmada y temporal donde el cliente final sube su .p12 directamente al
 * servicio. La clave privada nunca pasa por el partner. El enlace se
 * puede regenerar cuando haga falta (cada uno con su propia expiración).
 */
class EnlaceCertificadoController extends Controller
{
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        /** @var Partner $partner */
        $partner = $request->user();

        $contribuyente = $partner->contribuyentes()->where('uuid', $uuid)->first()
            ?? abort(404);

        $expiraEn = now()->addHours(config()->integer('sri.certificados.enlace_ttl_horas', 72));

        return response()->json([
            'url' => URL::temporarySignedRoute('certificado.hospedado', $expiraEn, [
                'contribuyente' => $contribuyente->uuid,
            ]),
            'expiraEn' => $expiraEn->toIso8601String(),
        ], 201);
    }
}
