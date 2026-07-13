<?php

namespace App\Http\Controllers\Api\Partner\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContribuyenteResource;
use App\Models\Partner;
use Illuminate\Http\Request;

/**
 * Actualiza los datos editables de un contribuyente gestionado, incluido
 * su sublímite mensual dentro de la cuota pool (§11, 7d). El RUC no se
 * edita: identifica al emisor ante el SRI.
 */
class ActualizarContribuyenteController extends Controller
{
    public function __invoke(Request $request, string $uuid): ContribuyenteResource
    {
        $request->validate([
            'razon_social' => ['sometimes', 'string', 'max:255'],
            'nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dir_matriz' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limite_mensual' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        /** @var Partner $partner */
        $partner = $request->user();

        $contribuyente = $partner->contribuyentes()->where('uuid', $uuid)->first()
            ?? abort(404);

        // solo las claves presentes en el request (incluido null explícito,
        // que quita el sublímite); las ausentes no se tocan
        $datos = [];

        foreach (['razon_social', 'nombre_comercial', 'dir_matriz', 'limite_mensual'] as $campo) {
            if ($request->exists($campo)) {
                $datos[$campo] = $request->input($campo);
            }
        }

        $contribuyente->update($datos);

        return new ContribuyenteResource($contribuyente);
    }
}
