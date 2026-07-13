<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // el panel de contribuyentes autentica por sesión web; el de
        // partners, por su propio guard de sesión
        $user = $request->user('web');
        $contribuyente = $user?->contribuyente;
        $partner = $request->user('partner-web');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email']),
                'contribuyente' => $contribuyente === null ? null : [
                    ...$contribuyente->only(['uuid', 'ruc', 'razon_social', 'nombre_comercial']),
                    'tiene_certificado' => $contribuyente->tieneCertificado(),
                    'tiene_logo' => $contribuyente->logo_path !== null,
                ],
                'partner' => $partner?->only(['uuid', 'nombre', 'slug', 'email']),
            ],
            'flash' => [
                'exito' => $request->session()->get('exito'),
                'token' => $request->session()->get('token'),
                'enlace_certificado' => $request->session()->get('enlace_certificado'),
            ],
        ];
    }
}
