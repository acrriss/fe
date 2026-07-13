<?php

namespace App\Http\Controllers\PartnerPanel;

use App\Http\Controllers\Controller;
use App\Models\Comprobante;
use App\Models\Partner;
use App\Sri\Enums\EstadoComprobante;
use App\Sri\Enums\EstadoVinculacion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen del panel de partner: consumo pool del mes vs. cuota, totales
 * de la operación y últimas emisiones de sus gestionados.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        return Inertia::render('PartnerPanel/Inicio', [
            'consumo' => [
                'emisionesDelMes' => $partner->emisionesDelMes(),
                'cuotaMensual' => $partner->cuota_mensual,
                'limitePorMinuto' => $partner->limite_por_minuto,
            ],
            'totales' => [
                'contribuyentes' => $partner->contribuyentes()->count(),
                'autorizados' => $partner->comprobantes()
                    ->where('comprobantes.estado', EstadoComprobante::Autorizado)->count(),
                'vinculacionesPendientes' => $partner->vinculaciones()
                    ->where('estado', EstadoVinculacion::Pendiente)->count(),
            ],
            'ultimos' => $partner->comprobantes()
                ->with('contribuyente')
                ->latest('comprobantes.id')
                ->limit(8)
                ->get()
                ->map(fn (Comprobante $comprobante): array => [
                    'id' => $comprobante->uuid,
                    'tipo' => $comprobante->tipo->rootElement(),
                    'estado' => $comprobante->estado->value,
                    'razonSocial' => $comprobante->contribuyente?->razon_social,
                    'secuencial' => $comprobante->secuencial,
                    'externalId' => $comprobante->external_id,
                    'emitidoEn' => $comprobante->emitido_en?->toDateString(),
                ]),
        ]);
    }
}
