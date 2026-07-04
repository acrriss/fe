<?php

namespace App\Sri\Support;

/**
 * Utilidades para normalizar el payload de entrada.
 */
final class Payload
{
    /**
     * El XML del SRI (y el payload heredado que lo refleja) representa las
     * colecciones como `{ wrapper: { item: X } }` donde X puede ser un solo
     * objeto o una lista. Esto las normaliza siempre a lista.
     *
     * @return array<int, mixed>
     */
    public static function lista(mixed $valor): array
    {
        if ($valor === null || $valor === []) {
            return [];
        }

        if (is_array($valor) && array_is_list($valor)) {
            return $valor;
        }

        return [$valor];
    }
}
