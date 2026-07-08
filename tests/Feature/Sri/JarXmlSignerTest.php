<?php

use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Firma\JarXmlSigner;
use App\Sri\ValueObjects\CertificadoFirma;
use Symfony\Component\Process\ExecutableFinder;

/*
 * Integración real con el firmador sri.jar (requiere java). En máquinas
 * sin JRE la suite se salta estos tests sin fallar.
 */

function java_disponible(): bool
{
    $java = config()->string('sri.firmador.java');

    return str_contains($java, '/')
        ? is_executable($java)
        : new ExecutableFinder()->find($java) !== null;
}

function certificado_de_prueba(string $clave = 'clave-prueba'): CertificadoFirma
{
    return CertificadoFirma::desdeBase64(
        base64_encode((string) file_get_contents(base_path('tests/Fixtures/certificado-prueba.p12'))),
        $clave,
    );
}

describe('firma XAdES real con sri.jar', function () {
    beforeEach(function () {
        if (! java_disponible()) {
            $this->markTestSkipped('java no está disponible en esta máquina.');
        }
    });

    it('firma el XML golden y devuelve el documento con la firma embebida', function () {
        $xml = file_get_contents(golden_path('factura/comprobante.xml'));

        $firmado = new JarXmlSigner()->firmar($xml, certificado_de_prueba());

        expect($firmado)->toContain('<ds:Signature')
            ->toContain('<factura id="comprobante"')
            // el contenido original sigue intacto dentro del documento firmado
            ->toContain('<claveAcceso>'.trim(file_get_contents(golden_path('factura/claveAcceso.txt'))).'</claveAcceso>');
    });

    it('reporta con claridad una clave de certificado incorrecta', function () {
        $xml = file_get_contents(golden_path('factura/comprobante.xml'));

        try {
            new JarXmlSigner()->firmar($xml, certificado_de_prueba(clave: 'clave-equivocada'));
            $this->fail('Debió lanzar EmisionFallida');
        } catch (EmisionFallida $fallo) {
            expect($fallo->etapa)->toBe('firma')
                ->and($fallo->getMessage())->toContain('la clave del certificado no es correcta')
                // jamás debe filtrarse la clave en el mensaje
                ->and($fallo->getMessage())->not->toContain('clave-equivocada');
        }
    });

    it('reporta un certificado que no es un p12 válido', function () {
        $xml = file_get_contents(golden_path('factura/comprobante.xml'));
        $certificado = CertificadoFirma::desdeBase64(base64_encode('no-soy-un-p12'), 'x');

        try {
            new JarXmlSigner()->firmar($xml, $certificado);
            $this->fail('Debió lanzar EmisionFallida');
        } catch (EmisionFallida $fallo) {
            expect($fallo->etapa)->toBe('firma')
                ->and($fallo->getMessage())->toContain('el firmador reportó');
        }
    });
});
