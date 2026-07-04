<?php

namespace App\Sri\Data\Casts;

use App\Sri\ValueObjects\ValueObject;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Cast genérico de laravel-data hacia los value objects del dominio SRI.
 *
 * Uso: #[WithCast(ValueObjectCast::class, Ruc::class)]
 *
 * Un valor vacío se convierte en null (p. ej. la claveAcceso llega vacía
 * en el payload y la genera el servidor).
 */
final readonly class ValueObjectCast implements Cast
{
    /**
     * @param  class-string<ValueObject>  $valueObject
     */
    public function __construct(private string $valueObject) {}

    /**
     * @param  array<string, mixed>  $properties
     * @param  CreationContext<Data>  $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): ?ValueObject
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $this->valueObject) {
            return $value;
        }

        if (! is_string($value) && ! is_int($value)) {
            throw new \InvalidArgumentException(
                sprintf('No se puede construir %s desde %s.', $this->valueObject, get_debug_type($value)),
            );
        }

        return $this->valueObject::fromString((string) $value);
    }
}
