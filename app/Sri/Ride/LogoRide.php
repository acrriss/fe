<?php

namespace App\Sri\Ride;

use App\Sri\Exceptions\DatoInvalido;

/**
 * Logo del emisor ya normalizado al formato del RIDE. Solo se construye
 * pasando por {@see NormalizadorLogo}: quien reciba un LogoRide sabe que
 * es un PNG de 600 × 300, sin volver a comprobarlo.
 */
final readonly class LogoRide
{
    private function __construct(public string $png) {}

    /**
     * @throws DatoInvalido si no es una imagen PNG, JPEG o WebP utilizable
     */
    public static function desdeImagen(string $contenido): self
    {
        return new self(app(NormalizadorLogo::class)->normalizar($contenido));
    }

    /**
     * @throws DatoInvalido si el base64 es inválido o no es una imagen utilizable
     */
    public static function desdeBase64(string $base64): self
    {
        $contenido = base64_decode($base64, true);

        if ($contenido === false) {
            throw DatoInvalido::porFormato('logo', 'una imagen codificada en base64', '<binario>');
        }

        return self::desdeImagen($contenido);
    }
}
