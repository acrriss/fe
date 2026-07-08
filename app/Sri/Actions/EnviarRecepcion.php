<?php

namespace App\Sri\Actions;

use App\Sri\Contracts\SriGateway;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionEnCurso;
use Closure;

/**
 * Envía el XML firmado al web service de recepción. Si el SRI lo devuelve,
 * la emisión termina aquí con los mensajes de rechazo.
 */
final class EnviarRecepcion
{
    public function __construct(private readonly SriGateway $gateway) {}

    public function __invoke(EmisionEnCurso $emision, Closure $next): mixed
    {
        $emision->recepcion = $this->gateway->recibir(
            $emision->xmlFirmado(),
            $emision->comprobante->infoTributaria->ambiente,
        );

        if (! $emision->recepcion->recibida()) {
            throw EmisionFallida::comprobanteDevuelto($emision->recepcion);
        }

        return $next($emision);
    }
}
