<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Sri\Catalogos\CodigosAuxiliares;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogos de la ficha técnica del SRI: tablas de valores que el
 * integrador necesita para construir sus formularios sin transcribirlas a
 * mano y sin quedarse atrás cuando el SRI las amplíe.
 *
 * Son datos de una norma publicada, iguales para todos: el endpoint es
 * público (no depende del contribuyente ni expone nada suyo) y cacheable.
 */
class CatalogosController extends Controller
{
    /**
     * Catálogos disponibles, para descubrirlos sin leer la documentación.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'clave' => 'codigos-auxiliares',
                    'nombre' => 'Códigos del segundo código del ítem por actividad regulada',
                    'url' => route('api.v1.catalogos.codigos-auxiliares'),
                ],
            ],
        ]);
    }

    /**
     * Tablas 31 y 32 de la ficha (Anexos 23 y 25 §1).
     */
    public function codigosAuxiliares(Request $request): JsonResponse
    {
        return $this->cacheable($request, [
            'ficha' => CodigosAuxiliares::FICHA,
            'version' => CodigosAuxiliares::VERSION,
            'grupos' => CodigosAuxiliares::grupos(),
        ]);
    }

    /**
     * Un catálogo cambia como mucho con la ficha: se sirve con ETag para
     * que el integrador revalide en una petición vacía (304) en vez de
     * descargarlo en cada apertura de su formulario.
     *
     * @param  array<string, mixed>  $datos
     */
    private function cacheable(Request $request, array $datos): JsonResponse
    {
        $respuesta = response()
            ->json($datos)
            ->setMaxAge(86400)
            ->setPublic();

        $respuesta->setEtag(md5((string) json_encode($datos)));
        $respuesta->isNotModified($request);

        return $respuesta;
    }
}
