<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaLeyendasEmisor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos editables del contribuyente desde el panel: identificación y
 * designaciones del SRI que salen como leyenda en cada comprobante.
 */
class ActualizarConfiguracionRequest extends FormRequest
{
    use ValidaLeyendasEmisor;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['nullable', 'string', 'max:300'],
            'dir_matriz' => ['nullable', 'string', 'max:300'],
            ...$this->reglasLeyendasEmisor(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function datosContribuyente(): array
    {
        return [
            'razon_social' => $this->string('razon_social')->toString(),
            'nombre_comercial' => $this->string('nombre_comercial')->toString() ?: null,
            'dir_matriz' => $this->string('dir_matriz')->toString() ?: null,
            ...$this->leyendasEmisorValidadas(),
        ];
    }
}
