<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\GuiaRemision\GuiaRemisionData;
use App\Sri\Data\Liquidacion\LiquidacionCompraData;
use App\Sri\Data\NotaDebito\NotaDebitoData;
use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use App\Sri\Support\ComprobanteXmlParser;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;
use Illuminate\Support\Facades\Storage;

/*
 * Los payloads (payload_nota_debito, payload_guia_remision, payload_liquidacion)
 * viven en tests/Payloads.php.
 */
dataset('tipos nuevos', [
    'notaDebito' => ['notaDebito', NotaDebitoData::class, TipoComprobante::NotaDebito, '05', '1.0.0', 'payload_nota_debito'],
    'guiaRemision' => ['guiaRemision', GuiaRemisionData::class, TipoComprobante::GuiaRemision, '06', '1.0.0', 'payload_guia_remision'],
    'liquidacionCompra' => ['liquidacionCompra', LiquidacionCompraData::class, TipoComprobante::LiquidacionCompra, '03', '1.1.0', 'payload_liquidacion'],
]);

it('deriva tipo, codDoc y versión de esquema de :dataset', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version) {
    expect($clase::tipo())->toBe($tipo)
        ->and($tipo->value)->toBe($codDoc)
        ->and($tipo->rootElement())->toBe($root)
        ->and($tipo->versionEsquema())->toBe($version);
})->with('tipos nuevos');

it('construye un XML bien formado con la raíz y versión correctas para :dataset', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version, string $payloadFn) {
    $comprobante = $clase::from($payloadFn());
    $comprobante->infoTributaria->claveAcceso = ClaveAcceso::generar(
        fechaEmision: $comprobante->fechaEmision(),
        tipoComprobante: $tipo,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '001',
        puntoEmision: '001',
        secuencial: Secuencial::fromString($comprobante->infoTributaria->secuencial),
        codigoNumerico: CodigoNumerico::fromString('22568496'),
    );

    $xml = ConstruirXml::render($comprobante);
    $dom = simplexml_load_string($xml);

    expect($dom)->not->toBeFalse()
        ->and($dom->getName())->toBe($root)
        ->and((string) $dom['version'])->toBe($version)
        // el codDoc lo deriva el dominio, no el payload
        ->and((string) $dom->infoTributaria->codDoc)->toBe($codDoc);
})->with('tipos nuevos');

it('hace roundtrip XML → DTO → XML para :dataset', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version, string $payloadFn) {
    $comprobante = $clase::from($payloadFn());
    $comprobante->infoTributaria->claveAcceso = ClaveAcceso::generar(
        fechaEmision: $comprobante->fechaEmision(),
        tipoComprobante: $tipo,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '001',
        puntoEmision: '001',
        secuencial: Secuencial::fromString($comprobante->infoTributaria->secuencial),
        codigoNumerico: CodigoNumerico::fromString('22568496'),
    );
    $xml = ConstruirXml::render($comprobante);

    $reparseado = new ComprobanteXmlParser()->parse($xml);

    expect($reparseado)->toBeInstanceOf($clase)
        ->and(ConstruirXml::render($reparseado))->toBe($xml);
})->with('tipos nuevos');

describe('emisión end-to-end de los tipos nuevos', function () {
    beforeEach(function () {
        $this->gateway = new FakeSriGateway;
        $this->app->instance(SriGateway::class, $this->gateway);
        $this->app->instance(XmlSigner::class, new FakeXmlSigner);
        config()->set('sri.firmador.driver', 'nativo');
        config()->set('sri.autorizacion.espera_ms', 0);
        Storage::fake();
        actuar_como_contribuyente();
    });

    it('emite y autoriza :dataset vía la API', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version, string $payloadFn) {
        $this->postJson(route('api.v1.comprobantes.emitir'), [$root => $payloadFn()])
            ->assertSuccessful()
            ->assertJsonPath('emitido', true)
            ->assertJsonPath('tipo', $root);
    })->with('tipos nuevos');
});
