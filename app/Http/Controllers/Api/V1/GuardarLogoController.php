<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Http\Requests\GuardarLogoRequest;
use Illuminate\Http\Response;

/**
 * Carga (o reemplaza) el logo que el RIDE imprime (Tabla 11, fila 10). Se
 * normaliza a un PNG de 600 × 300; los RIDE ya generados se regeneran con
 * él en su próxima descarga.
 */
class GuardarLogoController extends Controller
{
    public function __invoke(GuardarLogoRequest $request): Response
    {
        $contribuyente = ResolverContribuyente::de($request);

        if ($contribuyente === null) {
            abort(403, 'El usuario no pertenece a ningún contribuyente.');
        }

        $contribuyente->guardarLogo($request->logoNormalizado());

        return response()->noContent();
    }
}
