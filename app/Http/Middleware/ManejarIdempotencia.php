<?php

namespace App\Http\Middleware;

use App\Models\ClaveIdempotencia;
use App\Models\Contribuyente;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotencia de emisión (§11): si la petición trae la cabecera
 * `Idempotency-Key`, el resultado se guarda asociado a la clave (única
 * por contribuyente) y cualquier reintento con la misma clave devuelve
 * la respuesta original en vez de re-ejecutar — un POS puede reintentar
 * tras un timeout sin riesgo de duplicar la emisión.
 *
 *  - misma clave + mismo payload → respuesta original (Idempotency-Replayed)
 *  - misma clave + otro payload  → 409 (clave mal reutilizada)
 *  - petición original en curso  → 409 (reintentar en unos segundos)
 *
 * Solo se guardan desenlaces deterministas (2xx/4xx de negocio); ante
 * un 5xx o límites (401/403/429) la clave se libera para reintentar.
 */
class ManejarIdempotencia
{
    public const string CABECERA = 'Idempotency-Key';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clave = (string) $request->header(self::CABECERA, '');
        $contribuyente = ResolverContribuyente::de($request);

        if ($clave === '' || $contribuyente === null) {
            return $next($request);
        }

        abort_if(strlen($clave) > 255, 422, 'La cabecera Idempotency-Key no debe exceder 255 caracteres.');

        $huella = $this->huella($request);

        $existente = ClaveIdempotencia::query()
            ->where('contribuyente_id', $contribuyente->id)
            ->where('clave', $clave)
            ->first();

        if ($existente !== null && ! $existente->expirada()) {
            if ($existente->respondida()) {
                abort_if(
                    $existente->huella !== $huella,
                    409,
                    'La Idempotency-Key ya fue usada con un payload distinto.',
                );

                return $this->reproducir($existente);
            }

            abort_if(
                $existente->enCursoVigente(),
                409,
                'La petición original con esta Idempotency-Key sigue en curso; reintente en unos segundos.',
            );
        }

        // clave expirada o en-curso huérfana (proceso muerto): se libera
        $existente?->delete();

        return $this->procesarYGuardar($request, $next, $contribuyente, $clave, $huella);
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    private function procesarYGuardar(
        Request $request,
        Closure $next,
        Contribuyente $contribuyente,
        string $clave,
        string $huella,
    ): Response {
        try {
            $registro = ClaveIdempotencia::create([
                'contribuyente_id' => $contribuyente->id,
                'clave' => $clave,
                'huella' => $huella,
            ]);
        } catch (UniqueConstraintViolationException) {
            // carrera: otra petición registró la clave hace un instante
            abort(409, 'La petición original con esta Idempotency-Key sigue en curso; reintente en unos segundos.');
        }

        $respuesta = $next($request);

        if ($this->esDesenlaceDeterminista($respuesta)) {
            $registro->update([
                'codigo_http' => $respuesta->getStatusCode(),
                'respuesta' => (string) $respuesta->getContent(),
            ]);
        } else {
            // 5xx o rechazo transitorio: liberar la clave para reintentar
            $registro->delete();
        }

        return $respuesta;
    }

    private function reproducir(ClaveIdempotencia $registro): Response
    {
        return response((string) $registro->respuesta, (int) $registro->codigo_http, [
            'Content-Type' => 'application/json',
            'Idempotency-Replayed' => 'true',
        ]);
    }

    /**
     * La huella ata la clave al request exacto: método, URI (incluye
     * ?async=1) y cuerpo crudo.
     */
    private function huella(Request $request): string
    {
        return hash('sha256', $request->method().'|'.$request->getRequestUri().'|'.$request->getContent());
    }

    /**
     * Solo se cachean desenlaces de negocio (emitido, devuelto, payload
     * inválido…), nunca errores transitorios que el cliente debe poder
     * reintentar con la misma clave.
     */
    private function esDesenlaceDeterminista(Response $respuesta): bool
    {
        $codigo = $respuesta->getStatusCode();

        return $codigo < 500 && ! in_array($codigo, [401, 403, 429], true);
    }
}
