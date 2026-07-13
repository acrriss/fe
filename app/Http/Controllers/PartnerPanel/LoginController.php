<?php

namespace App\Http\Controllers\PartnerPanel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login del panel de partner (§11, 7d): sesión propia (guard
 * partner-web), separada del panel de contribuyentes. Solo pueden entrar
 * los partners con credenciales asignadas (partner:credenciales).
 */
class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('PartnerPanel/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('partner-web')->attempt($request->only('email', 'password'), remember: true)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('partner.inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('partner-web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('partner.login');
    }
}
