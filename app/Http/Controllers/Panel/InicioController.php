<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolverContribuyente;
use App\Http\Resources\ComprobanteResource;
use App\Sri\Enums\EstadoComprobante;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen del panel: consumo del mes vs. cuota del plan y últimas emisiones.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $contribuyente = ResolverContribuyente::de($request);

        abort_if($contribuyente === null, 403, 'El usuario no pertenece a ningún contribuyente.');

        $plan = $contribuyente->plan;

        return Inertia::render('Panel/Inicio', [
            'consumo' => [
                'emisionesDelMes' => $contribuyente->emisionesDelMes(),
                'cuotaMensual' => $plan?->cuota_mensual,
                'plan' => $plan?->nombre,
            ],
            'totales' => [
                'autorizados' => $contribuyente->comprobantes()
                    ->where('estado', EstadoComprobante::Autorizado)->count(),
                'fallidos' => $contribuyente->comprobantes()
                    ->whereIn('estado', [EstadoComprobante::Devuelto, EstadoComprobante::NoAutorizado, EstadoComprobante::Fallido])
                    ->count(),
            ],
            'ultimos' => ComprobanteResource::collection(
                $contribuyente->comprobantes()->latest('id')->limit(5)->get(),
            ),
        ]);
    }
}
