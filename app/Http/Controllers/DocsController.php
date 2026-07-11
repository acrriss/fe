<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Documentación pública de la API (Scalar sobre docs/openapi.yaml).
 */
class DocsController extends Controller
{
    public function page(): View
    {
        return view('docs');
    }

    public function spec(): HttpResponse
    {
        $ruta = base_path('docs/openapi.yaml');

        abort_unless(is_file($ruta), 404);

        return response((string) file_get_contents($ruta), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
        ]);
    }
}
