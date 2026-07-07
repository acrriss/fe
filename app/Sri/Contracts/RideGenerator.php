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
}
