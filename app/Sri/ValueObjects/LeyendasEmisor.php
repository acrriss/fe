<?php

namespace App\Sri\ValueObjects;

use App\Sri\Enums\RegimenRimpe;
use App\Sri\Exceptions\DatoInvalido;

/**
 * Designaciones del emisor que el SRI obliga a imprimir como leyenda en
 * cada comprobante (ficha técnica, Anexos 21, 22 y 24): son un atributo
 * del contribuyente, no de la transacción, así que se configuran una vez
 * y el pipeline las inyecta en cada emisión.
 *
 * Los formatos vienen de la ficha:
 * - `agenteRetencion`: número de la resolución, numérico, máximo 8 dígitos,
 *   "omitiendo los ceros a la izquierda" (Anexo 21).
 * - `contribuyenteEspecial`: número de la resolución, alfanumérico de 3 a
 *   13 caracteres (tag <contribuyenteEspecial> de cada formato XML).
 * - `regimenRimpe`: leyenda literal de RegimenRimpe (Anexo 22).
 * - `granContribuyente`: número de la resolución de calificación, que va
 *   como campo adicional (Anexo 24), no como tag propio.
 */
final readonly class LeyendasEmisor
{
    /**
     * Sin argumentos = emisor sin designaciones. Los valores se asumen ya
     * normalizados: lo que viene de fuera (panel, API) pasa por de().
     */
    public function __construct(
        public ?string $agenteRetencion = null,
        public ?string $contribuyenteEspecial = null,
        public ?RegimenRimpe $regimenRimpe = null,
        public ?string $granContribuyente = null,
    ) {}

    /**
     * Normaliza y valida; una cadena vacía equivale a "no designado".
     *
     * @throws DatoInvalido
     */
    public static function de(
        ?string $agenteRetencion,
        ?string $contribuyenteEspecial,
        ?string $regimenRimpe = null,
        ?string $granContribuyente = null,
    ): self {
        return new self(
            self::resolucionAgenteRetencion($agenteRetencion),
            self::resolucionContribuyenteEspecial($contribuyenteEspecial),
            self::regimenRimpe($regimenRimpe),
            self::resolucionGranContribuyente($granContribuyente),
        );
    }

    public function vacias(): bool
    {
        return $this->agenteRetencion === null
            && $this->contribuyenteEspecial === null
            && $this->regimenRimpe === null
            && $this->granContribuyente === null;
    }

    /**
     * Leyenda literal para <contribuyenteRimpe>, o null si no está en el régimen.
     */
    public function leyendaRimpe(): ?string
    {
        return $this->regimenRimpe?->leyenda();
    }

    public static function resolucionAgenteRetencion(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        $sinCeros = ltrim($valor, '0');

        if (preg_match('/^\d{1,8}$/', $sinCeros) !== 1) {
            throw DatoInvalido::porFormato(
                'agenteRetencion',
                'el número de la resolución (hasta 8 dígitos, sin ceros a la izquierda)',
                $valor,
            );
        }

        return $sinCeros;
    }

    /**
     * Resolución de calificación como Gran Contribuyente. La ficha solo la
     * acota a 300 caracteres alfanuméricos (el tope del campo adicional);
     * el ejemplo es «NAC-GCFOIOC21-00000868-E», así que se admiten guiones.
     */
    public static function resolucionGranContribuyente(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9\-]{3,300}$/', $valor) !== 1) {
            throw DatoInvalido::porFormato(
                'granContribuyente',
                'el número de la resolución (alfanumérico con guiones, de 3 a 300 caracteres)',
                $valor,
            );
        }

        return $valor;
    }

    public static function regimenRimpe(?string $valor): ?RegimenRimpe
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        return RegimenRimpe::tryFrom($valor) ?? throw DatoInvalido::porFormato(
            'regimenRimpe',
            'uno de: '.implode(', ', array_map(fn (RegimenRimpe $r): string => $r->value, RegimenRimpe::cases())),
            $valor,
        );
    }

    public static function resolucionContribuyenteEspecial(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9]{3,13}$/', $valor) !== 1) {
            throw DatoInvalido::porFormato(
                'contribuyenteEspecial',
                'el número de la resolución (alfanumérico de 3 a 13 caracteres)',
                $valor,
            );
        }

        return $valor;
    }
}
