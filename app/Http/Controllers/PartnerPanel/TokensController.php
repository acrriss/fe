<?php

namespace App\Http\Controllers\PartnerPanel;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens de API del partner: rotación de la credencial desde el panel.
 * El token en claro se muestra una sola vez (vía flash), igual que en el
 * panel de contribuyentes.
 */
class TokensController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        return Inertia::render('PartnerPanel/Tokens', [
            'tokens' => $partner->tokens()
                ->latest('id')
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'nombre' => $token->name,
                    'ultimoUso' => $token->last_used_at?->diffForHumans(),
                    'creado' => $token->created_at?->toDateString(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        $token = $partner->createToken($request->string('nombre')->toString())->plainTextToken;

        return redirect()->route('partner.tokens')
            ->with('exito', 'Token creado. Cópielo ahora: no volverá a mostrarse.')
            ->with('token', $token);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        /** @var Partner $partner */
        $partner = $request->user('partner-web');

        $partner->tokens()->where('id', $tokenId)->delete();

        return redirect()->route('partner.tokens')->with('exito', 'Token revocado.');
    }
}
