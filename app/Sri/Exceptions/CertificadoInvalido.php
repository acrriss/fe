<?php

namespace App\Sri\Exceptions;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * El certificado de firma no puede usarse: la clave es incorrecta, el
 * archivo no es un PKCS#12 válido, o está vencido.
 */
final class CertificadoInvalido extends RuntimeException
{
    public static function claveIncorrecta(): self
    {
        return new self('La clave del certificado no es correcta.');
    }

    public static function noSePudoAbrir(): self
    {
        return new self('El archivo no es un certificado .p12 válido.');
    }

    public static function vencido(CarbonImmutable $validoHasta): self
    {
        return new self("El certificado está vencido desde el {$validoHasta->format('d/m/Y')}.");
    }
}
