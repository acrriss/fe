<?php

namespace App\Sri\ValueObjects;

use App\Sri\Exceptions\DatoInvalido;

/**
 * Registro Único de Contribuyentes: 13 dígitos.
 */
final readonly class Ruc implements ValueObject
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): static
    {
        if (preg_match('/^\d{13}$/', $value) !== 1) {
            throw DatoInvalido::porFormato('ruc', 'una cadena de 13 dígitos', $value);
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
