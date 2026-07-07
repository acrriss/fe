<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Gestión de tokens de API del usuario. El token en claro se muestra una
 * sola vez (vía flash) tras crearlo.
 */
class TokensController extends Controller
{
    public function index(Request $request): Response
    {
        $tokens = $request->user()
            ?->tokens()
            ->latest('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'nombre' => $token->name,
                'ultimoUso' => $token->last_used_at?->diffForHumans(),
                'creado' => $token->created_at?->toDateString(),
            ]);

        return Inertia::render('Panel/Tokens', [
            'tokens' => $tokens ?? collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()
            ?->createToken($request->string('nombre')->toString())
            ->plainTextToken;

        return redirect()->route('panel.tokens')
            ->with('exito', 'Token creado. Cópielo ahora: no volverá a mostrarse.')
            ->with('token', $token);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()?->tokens()->where('id', $tokenId)->delete();

        return redirect()->route('panel.tokens')->with('exito', 'Token revocado.');
    }
}
