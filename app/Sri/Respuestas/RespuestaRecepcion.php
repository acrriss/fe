<?php

namespace App\Sri\Respuestas;

/**
 * Resultado del web service de recepción: RECIBIDA o DEVUELTA.
 */
final readonly class RespuestaRecepcion
{
    /**
     * @param  array<int, MensajeSri>  $mensajes
     */
    public function __construct(
        public string $estado,
        public array $mensajes = [],
    ) {}

    public function recibida(): bool
    {
        return $this->estado === 'RECIBIDA';
    }
}
