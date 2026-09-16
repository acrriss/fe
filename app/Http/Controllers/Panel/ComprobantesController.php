<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Models\Comprobante;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historial de emisiones del contribuyente.
 *
 * Las descargas (RIDE y XML autorizado) reutilizan los controladores de la
 * API: el panel y la API deben entregar exactamente el mismo documento.
 */
class ComprobantesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $contribuyente = ResolverContribuyente::de($request);

        abort_if($contribuyente === null, 403, 'El usuario no pertenece a ningún contribuyente.');

        $comprobantes = $contribuyente->comprobantes()
            ->latest('id')
            ->paginate(15)
            ->through(fn (Comprobante $comprobante): array => [
                'id' => $comprobante->uuid,
                'tipo' => $comprobante->tipo->etiqueta(),
                'estado' => $comprobante->estado->value,
                'estadoFinal' => $comprobante->estado->esFinal(),
                'secuencial' => $comprobante->secuencial,
                'claveAcceso' => $comprobante->clave_acceso,
                'importeTotal' => $comprobante->importe_total,
                'emitidoEn' => $comprobante->emitido_en?->toDateString(),
                'tieneXml' => $comprobante->xml_path !== null,
            ]);

        return Inertia::render('Panel/Comprobantes', [
            'comprobantes' => $comprobantes,
        ]);
    }
}
