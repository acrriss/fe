<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Sri\Exceptions\CertificadoInvalido;
use App\Sri\Exceptions\DatoInvalido;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Carga (o reemplaza) el certificado de firma del contribuyente
 * autenticado. Se almacena cifrado en reposo.
 */
class GuardarCertificadoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->validate([
            'p12' => ['required', 'string', 'max:120000'],
            'clave' => ['required', 'string', 'max:255'],
        ]);

        $contribuyente = $request->user()?->contribuyente;

        if ($contribuyente === null) {
            abort(403, 'El usuario no pertenece a ningún contribuyente.');
        }

        try {
            $contribuyente->guardarCertificado(
                $request->string('p12')->toString(),
                $request->string('clave')->toString(),
            );
        } catch (DatoInvalido|CertificadoInvalido $excepcion) {
            throw ValidationException::withMessages(['p12' => $excepcion->getMessage()]);
        }

        return response()->noContent();
    }
}
