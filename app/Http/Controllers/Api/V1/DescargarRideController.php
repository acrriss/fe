<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Models\Comprobante;
use App\Sri\Contracts\RideGenerator;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Support\ComprobanteXmlParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Descarga del RIDE (PDF) de un comprobante autorizado.
 *
 * El RIDE se genera bajo demanda desde el XML firmado almacenado (la
 * fuente de verdad legal) y se cachea en storage para descargas futuras.
 * La ruta cacheada lleva la huella del generador (plantilla y logo): si
 * cualquiera de los dos cambió, el RIDE se regenera y el viejo se borra.
 */
class DescargarRideController extends Controller
{
    public function __invoke(
        Request $request,
        Comprobante $comprobante,
        RideGenerator $generator,
        ComprobanteXmlParser $parser,
    ): Response {
        // aislamiento entre contribuyentes: un id ajeno "no existe"
        abort_unless(
            $comprobante->contribuyente_id === ResolverContribuyente::de($request)?->id,
            404,
        );

        abort_unless(
            $comprobante->estado === EstadoComprobante::Autorizado,
            409,
            'El RIDE solo está disponible para comprobantes autorizados.',
        );

        abort_if(
            $comprobante->xml_path === null || ! Storage::exists($comprobante->xml_path),
            404,
            'El XML del comprobante ya no está disponible.',
        );

        $ridePath = "rides/{$comprobante->clave_acceso}-{$generator->huella($comprobante)}.pdf";

        $pdf = $this->rideCacheado($comprobante, $ridePath)
            ?? $this->generarYCachear($comprobante, $ridePath, $generator, $parser);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"ride-{$comprobante->clave_acceso}.pdf\"",
        ]);
    }

    private function rideCacheado(Comprobante $comprobante, string $ridePath): ?string
    {
        if ($comprobante->ride_path !== $ridePath || ! Storage::exists($ridePath)) {
            return null;
        }

        return Storage::get($ridePath);
    }

    private function generarYCachear(
        Comprobante $comprobante,
        string $ridePath,
        RideGenerator $generator,
        ComprobanteXmlParser $parser,
    ): string {
        $xml = (string) Storage::get((string) $comprobante->xml_path);
        $pdf = $generator->generar($comprobante, $parser->parse($xml));

        Storage::put($ridePath, $pdf);

        $desactualizado = $comprobante->ride_path;
        $comprobante->update(['ride_path' => $ridePath]);

        if ($desactualizado !== null && $desactualizado !== $ridePath) {
            Storage::delete($desactualizado);
        }

        return $pdf;
    }
}
