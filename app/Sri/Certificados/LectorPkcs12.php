<?php

namespace App\Sri\Certificados;

use App\Sri\Exceptions\CertificadoInvalido;
use App\Sri\ValueObjects\CertificadoFirma;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Abre contenedores PKCS#12 (.p12) con su clave.
 *
 * Intenta primero con la extensión openssl de PHP; si el contenedor usa
 * algoritmos legacy (RC2/3DES, comunes en certificados ecuatorianos
 * antiguos que OpenSSL 3 ya no soporta por defecto), recurre al binario
 * `openssl` con reintento `-legacy`. La clave viaja al proceso hijo por
 * variable de entorno, nunca por argv.
 */
class LectorPkcs12
{
    public function abrir(CertificadoFirma $certificado): CertificadoAbierto
    {
        $abierto = $this->abrirNativo($certificado) ?? $this->abrirConBinario($certificado);

        if ($abierto === null) {
            throw CertificadoInvalido::noSePudoAbrir();
        }

        return $this->conMetadatos($abierto[0], $abierto[1]);
    }

    /**
     * @return array{string, string}|null [certificadoPem, clavePrivadaPem]
     */
    private function abrirNativo(CertificadoFirma $certificado): ?array
    {
        $resultado = [];

        if (openssl_pkcs12_read($certificado->contenido, $resultado, $certificado->clave) && is_array($resultado)) {
            $cert = $resultado['cert'] ?? null;
            $clave = $resultado['pkey'] ?? null;

            return is_string($cert) && is_string($clave) ? [$cert, $clave] : null;
        }

        $errores = $this->drenarErroresOpenssl();

        // el MAC solo falla con la clave equivocada; "unsupported" delata
        // algoritmos legacy y amerita el fallback al binario
        if (str_contains($errores, 'mac verify failure')) {
            throw CertificadoInvalido::claveIncorrecta();
        }

        return null;
    }

    /**
     * @return array{string, string}|null
     */
    private function abrirConBinario(CertificadoFirma $certificado): ?array
    {
        $binario = config()->string('sri.certificados.openssl');
        $ruta = storage_path('app/firmador/tmp/'.Str::uuid().'.p12');

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0700, true);
        }

        file_put_contents($ruta, $certificado->contenido);
        chmod($ruta, 0600);

        try {
            // sin -legacy primero (LibreSSL no conoce la opción y no la
            // necesita); con -legacy después (OpenSSL 3)
            foreach ([[], ['-legacy']] as $extra) {
                $resultado = Process::env(['SRI_P12_PASS' => $certificado->clave])->run([
                    $binario, 'pkcs12',
                    '-in', $ruta,
                    '-passin', 'env:SRI_P12_PASS',
                    '-nodes',
                    ...$extra,
                ]);

                if ($resultado->successful()) {
                    return $this->extraerPem($resultado->output());
                }

                if (preg_match('/invalid password|mac verify (error|failure)/i', $resultado->errorOutput()) === 1) {
                    throw CertificadoInvalido::claveIncorrecta();
                }
            }

            return null;
        } finally {
            @unlink($ruta);
        }
    }

    /**
     * @return array{string, string}|null
     */
    private function extraerPem(string $salida): ?array
    {
        preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $salida, $cert);
        preg_match('/-----BEGIN (?:RSA |ENCRYPTED )?PRIVATE KEY-----.*?-----END (?:RSA |ENCRYPTED )?PRIVATE KEY-----/s', $salida, $clave);

        if ($cert === [] || $clave === []) {
            return null;
        }

        return [$cert[0], $clave[0]];
    }

    private function conMetadatos(string $certificadoPem, string $clavePrivadaPem): CertificadoAbierto
    {
        $x509 = openssl_x509_parse($certificadoPem);

        if ($x509 === false) {
            throw CertificadoInvalido::noSePudoAbrir();
        }

        return new CertificadoAbierto(
            certificadoPem: $certificadoPem,
            clavePrivadaPem: $clavePrivadaPem,
            titular: self::texto(data_get($x509, 'subject.CN') ?? data_get($x509, 'name')),
            emisor: self::texto(data_get($x509, 'issuer.CN') ?? data_get($x509, 'issuer.O')),
            validoDesde: CarbonImmutable::createFromTimestamp(self::entero(data_get($x509, 'validFrom_time_t'))),
            validoHasta: CarbonImmutable::createFromTimestamp(self::entero(data_get($x509, 'validTo_time_t'))),
        );
    }

    private static function texto(mixed $valor): string
    {
        return is_string($valor) && $valor !== '' ? $valor : 'Desconocido';
    }

    private static function entero(mixed $valor): int
    {
        return is_int($valor) ? $valor : 0;
    }

    private function drenarErroresOpenssl(): string
    {
        $errores = [];

        while (($error = openssl_error_string()) !== false) {
            $errores[] = $error;
        }

        return implode(' | ', $errores);
    }
}
