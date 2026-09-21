<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidaLeyendasEmisor;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\Ruc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Alta de un contribuyente gestionado por un partner (§11).
 */
class AprovisionarContribuyenteRequest extends FormRequest
{
    use ValidaLeyendasEmisor;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ruc' => ['required', 'string'],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'dir_matriz' => ['nullable', 'string', 'max:255'],
            'limite_mensual' => ['nullable', 'integer', 'min:1'],
            ...$this->reglasLeyendasEmisor(),
        ];
    }

    public function ruc(): string
    {
        try {
            return (string) Ruc::fromString($this->string('ruc')->toString());
        } catch (DatoInvalido $excepcion) {
            throw ValidationException::withMessages(['ruc' => $excepcion->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function datosContribuyente(): array
    {
        return [
            'ruc' => $this->ruc(),
            'razon_social' => $this->string('razon_social')->toString(),
            'nombre_comercial' => $this->input('nombre_comercial'),
            'dir_matriz' => $this->input('dir_matriz'),
            'limite_mensual' => $this->input('limite_mensual'),
            ...$this->leyendasEmisorValidadas(),
        ];
    }
}
