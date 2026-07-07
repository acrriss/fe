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
        $user = $request->user();
        $contribuyente = $user?->contribuyente;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email']),
                'contribuyente' => $contribuyente === null ? null : [
                    ...$contribuyente->only(['uuid', 'ruc', 'razon_social', 'nombre_comercial']),
                    'tiene_certificado' => $contribuyente->tieneCertificado(),
                    'tiene_logo' => $contribuyente->logo_path !== null,
                ],
            ],
            'flash' => [
                'exito' => $request->session()->get('exito'),
                'token' => $request->session()->get('token'),
            ],
        ];
    }
}
