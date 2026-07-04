<?php

namespace App\Sri\Contracts;

use App\Sri\Enums\Ambiente;
use App\Sri\Respuestas\RespuestaAutorizacion;
use App\Sri\Respuestas\RespuestaRecepcion;
use App\Sri\ValueObjects\ClaveAcceso;

/**
 * Puerta de enlace hacia los web services de comprobantes electrónicos del
 * SRI. La implementación real habla SOAP; la fake permite testear el flujo
 * completo sin tocar los servidores del SRI.
 */
interface SriGateway
{
    public function recibir(string $xmlFirmado, Ambiente $ambiente): RespuestaRecepcion;

    public function autorizar(ClaveAcceso $claveAcceso, Ambiente $ambiente): RespuestaAutorizacion;
}
