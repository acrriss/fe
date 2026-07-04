<?php

namespace App\Sri\Respuestas;

use Carbon\CarbonImmutable;

/**
 * Resultado del web service de autorización.
 */
final readonly class RespuestaAutorizacion
{
    /**
     * @param  array<int, MensajeSri>  $mensajes
     */
    public function __construct(
        public string $estado,
        public ?string $numeroAutorizacion = null,
        public ?CarbonImmutable $fechaAutorizacion = null,
        public array $mensajes = [],
    ) {}

    public function autorizado(): bool
    {
        return $this->estado === 'AUTORIZADO';
    }

    /**
     * El SRI puede responder sin autorizaciones aún (en procesamiento):
     * en ese caso conviene reintentar la consulta.
     */
    public function enProcesamiento(): bool
    {
        return $this->estado === 'EN PROCESO' || $this->estado === '';
    }
}
