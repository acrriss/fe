<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el plano de gestión (/api/partner/v1) a tokens de partner:
 * los tokens de usuarios directos no aprovisionan ni listan contribuyentes.
 */
class SoloPartners
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user() instanceof Partner,
            403,
            'Esta sección de la API requiere un token de partner.',
        );

        return $next($request);
    }
}
