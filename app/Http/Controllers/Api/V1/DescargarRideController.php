<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
            $comprobante->contribuyente_id === $request->user()?->contribuyente_id,
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

        $pdf = $this->rideCacheado($comprobante)
            ?? $this->generarYCachear($comprobante, $generator, $parser);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"ride-{$comprobante->clave_acceso}.pdf\"",
        ]);
    }

    private function rideCacheado(Comprobante $comprobante): ?string
    {
        if ($comprobante->ride_path === null || ! Storage::exists($comprobante->ride_path)) {
            return null;
        }

        return Storage::get($comprobante->ride_path);
    }

    private function generarYCachear(
        Comprobante $comprobante,
        RideGenerator $generator,
        ComprobanteXmlParser $parser,
    ): string {
        $xml = (string) Storage::get((string) $comprobante->xml_path);
        $pdf = $generator->generar($comprobante, $parser->parse($xml));

        $ridePath = "rides/{$comprobante->clave_acceso}.pdf";
        Storage::put($ridePath, $pdf);
        $comprobante->update(['ride_path' => $ridePath]);

        return $pdf;
    }
}
