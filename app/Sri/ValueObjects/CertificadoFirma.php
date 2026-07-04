<?php

namespace App\Sri\ValueObjects;

use App\Sri\Exceptions\DatoInvalido;
use SensitiveParameter;

/**
 * Certificado de firma electrónica (.p12) con su contraseña.
 *
 * Vive solo en memoria durante la emisión: a diferencia del legado (que lo
 * escribía en public/p12/active.p12, compartido entre peticiones), cada
 * emisión trabaja con su propio certificado y nunca toca un archivo global.
 */
final readonly class CertificadoFirma
{
    private function __construct(
        public string $contenido,
        #[SensitiveParameter] public string $clave,
    ) {}

    public static function desdeBase64(string $base64, #[SensitiveParameter] string $clave): self
    {
        $contenido = base64_decode($base64, true);

        if ($contenido === false || $contenido === '') {
            throw DatoInvalido::porFormato('p12', 'un certificado codificado en base64', '<binario>');
        }

        return new self($contenido, $clave);
    }
}
