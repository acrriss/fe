<?php

namespace App\Sri\Ride;

use App\Models\Comprobante;
use App\Sri\Contracts\RideGenerator;
use App\Sri\Data\ComprobanteData;
use App\Sri\Enums\TipoComprobante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * RIDE en PDF con dompdf (puro PHP, sin binarios externos), desde
 * plantillas Blade según el Anexo 2 de la ficha del SRI.
 */
class DompdfRideGenerator implements RideGenerator
{
    public function __construct(private readonly GeneradorCodigoBarras $codigoBarras) {}

    public function generar(Comprobante $registro, ComprobanteData $comprobante): string
    {
        $vista = match ($comprobante::tipo()) {
            TipoComprobante::Factura => 'ride.factura',
            TipoComprobante::NotaCredito => 'ride.nota-credito',
            TipoComprobante::NotaDebito => 'ride.nota-debito',
            TipoComprobante::ComprobanteRetencion => 'ride.retencion',
            TipoComprobante::GuiaRemision => 'ride.guia-remision',
            TipoComprobante::LiquidacionCompra => 'ride.liquidacion',
        };

        // dompdf trae el subsetting de fuentes activado, pero
        // barryvdh/laravel-dompdf lo apaga en su config y manda su default:
        // sin esto, cada RIDE incrusta DejaVu Sans ENTERA y pesa ~863 KB en
        // vez de ~29 KB. El RIDE viaja adjunto en cada correo al comprador,
        // así que son 30x de ancho de banda por factura emitida.
        return Pdf::setOption('enable_font_subsetting', true)
            ->loadView($vista, [
                'registro' => $registro,
                'comprobante' => $comprobante,
                'logo' => $this->logoComoDataUri($registro),
                'codigoBarras' => $registro->clave_acceso !== null
                    ? $this->codigoBarras->svgDataUri($registro->clave_acceso)
                    : null,
            ])->output();
    }

    /**
     * El logo del contribuyente, embebido como data-uri (dompdf no debe
     * salir a buscar archivos).
     */
    private function logoComoDataUri(Comprobante $registro): ?string
    {
        $logoPath = $registro->contribuyente?->logo_path;

        if ($logoPath === null || ! Storage::exists($logoPath)) {
            return null;
        }

        $extension = pathinfo($logoPath, PATHINFO_EXTENSION) ?: 'png';

        return "data:image/{$extension};base64,".base64_encode((string) Storage::get($logoPath));
    }
}
