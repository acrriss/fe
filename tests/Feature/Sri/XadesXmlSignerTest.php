<?php

use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Firma\JarXmlSigner;
use App\Sri\Firma\VerificadorXades;
use App\Sri\Firma\XadesXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use App\Sri\Support\ComprobanteXmlParser;
use App\Sri\ValueObjects\CertificadoFirma;
use Illuminate\Support\Facades\Storage;

function firmador_nativo(): XadesXmlSigner
{
    return app(XadesXmlSigner::class);
}

it('produce una firma XAdES-BES que verifica al 100% (digests y firma)', function () {
    $xml = file_get_contents(golden_path('factura/comprobante.xml'));

    $firmado = firmador_nativo()->firmar($xml, certificado_de_prueba());

    $resultado = new VerificadorXades()->verificar($firmado);

    expect($resultado->esValida())->toBeTrue()
        ->and($resultado->referencias)->toHaveCount(3)
        ->and($resultado->referencias['#comprobante'])->toBeTrue();
});

it('replica la estructura de firma que el SRI acepta (paridad con el jar)', function () {
    $xml = file_get_contents(golden_path('factura/comprobante.xml'));

    $firmado = firmador_nativo()->firmar($xml, certificado_de_prueba());

    // los elementos y algoritmos exigidos por la ficha §6 y el Anexo 14
    expect($firmado)
        ->toContain('xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#"')
        ->toContain('Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"')
        ->toContain('Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"')
        ->toContain('Type="http://uri.etsi.org/01903#SignedProperties"')
        ->toContain('Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"')
        ->toContain('<etsi:SigningTime>')
        ->toContain('<etsi:MimeType>text/xml</etsi:MimeType>')
        ->toContain('<ds:X509Certificate>')
        ->toContain('<ds:RSAKeyValue>');
});

it('el contenido del comprobante queda intacto y parseable (roundtrip)', function () {
    $xml = file_get_contents(golden_path('factura/comprobante.xml'));

    $firmado = firmador_nativo()->firmar($xml, certificado_de_prueba());

    $comprobante = new ComprobanteXmlParser()->parse($firmado);

    expect($comprobante->infoFactura->importeTotal)->toBe('11.20')
        ->and((string) $comprobante->infoTributaria->claveAcceso)
        ->toBe(trim(file_get_contents(golden_path('factura/claveAcceso.txt'))));
});

it('reporta la clave incorrecta igual que el firmador jar', function () {
    $xml = file_get_contents(golden_path('factura/comprobante.xml'));

    try {
        firmador_nativo()->firmar($xml, certificado_de_prueba(clave: 'clave-equivocada'));
        $this->fail('Debió lanzar EmisionFallida');
    } catch (EmisionFallida $fallo) {
        expect($fallo->etapa)->toBe('firma')
            ->and($fallo->getMessage())->toContain('clave del certificado no es correcta')
            ->and($fallo->getMessage())->not->toContain('clave-equivocada');
    }
});

it('firma también con certificados .p12 legacy', function () {
    $xml = file_get_contents(golden_path('factura/comprobante.xml'));
    $certificado = CertificadoFirma::desdeBase64(base64_encode(p12_de_prueba(legacy: true)), 'clave-prueba');

    $firmado = firmador_nativo()->firmar($xml, $certificado);

    expect(new VerificadorXades()->verificar($firmado)->esValida())->toBeTrue();
});

describe('driver conmutable', function () {
    it('resuelve el firmador según config: :dataset', function (string $driver, string $clase) {
        config()->set('sri.firmador.driver', $driver);

        expect(app(XmlSigner::class))->toBeInstanceOf($clase);
    })->with([
        'nativo' => ['nativo', XadesXmlSigner::class],
        'jar' => ['jar', JarXmlSigner::class],
    ]);

    it('el pipeline completo emite con el firmador nativo y el SRI fake recibe una firma válida', function () {
        config()->set('sri.firmador.driver', 'nativo');
        config()->set('sri.autorizacion.espera_ms', 0);
        $gateway = new FakeSriGateway;
        $this->app->instance(SriGateway::class, $gateway);
        Storage::fake();

        $contribuyente = actuar_como_contribuyente();

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertSuccessful()
            ->assertJsonPath('emitido', true);

        // lo que viajó al SRI es un XML con firma XAdES íntegra
        expect(new VerificadorXades()->verificar((string) $gateway->xmlRecibido)->esValida())->toBeTrue();
    });
});
