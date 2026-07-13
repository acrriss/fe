<?php

namespace App\Http\Controllers\Api\Partner\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VinculacionResource;
use App\Models\Contribuyente;
use App\Models\Partner;
use App\Sri\Enums\EstadoVinculacion;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\Ruc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Vinculación de un RUC ya registrado como cuenta directa (§11, 7d): el
 * partner solicita gestionarlo y el dueño de la cuenta decide desde su
 * panel. Idempotente: repetir la solicitud pendiente devuelve la misma.
 */
class VinculacionesController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['ruc' => ['required', 'string']]);

        try {
            $ruc = (string) Ruc::fromString($request->string('ruc')->toString());
        } catch (DatoInvalido $excepcion) {
            throw ValidationException::withMessages(['ruc' => $excepcion->getMessage()]);
        }

        /** @var Partner $partner */
        $partner = $request->user();

        $contribuyente = Contribuyente::where('ruc', $ruc)->first()
            ?? abort(404, 'El RUC no está registrado en el servicio; aprovisiónalo con POST /contribuyentes.');

        abort_if(
            $contribuyente->partner_id === $partner->id,
            409,
            'El contribuyente ya es gestionado por este partner.',
        );

        abort_if(
            $contribuyente->partner_id !== null,
            409,
            'El contribuyente ya es gestionado por otro partner.',
        );

        $pendiente = $partner->vinculaciones()
            ->where('contribuyente_id', $contribuyente->id)
            ->where('estado', EstadoVinculacion::Pendiente)
            ->first();

        if ($pendiente !== null) {
            return (new VinculacionResource($pendiente))->response();
        }

        $vinculacion = $partner->vinculaciones()->create([
            'contribuyente_id' => $contribuyente->id,
            'estado' => EstadoVinculacion::Pendiente,
        ]);

        return (new VinculacionResource($vinculacion))
            ->response()
            ->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Partner $partner */
        $partner = $request->user();

        return VinculacionResource::collection(
            $partner->vinculaciones()->with('contribuyente')->latest()->paginate(25),
        );
    }
}
