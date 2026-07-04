<?php

namespace App\Sri\ValueObjects;

use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Enums\TipoEmision;
use App\Sri\Exceptions\DatoInvalido;
use DateTimeInterface;

/**
 * Clave de acceso del comprobante electrónico: 49 dígitos.
 *
 * Estructura (ficha técnica del SRI):
 *
 *   fecha (ddmmaaaa) + codDoc (2) + ruc (13) + ambiente (1) + estab (3)
 *   + ptoEmi (3) + secuencial (9) + código numérico (8) + tipoEmision (1)
 *   + dígito verificador módulo 11 (1)
 *
 * El algoritmo está verificado contra los fixtures golden-master del legado
 * (fixtures/golden) y contra la ficha técnica del SRI.
 */
final readonly class ClaveAcceso implements ValueObject
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): static
    {
        if (preg_match('/^\d{49}$/', $value) !== 1) {
            throw DatoInvalido::porFormato('claveAcceso', 'una cadena de 49 dígitos', $value);
        }

        $esperado = self::digitoVerificador(substr($value, 0, 48));

        if ((int) $value[48] !== $esperado) {
            throw DatoInvalido::porFormato(
                'claveAcceso',
                "una clave con dígito verificador {$esperado}",
                $value,
            );
        }

        return new self($value);
    }

    public static function generar(
        DateTimeInterface $fechaEmision,
        TipoComprobante $tipoComprobante,
        Ruc $ruc,
        Ambiente $ambiente,
        string $establecimiento,
        string $puntoEmision,
        Secuencial $secuencial,
        CodigoNumerico $codigoNumerico,
        TipoEmision $tipoEmision = TipoEmision::Normal,
    ): self {
        self::assertSerie($establecimiento, 'establecimiento');
        self::assertSerie($puntoEmision, 'puntoEmision');

        $cadena = $fechaEmision->format('dmY')
            .$tipoComprobante->value
            .$ruc
            .$ambiente->value
            .$establecimiento
            .$puntoEmision
            .$secuencial
            .$codigoNumerico
            .$tipoEmision->value;

        return new self($cadena.self::digitoVerificador($cadena));
    }

    /**
     * Módulo 11: pesos 2..7 de derecha a izquierda; 11 - (total % 11);
     * los casos especiales 11 → 0 y 10 → 1.
     */
    public static function digitoVerificador(string $cadena): int
    {
        if (preg_match('/^\d+$/', $cadena) !== 1) {
            throw DatoInvalido::porFormato('claveAcceso', 'una cadena numérica', $cadena);
        }

        $peso = 2;
        $total = 0;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $total += (int) $cadena[$i] * $peso;
            $peso = $peso === 7 ? 2 : $peso + 1;
        }

        $verificador = 11 - ($total % 11);

        return match ($verificador) {
            11 => 0,
            10 => 1,
            default => $verificador,
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function assertSerie(string $valor, string $campo): void
    {
        if (preg_match('/^\d{3}$/', $valor) !== 1) {
            throw DatoInvalido::porFormato($campo, 'una cadena de 3 dígitos', $valor);
        }
    }
}
