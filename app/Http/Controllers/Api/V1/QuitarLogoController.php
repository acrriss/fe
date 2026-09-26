<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Quita el logo del RIDE. Idempotente: sin logo también responde 204.
 */
class QuitarLogoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $contribuyente = ResolverContribuyente::de($request);

        if ($contribuyente === null) {
            abort(403, 'El usuario no pertenece a ningún contribuyente.');
        }

        $contribuyente->quitarLogo();

        return response()->noContent();
    }
}
