<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Models\Vinculacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resolución de solicitudes de vinculación (§11, 7d) por el dueño de la
 * cuenta directa: al aprobar, su contribuyente pasa a ser gestionado por
 * el partner (las emisiones consumen la cuota pool del partner); al
 * rechazar, no cambia nada. Se muestran en Configuración.
 */
class VinculacionesController extends Controller
{
    public function aprobar(Request $request, string $uuid): RedirectResponse
    {
        $this->pendienteDelContribuyente($request, $uuid)->aprobar();

        return redirect()->route('panel.configuracion')
            ->with('exito', 'Vinculación aprobada: el partner ya puede emitir a tu nombre.');
    }

    public function rechazar(Request $request, string $uuid): RedirectResponse
    {
        $this->pendienteDelContribuyente($request, $uuid)->rechazar();

        return redirect()->route('panel.configuracion')
            ->with('exito', 'Vinculación rechazada.');
    }

    private function pendienteDelContribuyente(Request $request, string $uuid): Vinculacion
    {
        $contribuyente = ResolverContribuyente::de($request)
            ?? abort(403, 'El usuario no pertenece a ningún contribuyente.');

        $vinculacion = Vinculacion::query()
            ->where('uuid', $uuid)
            ->where('contribuyente_id', $contribuyente->id)
            ->first() ?? abort(404);

        abort_unless($vinculacion->pendiente(), 409, 'La solicitud ya fue resuelta.');

        return $vinculacion;
    }
}
