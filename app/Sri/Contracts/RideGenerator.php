<?php

namespace App\Sri\Contracts;

use App\Models\Comprobante;
use App\Sri\Data\ComprobanteData;

/**
 * Generación de la representación impresa (RIDE) de un comprobante
 * autorizado, según el Anexo 2 de la ficha técnica del SRI.
 */
interface RideGenerator
{
    /**
     * @return string el PDF binario
     */
    public function generar(Comprobante $registro, ComprobanteData $comprobante): string;

    /**
     * Identifica el aspecto del RIDE que se generaría hoy para el registro
     * (plantilla y logo del emisor). El RIDE cacheado con otra huella está
     * desactualizado y se regenera.
     */
    public function huella(Comprobante $registro): string;
}
