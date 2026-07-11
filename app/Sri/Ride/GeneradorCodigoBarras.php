<?php

namespace App\Sri\Ride;

use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Genera el código de barras Code 128 de la clave de acceso para el RIDE
 * (opcional según la ficha del SRI §9.20), como data-uri SVG embebible en
 * el PDF (dompdf lo rasteriza sin salir a buscar recursos externos).
 */
class GeneradorCodigoBarras
{
    public function svgDataUri(string $claveAcceso, int $alto = 45): string
    {
        $svg = new BarcodeGeneratorSVG()->getBarcode(
            $claveAcceso,
            BarcodeGeneratorSVG::TYPE_CODE_128,
            widthFactor: 1,
            height: $alto,
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
