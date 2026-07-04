<?php

namespace App\Sri\ValueObjects;

use App\Sri\Exceptions\DatoInvalido;

/**
 * Código numérico de la clave de acceso: 8 dígitos elegidos por el emisor.
 *
 * El legado lo tenía hardcodeado en "22568496"; el nuevo dominio lo genera
 * aleatorio por comprobante (recomendación del SRI) o lo acepta explícito.
 */
final readonly class CodigoNumerico implements ValueObject
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): static
    {
        if (preg_match('/^\d{8}$/', $value) !== 1) {
            throw DatoInvalido::porFormato('codigoNumerico', 'una cadena de 8 dígitos', $value);
        }

        return new self($value);
    }

    public static function aleatorio(): self
    {
        return new self(str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
