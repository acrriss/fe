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

function emision_de_factura_golden(): EmisionEnCurso
{
    return new EmisionEnCurso(
        comprobante: FacturaData::from(golden_input('factura')),
        certificado: CertificadoFirma::desdeBase64(base64_encode('certificado-dummy'), 'secreto'),
        codigoNumerico: CodigoNumerico::fromString('22568496'), // fija la clave = golden
    );
}

it('emite una factura de punta a punta reproduciendo los artefactos golden', function () {
    $emision = app(EmitirComprobante::class)->emitir(emision_de_factura_golden());

    $claveGolden = trim(file_get_contents(golden_path('factura/claveAcceso.txt')));
    $xmlGolden = file_get_contents(golden_path('factura/comprobante.xml'));

    expect($emision->claveAcceso()->value)->toBe($claveGolden)
        ->and($emision->xml)->toBe($xmlGolden)
        ->and($emision->xmlFirmado())->toBe($xmlGolden.'<!--firma-fake-->')
        // el gateway recibió el XML FIRMADO, no el original
        ->and($this->gateway->xmlRecibido)->toBe($emision->xmlFirmado())
        ->and((string) $this->gateway->claveConsultada)->toBe($claveGolden)
        ->and($emision->recepcion?->recibida())->toBeTrue()
        ->and($emision->autorizacion?->autorizado())->toBeTrue();
});

it('aborta en recepción cuando el SRI devuelve el comprobante', function () {
    $this->gateway->devolverComprobantes();

    try {
        app(EmitirComprobante::class)->emitir(emision_de_factura_golden());
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
        app(EmitirComprobante::class)->emitir(emision_de_factura_golden());
        $this->fail('Debió lanzar EmisionFallida');
    } catch (EmisionFallida $fallo) {
        expect($fallo->etapa)->toBe('autorizacion')
            ->and((string) $fallo->mensajes[0])->toContain('CLAVE ACCESO REGISTRADA');
    }
});

it('genera un código numérico aleatorio cuando la emisión no fija uno', function () {
    $emision = new EmisionEnCurso(
        comprobante: FacturaData::from(golden_input('factura')),
        certificado: CertificadoFirma::desdeBase64(base64_encode('certificado-dummy'), 'secreto'),
    );

    app(EmitirComprobante::class)->emitir($emision);

    // los primeros 39 dígitos (fecha+codDoc+ruc+ambiente+serie+secuencial)
    // son deterministas; el código numérico varía por comprobante
    $claveGolden = trim(file_get_contents(golden_path('factura/claveAcceso.txt')));

    expect($emision->claveAcceso()->value)
        ->toHaveLength(49)
        ->toStartWith(substr($claveGolden, 0, 39));
});
