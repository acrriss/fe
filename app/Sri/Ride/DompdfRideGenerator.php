<?php

namespace App\Sri\Ride;

use App\Models\Comprobante;
use App\Sri\Contracts\RideGenerator;
use App\Sri\Data\ComprobanteData;
use App\Sri\Enums\TipoComprobante;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * RIDE en PDF con dompdf (puro PHP, sin binarios externos), desde
 * plantillas Blade según el Anexo 2 de la ficha del SRI.
 */
class DompdfRideGenerator implements RideGenerator
{
    public function generar(Comprobante $registro, ComprobanteData $comprobante): string
    {
        $vista = match ($comprobante::tipo()) {
            TipoComprobante::Factura => 'ride.factura',
            TipoComprobante::NotaCredito => 'ride.nota-credito',
            TipoComprobante::ComprobanteRetencion => 'ride.retencion',
            default => throw new \InvalidArgumentException(
                "No hay plantilla RIDE para {$comprobante::tipo()->rootElement()}.",
            ),
        };

        return Pdf::loadView($vista, [
            'registro' => $registro,
            'comprobante' => $comprobante,
        ])->output();
    }
}
