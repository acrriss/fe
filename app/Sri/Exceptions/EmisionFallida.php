<?php

namespace App\Sri\Exceptions;

use App\Sri\Respuestas\MensajeSri;
use App\Sri\Respuestas\RespuestaAutorizacion;
use App\Sri\Respuestas\RespuestaRecepcion;
use RuntimeException;

/**
 * La emisión no pudo completarse. Transporta los mensajes del SRI (o del
 * firmador) para que la capa HTTP los devuelva al consumidor.
 */
final class EmisionFallida extends RuntimeException
{
    /**
     * @param  array<int, MensajeSri>  $mensajes
     */
    private function __construct(
        string $message,
        public readonly string $etapa,
        public readonly array $mensajes = [],
    ) {
        parent::__construct($message);
    }

    public static function enFirma(string $detalle): self
    {
        return new self("La firma del comprobante falló: {$detalle}", 'firma');
    }

    public static function comprobanteDevuelto(RespuestaRecepcion $respuesta): self
    {
        return new self('El SRI devolvió el comprobante en recepción.', 'recepcion', $respuesta->mensajes);
    }

    public static function comprobanteNoAutorizado(RespuestaAutorizacion $respuesta): self
    {
        return new self(
            "El comprobante no fue autorizado (estado: {$respuesta->estado}).",
            'autorizacion',
            $respuesta->mensajes,
        );
    }

    public static function sinRespuestaDeAutorizacion(int $intentos): self
    {
        return new self(
            "El SRI no resolvió la autorización tras {$intentos} intentos; consulte más tarde con la clave de acceso.",
            'autorizacion',
        );
    }
}
