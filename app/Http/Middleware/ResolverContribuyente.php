<?php

namespace App\Http\Middleware;

use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el contribuyente sobre el que actúa la petición (§11):
 *
 *  - un User actúa sobre su propio contribuyente;
 *  - un Partner actúa on-behalf del contribuyente gestionado que indique
 *    la cabecera `X-Contribuyente` (400 si falta; 404 si el uuid no es de
 *    un contribuyente suyo — un id ajeno "no existe").
 *
 * El middleware falla temprano en la API v1; `de()` es la única fuente de
 * verdad y también sirve a rutas sin el middleware (panel por sesión).
 */
class ResolverContribuyente
{
    private const string ATRIBUTO = 'contribuyente-resuelto';

    public const string CABECERA = 'X-Contribuyente';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        self::de($request);

        return $next($request);
    }

    public static function de(Request $request): ?Contribuyente
    {
        $resuelto = $request->attributes->get(self::ATRIBUTO);

        if ($resuelto instanceof Contribuyente) {
            return $resuelto;
        }

        $actor = $request->user();

        if ($actor instanceof Partner) {
            $contribuyente = self::gestionadoPor($actor, $request);
            $request->attributes->set(self::ATRIBUTO, $contribuyente);

            return $contribuyente;
        }

        return $actor instanceof User ? $actor->contribuyente : null;
    }

    private static function gestionadoPor(Partner $partner, Request $request): Contribuyente
    {
        $uuid = $request->header(self::CABECERA);

        abort_if(
            $uuid === null || $uuid === '',
            400,
            'Falta la cabecera '.self::CABECERA.' con el id del contribuyente gestionado.',
        );

        return $partner->contribuyentes()->where('uuid', $uuid)->first()
            ?? abort(404);
    }
}
