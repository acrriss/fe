<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Contribuyente;
use App\Sri\Exceptions\CertificadoInvalido;
use App\Sri\Exceptions\DatoInvalido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Datos del contribuyente: identificación, certificado de firma y logo.
 */
class ConfiguracionController extends Controller
{
    public function show(Request $request): Response
    {
        $contribuyente = $this->contribuyente($request);

        return Inertia::render('Panel/Configuracion', [
            'contribuyente' => [
                'ruc' => $contribuyente->ruc,
                'razon_social' => $contribuyente->razon_social,
                'nombre_comercial' => $contribuyente->nombre_comercial,
                'dir_matriz' => $contribuyente->dir_matriz,
                'tiene_certificado' => $contribuyente->tieneCertificado(),
                'tiene_logo' => $contribuyente->logo_path !== null,
                'plan' => $contribuyente->plan?->nombre,
            ],
            'logo_url' => $contribuyente->logo_path !== null
                ? route('panel.configuracion.logo.mostrar', ['v' => md5($contribuyente->logo_path.$contribuyente->updated_at)])
                : null,
            'certificado' => $contribuyente->tieneCertificado() ? [
                'titular' => $contribuyente->certificado_titular,
                'emisor' => $contribuyente->certificado_emisor,
                'valido_hasta' => $contribuyente->certificado_valido_hasta?->format('d/m/Y'),
                'vencido' => $contribuyente->certificado_valido_hasta?->isPast() ?? false,
                'por_vencer' => $contribuyente->certificado_valido_hasta !== null
                    && $contribuyente->certificado_valido_hasta->isFuture()
                    && $contribuyente->certificado_valido_hasta->lessThan(now()->addDays(30)),
            ] : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'dir_matriz' => ['nullable', 'string', 'max:300'],
        ]);

        $this->contribuyente($request)->update([
            'razon_social' => $request->string('razon_social')->toString(),
            'nombre_comercial' => $request->string('nombre_comercial')->toString() ?: null,
            'dir_matriz' => $request->string('dir_matriz')->toString() ?: null,
        ]);

        return redirect()->route('panel.configuracion')->with('exito', 'Datos actualizados.');
    }

    public function guardarCertificado(Request $request): RedirectResponse
    {
        $request->validate([
            'certificado' => ['required', 'file', 'max:100'], // KB
            'clave' => ['required', 'string', 'max:255'],
        ]);

        $contenido = $request->file('certificado')?->getContent();

        try {
            $this->contribuyente($request)->guardarCertificado(
                base64_encode((string) $contenido),
                $request->string('clave')->toString(),
            );
        } catch (DatoInvalido|CertificadoInvalido $excepcion) {
            throw ValidationException::withMessages(['certificado' => $excepcion->getMessage()]);
        }

        return redirect()->route('panel.configuracion')
            ->with('exito', 'Certificado de firma guardado.');
    }

    /**
     * Sirve el logo del contribuyente autenticado (vive en storage
     * privado; el query param `v` solo rompe la caché tras reemplazarlo).
     */
    public function mostrarLogo(Request $request): StreamedResponse
    {
        $contribuyente = $this->contribuyente($request);

        abort_if(
            $contribuyente->logo_path === null || ! Storage::exists($contribuyente->logo_path),
            404,
        );

        return Storage::response($contribuyente->logo_path);
    }

    public function guardarLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024'], // KB
        ]);

        $contribuyente = $this->contribuyente($request);

        $path = $request->file('logo')?->storeAs(
            'logos',
            $contribuyente->uuid.'.'.$request->file('logo')->extension(),
        );

        $contribuyente->update(['logo_path' => $path]);

        return redirect()->route('panel.configuracion')->with('exito', 'Logo actualizado.');
    }

    private function contribuyente(Request $request): Contribuyente
    {
        $contribuyente = $request->user()?->contribuyente;

        abort_if($contribuyente === null, 403, 'El usuario no pertenece a ningún contribuyente.');

        return $contribuyente;
    }
}
