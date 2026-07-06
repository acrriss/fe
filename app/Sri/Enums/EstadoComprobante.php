<?php

namespace App\Sri\Enums;

/**
 * Ciclo de vida de un comprobante dentro del microservicio
 * (columna `estado` de la tabla `comprobantes`).
 */
enum EstadoComprobante: string
{
    case Pendiente = 'pendiente';
    case Firmado = 'firmado';
    case Recibido = 'recibido';
    case Autorizado = 'autorizado';
    case Devuelto = 'devuelto';
    case NoAutorizado = 'no_autorizado';
    case Fallido = 'fallido';

    public function esFinal(): bool
    {
        return match ($this) {
            self::Autorizado, self::Devuelto, self::NoAutorizado, self::Fallido => true,
            default => false,
        };
    }
}
