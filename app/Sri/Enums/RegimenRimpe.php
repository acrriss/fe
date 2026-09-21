<?php

namespace App\Sri\Enums;

/**
 * Régimen RIMPE del emisor (ficha técnica, Anexo 22). El valor es la clave
 * con la que viaja por la API y se guarda en BD; la leyenda es el texto
 * literal que exige el SRI en <contribuyenteRimpe> y en el RIDE.
 */
enum RegimenRimpe: string
{
    case Rimpe = 'rimpe';
    case NegocioPopular = 'negocio_popular';

    /**
     * Texto exacto de la ficha (27 y 45 caracteres, espacios incluidos):
     * cualquier variación deja de cumplir.
     */
    public function leyenda(): string
    {
        return match ($this) {
            self::Rimpe => 'CONTRIBUYENTE RÉGIMEN RIMPE',
            self::NegocioPopular => 'CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Rimpe => 'RIMPE (emprendedor)',
            self::NegocioPopular => 'RIMPE negocio popular',
        };
    }
}
