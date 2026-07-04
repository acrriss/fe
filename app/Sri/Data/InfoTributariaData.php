<?php

namespace App\Sri\Data;

use App\Sri\Data\Casts\ValueObjectCast;
use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Enums\TipoEmision;
use App\Sri\Support\Payload;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Bloque <infoTributaria>, común a todos los comprobantes.
 *
 * Nota: el `codDoc` del payload se IGNORA deliberadamente — siempre se
 * deriva del tipo del comprobante (TipoComprobante). La claveAcceso llega
 * vacía y la genera el servidor durante el pipeline de emisión.
 */
final class InfoTributariaData extends Data
{
    public function __construct(
        public Ambiente $ambiente,
        public TipoEmision $tipoEmision,
        public string $razonSocial,
        #[WithCast(ValueObjectCast::class, Ruc::class)]
        public Ruc $ruc,
        public string $estab,
        public string $ptoEmi,
        #[WithCast(ValueObjectCast::class, Secuencial::class)]
        public Secuencial $secuencial,
        public string $dirMatriz,
        public ?string $nombreComercial = null,
        #[WithCast(ValueObjectCast::class, ClaveAcceso::class)]
        public ?ClaveAcceso $claveAcceso = null,
    ) {}

    /**
     * Bloque <infoTributaria> en el orden de la ficha del SRI. El codDoc
     * viene del tipo del comprobante y la claveAcceso debe existir ya.
     *
     * @return array<string, string>
     */
    public function xmlArray(TipoComprobante $tipo): array
    {
        if (! $this->claveAcceso instanceof ClaveAcceso) {
            throw new \LogicException('La claveAcceso debe generarse antes de construir el XML.');
        }

        return Payload::sinNulos([
            'ambiente' => $this->ambiente->value,
            'tipoEmision' => $this->tipoEmision->value,
            'razonSocial' => $this->razonSocial,
            'nombreComercial' => $this->nombreComercial,
            'ruc' => (string) $this->ruc,
            'claveAcceso' => (string) $this->claveAcceso,
            'codDoc' => $tipo->value,
            'estab' => $this->estab,
            'ptoEmi' => $this->ptoEmi,
            'secuencial' => (string) $this->secuencial,
            'dirMatriz' => $this->dirMatriz,
        ]);
    }
}
