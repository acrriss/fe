<?php

namespace App\Http\Requests;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\CertificadoFirma;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Exceptions\CannotCastDate;
use Spatie\LaravelData\Exceptions\CannotCastEnum;
use Spatie\LaravelData\Exceptions\CannotCreateData;

/**
 * Emisión de un comprobante. Mantiene el contrato del payload heredado:
 * la primera clave identifica el tipo (factura, notaCredito…) y `info`
 * transporta el certificado (p12 base64 + clave) del emisor.
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
            'info' => ['required', 'array'],
            // un .p12 real pesa unos pocos KB; 120 000 caracteres base64
            // (~90 KB) es un techo holgado que corta payloads abusivos
            'info.p12' => ['required', 'string', 'max:120000'],
            'info.clavep12' => ['required', 'string', 'max:255'],
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
     * Normaliza el payload heredado ({factura: {...}, info: {...}}) a la
     * forma interna {tipo, comprobante, info} antes de validar.
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

    public function certificado(): CertificadoFirma
    {
        try {
            return CertificadoFirma::desdeBase64(
                $this->string('info.p12')->toString(),
                $this->string('info.clavep12')->toString(),
            );
        } catch (DatoInvalido $excepcion) {
            throw ValidationException::withMessages([
                'info.p12' => $excepcion->getMessage(),
            ]);
        }
    }
}
