<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Sri\Catalogos\CodigosAuxiliares;
use App\Sri\Catalogos\FormasPago;
use App\Sri\Catalogos\TarifasIva;
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
                [
                    'clave' => 'formas-pago',
                    'nombre' => 'Formas de pago del bloque <pagos> (Tabla 24)',
                    'url' => route('api.v1.catalogos.formas-pago'),
                ],
                [
                    'clave' => 'tarifas-iva',
                    'nombre' => 'Tarifas de IVA del campo <codigoPorcentaje> (Tabla 17)',
                    'url' => route('api.v1.catalogos.tarifas-iva'),
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
     * Tabla 24 de la ficha. A diferencia de los códigos auxiliares, esta
     * lista es cerrada: el servicio rechaza un `formaPago` que no esté aquí.
     */
    public function formasPago(Request $request): JsonResponse
    {
        return $this->cacheable($request, [
            'ficha' => FormasPago::FICHA,
            'version' => FormasPago::VERSION,
            'tabla' => 24,
            'validado' => true,
            'codigos' => FormasPago::codigos(),
        ]);
    }

    /**
     * Tabla 17 de la ficha. Lista cerrada y validada, como las formas de
     * pago: el servicio rechaza un `codigoPorcentaje` que no esté aquí
     * cuando el impuesto es IVA.
     */
    public function tarifasIva(Request $request): JsonResponse
    {
        return $this->cacheable($request, [
            'ficha' => TarifasIva::FICHA,
            'version' => TarifasIva::VERSION,
            'tabla' => 17,
            'validado' => true,
            'codigoImpuesto' => TarifasIva::CODIGO_IVA,
            'codigos' => TarifasIva::codigos(),
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
