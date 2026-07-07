<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Intercambia credenciales por un token de API (Sanctum). El dashboard de
 * la fase 6b ofrecerá gestión visual de tokens; este endpoint permite a
 * los integradores obtenerlos programáticamente.
 */
class EmitirTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        return response()->json([
            'token' => $user->createToken($request->string('device_name')->toString())->plainTextToken,
        ], 201);
    }
}
