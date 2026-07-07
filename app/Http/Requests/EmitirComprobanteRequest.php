<?php

namespace App\Http\Requests;

use App\Models\Contribuyente;
use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Exceptions\DatoInvalido;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Exceptions\CannotCastDate;
use Spatie\LaravelData\Exceptions\CannotCastEnum;
use Spatie\LaravelData\Exceptions\CannotCreateData;

/**
 * Emisión de un comprobante. Mantiene el contrato del payload heredado:
 * la primera clave identifica el tipo (factura, notaCredito…). El
 * certificado de firma ya no viaja en el payload: vive cifrado en el
 * contribuyente autenticado.
 */
class EmitirComprobanteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(self::DATA_POR_TIPO))],
            // sin reglas anidadas a propósito: la estructura interna la
            // valida el DTO tipado (ComprobanteData::from) con sus casts
            'comprobante' => ['required', 'array'],
        ];
    }

    /**
     * Mapa rootElement → clase de DTO.
     *
     * @var array<string, class-string<ComprobanteData>>
     */
    private const array DATA_POR_TIPO = [
        'factura' => FacturaData::class,
        'notaCredito' => NotaCreditoData::class,
        'comprobanteRetencion' => ComprobanteRetencionData::class,
    ];

    /**
     * Normaliza el payload heredado ({factura: {...}}) a la forma interna
     * {tipo, comprobante} antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $tipo = collect(array_keys(self::DATA_POR_TIPO))
            ->first(fn (string $rootElement): bool => $this->has($rootElement));

        if ($tipo !== null) {
            $this->merge([
                'tipo' => $tipo,
                'comprobante' => $this->input($tipo),
            ]);
        }
    }

    /**
     * El RUC del comprobante debe ser el del contribuyente autenticado:
     * nadie emite a nombre de otro.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $contribuyente = $this->contribuyente();
                $ruc = $this->input('comprobante.infoTributaria.ruc');

                if ($contribuyente !== null && $ruc !== $contribuyente->ruc) {
                    $validator->errors()->add(
                        'comprobante.infoTributaria.ruc',
                        'El RUC del comprobante no corresponde al contribuyente autenticado.',
                    );
                }
            },
        ];
    }

    public function contribuyente(): ?Contribuyente
    {
        return $this->user()?->contribuyente;
    }

    public function tipoComprobante(): TipoComprobante
    {
        return TipoComprobante::fromRootElement($this->string('tipo')->toString());
    }

    /**
     * Construye el DTO tipado. Cualquier violación del contrato (campo
     * faltante, enum desconocido, fecha malformada, RUC inválido…) se
     * reporta como error de validación 422, nunca como error 500.
     */
    public function comprobante(): ComprobanteData
    {
        $dataClass = self::DATA_POR_TIPO[$this->string('tipo')->toString()];

        try {
            return $dataClass::from($this->validated('comprobante'));
        } catch (DatoInvalido|CannotCreateData|CannotCastEnum|CannotCastDate|InvalidFormatException $excepcion) {
            throw ValidationException::withMessages([
                'comprobante' => $excepcion->getMessage(),
            ]);
        }
    }
}
