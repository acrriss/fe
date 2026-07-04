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

    /**
     * Elimina las entradas null preservando el orden (los elementos
     * opcionales del XML simplemente se omiten).
     *
     * @template TValor
     *
     * @param  array<string, TValor|null>  $valores
     * @return array<string, TValor>
     */
    public static function sinNulos(array $valores): array
    {
        return array_filter($valores, fn (mixed $valor): bool => $valor !== null);
    }
}
