<?php

namespace App\Http\Controllers\Panel\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contribuyente;
use App\Models\User;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\Ruc;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Alta de un contribuyente con su primer usuario administrador.
 */
class RegistroController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Registro');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'razon_social' => ['required', 'string', 'max:300'],
            'ruc' => ['required', 'string', self::reglaRuc(), 'unique:contribuyentes,ruc'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($request): User {
            $contribuyente = Contribuyente::create([
                'razon_social' => $request->string('razon_social')->toString(),
                'ruc' => $request->string('ruc')->toString(),
            ]);

            return User::create([
                'contribuyente_id' => $contribuyente->id,
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel.inicio')
            ->with('exito', '¡Bienvenido! Cargue su certificado de firma para empezar a emitir.');
    }

    /**
     * Un único criterio de RUC válido en toda la aplicación: el del value
     * object, que además del formato comprueba el dígito verificador.
     */
    private static function reglaRuc(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            if (! is_string($valor)) {
                $fallar('El RUC debe ser una cadena de 13 dígitos.');

                return;
            }

            try {
                Ruc::fromString($valor);
            } catch (DatoInvalido $excepcion) {
                $fallar($excepcion->getMessage());
            }
        };
    }
}
