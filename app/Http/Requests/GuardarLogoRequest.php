<?php

namespace App\Http\Requests;

use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Ride\LogoRide;
use App\Sri\Ride\NormalizadorLogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Logo del RIDE en base64, igual desde la API que desde el panel (su
 * recortador envía el PNG ya encuadrado). La imagen se normaliza aquí, así
 * que cualquier problema con ella es un 422 que nombra el campo.
 */
class GuardarLogoRequest extends FormRequest
{
    private LogoRide $logoNormalizado;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'string', 'max:'.self::maximoBase64()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
        ];
    }

    /**
     * Longitud en base64 de {@see NormalizadorLogo::MAXIMO_BYTES}, más el
     * prefijo de un data-uri.
     */
    private static function maximoBase64(): int
    {
        return 4 * intdiv(NormalizadorLogo::MAXIMO_BYTES + 2, 3) + 64;
    }

    protected function passedValidation(): void
    {
        $base64 = $this->string('logo')->toString();

        // el recortador del navegador entrega un data-uri; la API, base64 puro
        $base64 = preg_replace('/^data:image\/[a-z]+;base64,/', '', $base64) ?? $base64;

        try {
            $this->logoNormalizado = LogoRide::desdeBase64($base64);
        } catch (DatoInvalido $excepcion) {
            throw ValidationException::withMessages(['logo' => $excepcion->getMessage()]);
        }
    }

    public function logoNormalizado(): LogoRide
    {
        return $this->logoNormalizado;
    }
}
