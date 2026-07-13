<?php

namespace App\Sri\Enums;

/**
 * Ciclo de vida de una entrega de webhook: nace pendiente, y termina
 * entregada (2xx del receptor) o fallida (agotados los reintentos).
 */
enum EstadoEntregaWebhook: string
{
    case Pendiente = 'pendiente';
    case Entregada = 'entregada';
    case Fallida = 'fallida';
}
