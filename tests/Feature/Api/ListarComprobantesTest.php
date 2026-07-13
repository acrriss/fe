<?php

use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->gateway = new FakeSriGateway;
    $this->app->instance(SriGateway::class, $this->gateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();

    $this->contribuyente = actuar_como_contribuyente();
});

describe('trazabilidad del sistema origen (§11)', function () {
    it('persiste external_id y metadata de la emisión y los expone en la consulta', function () {
        $payload = golden_payload('factura') + [
            'external_id' => 'venta-123',
            'metadata' => ['caja' => '01', 'vendedor' => 'ana'],
        ];

        $respuesta = $this->postJson(route('api.v1.comprobantes.emitir'), $payload);

        $respuesta->assertSuccessful();

        $this->getJson(route('api.v1.comprobantes.mostrar', $respuesta->json('id')))
            ->assertSuccessful()
            ->assertJsonPath('data.externalId', 'venta-123')
            ->assertJsonPath('data.metadata.caja', '01');
    });

    it('rechaza un external_id demasiado largo', function () {
        $payload = golden_payload('factura') + ['external_id' => str_repeat('x', 256)];

        $this->postJson(route('api.v1.comprobantes.emitir'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['external_id']);
    });
});

describe('listado de comprobantes', function () {
    it('lista las emisiones del contribuyente y filtra por external_id', function () {
        Comprobante::factory()->create([
            'contribuyente_id' => $this->contribuyente->id,
            'external_id' => 'venta-1',
        ]);
        Comprobante::factory()->create([
            'contribuyente_id' => $this->contribuyente->id,
            'external_id' => 'venta-2',
        ]);

        $this->getJson(route('api.v1.comprobantes.index'))
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');

        $this->getJson(route('api.v1.comprobantes.index', ['external_id' => 'venta-2']))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.externalId', 'venta-2');
    });

    it('no expone comprobantes de otros contribuyentes', function () {
        Comprobante::factory()->create([
            'contribuyente_id' => Contribuyente::factory()->create()->id,
            'external_id' => 'venta-ajena',
        ]);

        $this->getJson(route('api.v1.comprobantes.index', ['external_id' => 'venta-ajena']))
            ->assertSuccessful()
            ->assertJsonCount(0, 'data');
    });

    it('filtra por estado', function () {
        Comprobante::factory()->autorizado()->create(['contribuyente_id' => $this->contribuyente->id]);
        Comprobante::factory()->create(['contribuyente_id' => $this->contribuyente->id]);

        $this->getJson(route('api.v1.comprobantes.index', ['estado' => 'autorizado']))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado', 'autorizado');
    });
});
