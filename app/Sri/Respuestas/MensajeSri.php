<?php

namespace App\Sri\Respuestas;

/**
 * Mensaje informativo o de error devuelto por los web services del SRI.
 */
final readonly class MensajeSri
{
    public function __construct(
        public string $identificador,
        public string $mensaje,
        public ?string $tipo = null,
        public ?string $informacionAdicional = null,
    ) {}

    public function __toString(): string
    {
        return trim(sprintf(
            '(%s%s) %s%s',
            $this->tipo !== null ? "{$this->tipo} - " : '',
            $this->identificador,
            $this->mensaje,
            $this->informacionAdicional !== null ? "\n{$this->informacionAdicional}" : '',
        ));
    }
}
