<?php

namespace App\Http\Requests;

use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\ValueObjects\CertificadoFirma;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'info.p12' => ['required', 'string'],
            'info.clavep12' => ['required', 'string'],
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

    public function comprobante(): ComprobanteData
    {
        $dataClass = self::DATA_POR_TIPO[$this->string('tipo')->toString()];

        return $dataClass::from($this->validated('comprobante'));
    }

    public function certificado(): CertificadoFirma
    {
        return CertificadoFirma::desdeBase64(
            $this->string('info.p12')->toString(),
            $this->string('info.clavep12')->toString(),
        );
    }
}
