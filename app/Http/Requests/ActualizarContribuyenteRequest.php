<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaLeyendasEmisor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición parcial de un contribuyente gestionado (§11, 7d): solo se tocan
 * las claves presentes en el request (null explícito borra el valor).
 */
class ActualizarContribuyenteRequest extends FormRequest
{
    use ValidaLeyendasEmisor;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'razon_social' => ['sometimes', 'string', 'max:255'],
            'nombre_comercial' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dir_matriz' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limite_mensual' => ['sometimes', 'nullable', 'integer', 'min:1'],
            ...$this->reglasLeyendasEmisor(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosPresentes(): array
    {
        $datos = [];

        foreach (['razon_social', 'nombre_comercial', 'dir_matriz', 'limite_mensual'] as $campo) {
            if ($this->exists($campo)) {
                $datos[$campo] = $this->input($campo);
            }
        }

        return [...$datos, ...$this->leyendasEmisorValidadas()];
    }
}
