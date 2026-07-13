<?php

namespace App\Sri\Enums;

/**
 * Ciclo de vida de una solicitud de vinculación (§11, 7d): el partner la
 * crea pendiente y el dueño de la cuenta directa la resuelve en su panel.
 */
enum EstadoVinculacion: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
}
