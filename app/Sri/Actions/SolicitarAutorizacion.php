<?php

namespace App\Sri\Actions;

use App\Sri\Contracts\SriGateway;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Pipeline\EmisionComprobante;
use Closure;

/**
 * Consulta la autorización del comprobante recibido. El SRI no autoriza al
 * instante: se reintenta con una espera entre consultas (el legado dormía
 * 5 s fijos y consultaba una sola vez).
 */
final class SolicitarAutorizacion
{
    public function __construct(private readonly SriGateway $gateway) {}

    public function __invoke(EmisionComprobante $emision, Closure $next): mixed
    {
        $intentos = max(1, config()->integer('sri.autorizacion.intentos'));
        $esperaMs = max(0, config()->integer('sri.autorizacion.espera_ms'));

        foreach (range(1, $intentos) as $intento) {
            $respuesta = $this->gateway->autorizar(
                $emision->claveAcceso(),
                $emision->comprobante->infoTributaria->ambiente,
            );

            if (! $respuesta->enProcesamiento()) {
                if (! $respuesta->autorizado()) {
                    throw EmisionFallida::comprobanteNoAutorizado($respuesta);
                }

                $emision->autorizacion = $respuesta;

                return $next($emision);
            }

            if ($intento < $intentos) {
                usleep($esperaMs * 1000);
            }
        }

        throw EmisionFallida::sinRespuestaDeAutorizacion($intentos);
    }
}
