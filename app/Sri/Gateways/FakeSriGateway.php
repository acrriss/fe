<?php

namespace App\Sri\Gateways;

use App\Sri\Contracts\SriGateway;
use App\Sri\Enums\Ambiente;
use App\Sri\Respuestas\MensajeSri;
use App\Sri\Respuestas\RespuestaAutorizacion;
use App\Sri\Respuestas\RespuestaRecepcion;
use App\Sri\ValueObjects\ClaveAcceso;
use Carbon\CarbonImmutable;

/**
 * Doble de pruebas del SRI: autoriza todo por defecto y permite simular
 * devoluciones o rechazos. Registra lo que recibe para las aserciones.
 */
final class FakeSriGateway implements SriGateway
{
    public ?string $xmlRecibido = null;

    public ?ClaveAcceso $claveConsultada = null;

    private bool $devolver = false;

    private bool $rechazar = false;

    public function devolverComprobantes(): self
    {
        $this->devolver = true;

        return $this;
    }

    public function rechazarAutorizacion(): self
    {
        $this->rechazar = true;

        return $this;
    }

    public function recibir(string $xmlFirmado, Ambiente $ambiente): RespuestaRecepcion
    {
        $this->xmlRecibido = $xmlFirmado;

        if ($this->devolver) {
            return new RespuestaRecepcion('DEVUELTA', [
                new MensajeSri('45', 'ERROR SECUENCIAL REGISTRADO', 'ERROR'),
            ]);
        }

        return new RespuestaRecepcion('RECIBIDA');
    }

    public function autorizar(ClaveAcceso $claveAcceso, Ambiente $ambiente): RespuestaAutorizacion
    {
        $this->claveConsultada = $claveAcceso;

        if ($this->rechazar) {
            return new RespuestaAutorizacion('NO AUTORIZADO', mensajes: [
                new MensajeSri('60', 'CLAVE ACCESO REGISTRADA', 'ERROR'),
            ]);
        }

        return new RespuestaAutorizacion(
            estado: 'AUTORIZADO',
            numeroAutorizacion: (string) $claveAcceso,
            fechaAutorizacion: CarbonImmutable::now(),
        );
    }
}
