<?php

namespace App\Sri\ValueObjects;

use App\Sri\Exceptions\DatoInvalido;

/**
 * Placa del vehículo con el que se prestó el servicio de transporte
 * comercial (ficha técnica 2.34, Anexo 25 §2, Tabla 33).
 *
 * La tabla contempla dos casos, ambos de tres letras y cuatro dígitos:
 * `ABC1234` y, cuando la placa solo tiene tres dígitos, `ABC0123` — «se
 * deberá colocar el cero sin ningún espacio antes de los mismos». Ese
 * relleno lo hace el servicio: es una regla de formato del SRI, no un dato
 * que el integrador deba recordar.
 *
 * No confundir con el `<placa>` de la guía de remisión ni con el de la
 * venta de combustibles (Anexo 16, Tabla 29), que admiten otros formatos y
 * viajan como texto libre.
 */
final readonly class Placa implements ValueObject
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): static
    {
        $normalizada = self::normalizar($value);

        if (preg_match('/^[A-Z]{3}[0-9]{4}$/', $normalizada) !== 1) {
            throw DatoInvalido::porFormato(
                'placa',
                'tres letras y tres o cuatro dígitos, sin espacios ni guiones (p. ej. ABC1234 o ABC0123)',
                $value,
            );
        }

        return new self($normalizada);
    }

    /**
     * Mayúsculas, sin separadores y con el cero de relleno de la Tabla 33.
     */
    private static function normalizar(string $value): string
    {
        $limpia = mb_strtoupper(preg_replace('/[\s\-]/u', '', $value) ?? '');

        if (preg_match('/^([A-Z]{3})([0-9]{3})$/', $limpia, $partes) === 1) {
            return $partes[1].'0'.$partes[2];
        }

        return $limpia;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
