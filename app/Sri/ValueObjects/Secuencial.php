<?php

namespace App\Sri\ValueObjects;

use App\Sri\Exceptions\DatoInvalido;

/**
 * Secuencial del comprobante: 9 dígitos con ceros a la izquierda.
 */
final readonly class Secuencial implements ValueObject
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): static
    {
        if (preg_match('/^\d{9}$/', $value) !== 1) {
            throw DatoInvalido::porFormato('secuencial', 'una cadena de 9 dígitos', $value);
        }

        return new self($value);
    }

    public static function fromInt(int $numero): self
    {
        if ($numero < 1 || $numero > 999_999_999) {
            throw DatoInvalido::porFormato('secuencial', 'un entero entre 1 y 999999999', (string) $numero);
        }

        return new self(str_pad((string) $numero, 9, '0', STR_PAD_LEFT));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
