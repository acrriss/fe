<?php

namespace App\Http\Controllers\PartnerPanel;

use App\Http\Controllers\Controller;
use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\Vinculacion;
use App\Sri\Enums\EstadoVinculacion;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\Ruc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vinculaciones del partner en el panel (§11, 7d): estado de las
 * solicitudes y formulario para pedir una nueva (RUC que ya existe como
 * cuenta directa; el dueño la resuelve desde SU panel).
 */
class VinculacionesController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        $vinculaciones = $partner->vinculaciones()
            ->with('contribuyente')
            ->latest()
            ->paginate(15)
            ->through(fn (Vinculacion $vinculacion): array => [
                'id' => $vinculacion->uuid,
                'ruc' => $vinculacion->contribuyente?->ruc,
                'razonSocial' => $vinculacion->contribuyente?->razon_social,
                'estado' => $vinculacion->estado->value,
                'solicitadaEn' => $vinculacion->created_at?->format('d/m/Y'),
                'resueltaEn' => $vinculacion->resuelta_en?->format('d/m/Y'),
            ]);

        return Inertia::render('PartnerPanel/Vinculaciones', [
            'vinculaciones' => $vinculaciones,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['ruc' => ['required', 'string']]);

        try {
            $ruc = (string) Ruc::fromString($request->string('ruc')->toString());
        } catch (DatoInvalido $excepcion) {
            throw ValidationException::withMessages(['ruc' => $excepcion->getMessage()]);
        }

        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        $contribuyente = Contribuyente::where('ruc', $ruc)->first();

        if ($contribuyente === null) {
            throw ValidationException::withMessages([
                'ruc' => 'El RUC no está registrado en el servicio; aprovisiónalo por la API (POST /contribuyentes).',
            ]);
        }

        if ($contribuyente->partner_id !== null) {
            throw ValidationException::withMessages([
                'ruc' => $contribuyente->partner_id === $partner->id
                    ? 'Ya gestionas este contribuyente.'
                    : 'El contribuyente ya es gestionado por otro partner.',
            ]);
        }

        $partner->vinculaciones()
            ->where('contribuyente_id', $contribuyente->id)
            ->where('estado', EstadoVinculacion::Pendiente)
            ->firstOr(fn () => $partner->vinculaciones()->create([
                'contribuyente_id' => $contribuyente->id,
                'estado' => EstadoVinculacion::Pendiente,
            ]));

        return redirect()->route('partner.vinculaciones')
            ->with('exito', "Solicitud enviada: el dueño de {$contribuyente->razon_social} debe aprobarla desde su panel.");
    }
}
