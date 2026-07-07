<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Historial de emisiones del contribuyente, con descarga de XML.
 * (La descarga del RIDE reutiliza DescargarRideController.)
 */
class ComprobantesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $contribuyente = $request->user()?->contribuyente;

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

    public function descargarXml(Request $request, Comprobante $comprobante): HttpResponse
    {
        abort_unless(
            $comprobante->contribuyente_id === $request->user()?->contribuyente_id,
            404,
        );

        abort_if(
            $comprobante->xml_path === null || ! Storage::exists($comprobante->xml_path),
            404,
            'El XML del comprobante no está disponible.',
        );

        return Storage::download(
            $comprobante->xml_path,
            "comprobante-{$comprobante->clave_acceso}.xml",
            ['Content-Type' => 'application/xml'],
        );
    }
}
