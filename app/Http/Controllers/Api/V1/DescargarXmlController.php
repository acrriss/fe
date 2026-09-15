<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Models\Comprobante;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Support\XmlAutorizado;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Descarga del XML autorizado de un comprobante: el XML firmado envuelto
 * en el nodo <autorizacion> del SRI. Es el documento que el emisor entrega
 * al receptor, así que se sirve solo cuando el SRI ya lo autorizó.
 */
class DescargarXmlController extends Controller
{
    public function __invoke(Request $request, Comprobante $comprobante): Response
    {
        // aislamiento entre contribuyentes: un id ajeno "no existe"
        abort_unless(
            $comprobante->contribuyente_id === ResolverContribuyente::de($request)?->id,
            404,
        );

        abort_unless(
            $comprobante->estado === EstadoComprobante::Autorizado,
            409,
            'El XML autorizado solo está disponible para comprobantes autorizados.',
        );

        abort_if(
            $comprobante->xml_path === null || ! Storage::exists($comprobante->xml_path),
            404,
            'El XML del comprobante ya no está disponible.',
        );

        $xml = XmlAutorizado::render(
            $comprobante,
            (string) Storage::get($comprobante->xml_path),
        );

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$comprobante->clave_acceso}.xml\"",
        ]);
    }
}
