<?php

use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Exceptions\EmisionFallida;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\Pipeline\EmitirComprobante;
use App\Sri\ValueObjects\CertificadoFirma;
use App\Sri\ValueObjects\CodigoNumerico;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->signer = new FakeXmlSigner;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, $this->signer);
    config()->set('sri.autorizacion.espera_ms', 0);
});

function emision_de_factura(): EmisionEnCurso
{
    return new EmisionEnCurso(
        comprobante: FacturaData::from(payload_factura()),
        certificado: CertificadoFirma::desdeBase64(base64_encode('certificado-dummy'), 'secreto'),
        codigoNumerico: CodigoNumerico::fromString(CODIGO_NUMERICO_PRUEBA), // clave reproducible
    );
}

it('emite una factura de punta a punta encadenando clave, XML, firma, recepción y autorización', function () {
    $emision = app(EmitirComprobante::class)->emitir(emision_de_factura());

    // con el mismo payload y código numérico, la clave y el XML son los que
    // producen el value object y ConstruirXml por separado
    $claveEsperada = (string) clave_acceso_de_prueba('factura');
    $xmlEsperado = xml_de_prueba('factura');

    expect($emision->claveAcceso()->value)->toBe($claveEsperada)
        ->and($emision->xml)->toBe($xmlEsperado)
        ->and($emision->xmlFirmado())->toBe($xmlEsperado.'<!--firma-fake-->')
        // el gateway recibió el XML FIRMADO, no el original
        ->and($this->gateway->xmlRecibido)->toBe($emision->xmlFirmado())
        ->and((string) $this->gateway->claveConsultada)->toBe($claveEsperada)
        ->and($emision->recepcion?->recibida())->toBeTrue()
        ->and($emision->autorizacion?->autorizado())->toBeTrue();
});

it('aborta en recepción cuando el SRI devuelve el comprobante', function () {
    $this->gateway->devolverComprobantes();

    try {
        app(EmitirComprobante::class)->emitir(emision_de_factura());
        $this->fail('Debió lanzar EmisionFallida');
    } catch (EmisionFallida $fallo) {
        expect($fallo->etapa)->toBe('recepcion')
            ->and((string) $fallo->mensajes[0])->toContain('ERROR SECUENCIAL REGISTRADO')
            // nunca llegó a consultar la autorización
            ->and($this->gateway->claveConsultada)->toBeNull();
    }
});

it('aborta en autorización cuando el SRI rechaza el comprobante', function () {
    $this->gateway->rechazarAutorizacion();

    try {
        app(EmitirComprobante::class)->emitir(emision_de_factura());
        $this->fail('Debió lanzar EmisionFallida');
    } catch (EmisionFallida $fallo) {
        expect($fallo->etapa)->toBe('autorizacion')
            ->and((string) $fallo->mensajes[0])->toContain('CLAVE ACCESO REGISTRADA');
    }
});

it('genera un código numérico aleatorio cuando la emisión no fija uno', function () {
    $emision = new EmisionEnCurso(
        comprobante: FacturaData::from(payload_factura()),
        certificado: CertificadoFirma::desdeBase64(base64_encode('certificado-dummy'), 'secreto'),
    );

    app(EmitirComprobante::class)->emitir($emision);

    // los primeros 39 dígitos (fecha+codDoc+ruc+ambiente+serie+secuencial)
    // son deterministas; el código numérico varía por comprobante
    $claveConCodigoFijo = (string) clave_acceso_de_prueba('factura');

    expect($emision->claveAcceso()->value)
        ->toHaveLength(49)
        ->toStartWith(substr($claveConCodigoFijo, 0, 39));
});
