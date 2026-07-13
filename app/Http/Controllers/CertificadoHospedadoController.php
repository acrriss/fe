<?php

namespace App\Http\Controllers;

use App\Models\Contribuyente;
use App\Sri\Exceptions\CertificadoInvalido;
use App\Sri\Exceptions\DatoInvalido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carga hospedada del certificado de firma (§11, 7d): página pública
 * protegida por URL firmada temporal (la genera el partner). El cliente
 * final sube su .p12 directamente al servicio — la clave privada nunca
 * pasa por el sistema del partner. La firma de la URL cubre GET y POST.
 */
class CertificadoHospedadoController extends Controller
{
    public function mostrar(Request $request, Contribuyente $contribuyente): Response
    {
        return Inertia::render('Certificado/Subir', [
            'contribuyente' => [
                'razon_social' => $contribuyente->razon_social,
                'ruc' => $contribuyente->ruc,
                'tiene_certificado' => $contribuyente->tieneCertificado(),
                'valido_hasta' => $contribuyente->certificado_valido_hasta?->format('d/m/Y'),
            ],
            // el POST va a esta misma URL firmada (la firma no depende del método)
            'url_guardar' => $request->fullUrl(),
        ]);
    }

    public function guardar(Request $request, Contribuyente $contribuyente): RedirectResponse
    {
        $request->validate([
            'certificado' => ['required', 'file', 'max:100'], // KB
            'clave' => ['required', 'string', 'max:255'],
        ]);

        $contenido = $request->file('certificado')?->getContent();

        try {
            $contribuyente->guardarCertificado(
                base64_encode((string) $contenido),
                $request->string('clave')->toString(),
            );
        } catch (DatoInvalido|CertificadoInvalido $excepcion) {
            throw ValidationException::withMessages(['certificado' => $excepcion->getMessage()]);
        }

        return redirect($request->fullUrl())
            ->with('exito', 'Certificado de firma guardado. Ya puedes cerrar esta página.');
    }
}
