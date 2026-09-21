<?php

namespace App\Http\Requests\Concerns;

use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\LeyendasEmisor;
use Illuminate\Validation\Validator;

/**
 * Campos de designación del emisor (ficha 2.34, Anexo 21 y Tabla 11) tal
 * como los reciben el panel y la API de partner: `agente_retencion_resolucion`
 * y `contribuyente_especial_resolucion`. El formato lo dicta LeyendasEmisor;
 * aquí solo se traduce su rechazo a un error de validación sobre el campo.
 */
trait ValidaLeyendasEmisor
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function reglasLeyendasEmisor(): array
    {
        return [
            'agente_retencion_resolucion' => ['sometimes', 'nullable', 'string', 'max:20'],
            'contribuyente_especial_resolucion' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ([
                    'agente_retencion_resolucion' => LeyendasEmisor::resolucionAgenteRetencion(...),
                    'contribuyente_especial_resolucion' => LeyendasEmisor::resolucionContribuyenteEspecial(...),
                ] as $campo => $normalizar) {
                    if (! $this->exists($campo)) {
                        continue;
                    }

                    try {
                        $normalizar($this->string($campo)->toString());
                    } catch (DatoInvalido $excepcion) {
                        $validator->errors()->add($campo, $excepcion->getMessage());
                    }
                }
            },
        ];
    }

    /**
     * Columnas a persistir, ya normalizadas; solo las que vienen en el
     * request (null explícito borra la designación).
     *
     * @return array<string, string|null>
     */
    public function leyendasEmisorValidadas(): array
    {
        $columnas = [];

        if ($this->exists('agente_retencion_resolucion')) {
            $columnas['agente_retencion_resolucion'] = LeyendasEmisor::resolucionAgenteRetencion(
                $this->string('agente_retencion_resolucion')->toString(),
            );
        }

        if ($this->exists('contribuyente_especial_resolucion')) {
            $columnas['contribuyente_especial_resolucion'] = LeyendasEmisor::resolucionContribuyenteEspecial(
                $this->string('contribuyente_especial_resolucion')->toString(),
            );
        }

        return $columnas;
    }
}
