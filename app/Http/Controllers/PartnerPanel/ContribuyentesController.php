<?php

namespace App\Http\Controllers\PartnerPanel;

use App\Http\Controllers\Controller;
use App\Models\Contribuyente;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contribuyentes gestionados por el partner: consumo, estado del
 * certificado y generación del enlace hospedado de carga (§11, 7d).
 */
class ContribuyentesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        $contribuyentes = $partner->contribuyentes()
            ->latest()
            ->paginate(15)
            ->through(fn (Contribuyente $contribuyente): array => [
                'id' => $contribuyente->uuid,
                'ruc' => $contribuyente->ruc,
                'razonSocial' => $contribuyente->razon_social,
                'emisionesDelMes' => $contribuyente->emisionesDelMes(),
                'limiteMensual' => $contribuyente->limite_mensual,
                'certificado' => [
                    'configurado' => $contribuyente->tieneCertificado(),
                    'validoHasta' => $contribuyente->certificado_valido_hasta?->format('d/m/Y'),
                    'vencido' => $contribuyente->certificado_valido_hasta?->isPast() ?? false,
                ],
            ]);

        return Inertia::render('PartnerPanel/Contribuyentes', [
            'contribuyentes' => $contribuyentes,
        ]);
    }

    /**
     * Genera el enlace hospedado de carga de certificado y lo muestra vía
     * flash, una sola vez (mismo enlace firmado que genera la API).
     */
    public function enlaceCertificado(Request $request, string $uuid): RedirectResponse
    {
        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        $contribuyente = $partner->contribuyentes()->where('uuid', $uuid)->first()
            ?? abort(404);

        $expiraEn = now()->addHours(config()->integer('sri.certificados.enlace_ttl_horas', 72));

        $url = URL::temporarySignedRoute('certificado.hospedado', $expiraEn, [
            'contribuyente' => $contribuyente->uuid,
        ]);

        return redirect()->route('partner.contribuyentes')
            ->with('exito', "Enlace generado para {$contribuyente->razon_social} (vigente hasta el {$expiraEn->format('d/m/Y H:i')}). Compártelo con tu cliente.")
            ->with('enlace_certificado', $url);
    }
}
