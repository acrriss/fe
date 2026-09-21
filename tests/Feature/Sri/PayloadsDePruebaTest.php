<?php

use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\GuiaRemision\GuiaRemisionData;
use App\Sri\Data\Liquidacion\LiquidacionCompraData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\NotaDebito\NotaDebitoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use Illuminate\Support\Facades\Storage;

/*
 * Los payloads de tests/Payloads.php son la base de casi toda la suite:
 * si uno deja de ser válido para el dominio, debe verse aquí primero.
 */
dataset('tipos de comprobante', [
    'factura' => ['factura', FacturaData::class, TipoComprobante::Factura],
    'notaCredito' => ['notaCredito', NotaCreditoData::class, TipoComprobante::NotaCredito],
    'comprobanteRetencion' => ['comprobanteRetencion', ComprobanteRetencionData::class, TipoComprobante::ComprobanteRetencion],
    'notaDebito' => ['notaDebito', NotaDebitoData::class, TipoComprobante::NotaDebito],
    'guiaRemision' => ['guiaRemision', GuiaRemisionData::class, TipoComprobante::GuiaRemision],
    'liquidacionCompra' => ['liquidacionCompra', LiquidacionCompraData::class, TipoComprobante::LiquidacionCompra],
]);

it('el payload de prueba de :dataset se convierte en su DTO tipado', function (string $tipo, string $dataClass, TipoComprobante $esperado) {
    $comprobante = $dataClass::from(payload_comprobante($tipo));

    expect($comprobante::tipo())->toBe($esperado)
        ->and((string) $comprobante->infoTributaria->ruc)->toBe(RUC_PRUEBA)
        ->and($comprobante->infoTributaria->claveAcceso)->toBeNull();
})->with('tipos de comprobante');

it('payload_emision envuelve el comprobante bajo su elemento raíz', function () {
    expect(payload_emision('factura'))->toBe(['factura' => payload_factura()]);
});

it('rechaza un tipo sin payload de prueba', function () {
    payload_comprobante('recibo');
})->throws(InvalidArgumentException::class);

describe('emisión vía la API', function () {
    beforeEach(function () {
        $this->app->instance(SriGateway::class, new FakeSriGateway);
        $this->app->instance(XmlSigner::class, new FakeXmlSigner);
        config()->set('sri.firmador.driver', 'nativo');
        config()->set('sri.autorizacion.espera_ms', 0);
        Storage::fake();
        actuar_como_contribuyente();
    });

    it('emite y autoriza el payload de prueba de :dataset', function (string $tipo) {
        $this->postJson(route('api.v1.comprobantes.emitir'), payload_emision($tipo))
            ->assertSuccessful()
            ->assertJsonPath('emitido', true)
            ->assertJsonPath('tipo', $tipo);
    })->with('tipos de comprobante');
});
