<?php

namespace App\Sri\Firma;

use App\Sri\Contracts\XmlSigner;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\ValueObjects\CertificadoFirma;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Firma XAdES-BES delegando en el jar heredado (java).
 *
 * A diferencia del legado (exec con string interpolado y archivos globales
 * en public/), aquí cada firma usa archivos temporales propios con permisos
 * restrictivos, los argumentos van escapados por Process, y todo se limpia
 * al terminar.
 */
class JarXmlSigner implements XmlSigner
{
    public function firmar(string $xml, CertificadoFirma $certificado): string
    {
        $directorio = storage_path('app/firmador/tmp/'.Str::uuid());

        if (! mkdir($directorio, 0700, true)) {
            throw EmisionFallida::enFirma('no se pudo crear el directorio temporal.');
        }

        $rutaCertificado = "{$directorio}/certificado.p12";
        $rutaXml = "{$directorio}/comprobante.xml";
        $nombreFirmado = 'comprobante-firmado.xml';

        try {
            file_put_contents($rutaCertificado, $certificado->contenido);
            chmod($rutaCertificado, 0600);
            file_put_contents($rutaXml, $xml);

            $resultado = Process::timeout(config()->integer('sri.firmador.timeout'))->run([
                config()->string('sri.firmador.java'),
                '-jar',
                config()->string('sri.firmador.jar'),
                $rutaCertificado,
                $certificado->clave,
                $rutaXml,
                $directorio,
                $nombreFirmado,
            ]);

            if ($resultado->failed()) {
                throw EmisionFallida::enFirma(trim($resultado->errorOutput()) ?: 'el firmador terminó con error.');
            }

            $firmado = @file_get_contents("{$directorio}/{$nombreFirmado}");

            if ($firmado === false || $firmado === '') {
                throw EmisionFallida::enFirma('el firmador no produjo el XML firmado.');
            }

            return $firmado;
        } finally {
            @unlink($rutaCertificado);
            @unlink($rutaXml);
            @unlink("{$directorio}/{$nombreFirmado}");
            @rmdir($directorio);
        }
    }
}
