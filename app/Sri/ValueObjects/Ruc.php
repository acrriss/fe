<?php

namespace App\Sri\ValueObjects;

use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Support\ValidadorIdentificacion;

/**
 * Registro Único de Contribuyentes: 13 dígitos con dígito verificador
 * válido (módulo 10 para persona natural, módulo 11 para sociedades).
 */
final readonly class Ruc implements ValueObject
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): static
    {
        ValidadorIdentificacion::validar(TipoIdentificacion::Ruc, $value, 'ruc');

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
