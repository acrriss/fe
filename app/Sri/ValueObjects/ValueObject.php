<?php

namespace App\Sri\ValueObjects;

use Stringable;

/**
 * Contrato común de los value objects del dominio SRI: se construyen desde
 * su representación en string (como viaja en el payload y el XML) y se
 * serializan de vuelta con __toString().
 */
interface ValueObject extends Stringable
{
    public static function fromString(string $value): static;
}
