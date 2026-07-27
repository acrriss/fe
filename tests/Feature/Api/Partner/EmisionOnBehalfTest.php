<?php

use App\Http\Middleware\ResolverContribuyente;
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

    $this->partner = actuar_como_partner();
    $this->gestionado = contribuyente_gestionado($this->partner);
});

/**
 * @return array<string, string>
 */
function cabecera_on_behalf(Contribuyente $contribuyente): array
{
    return [ResolverContribuyente::CABECERA => $contribuyente->uuid];
}

describe('emisión on-behalf (§11)', function () {
    it('emite a nombre del contribuyente gestionado con la cabecera X-Contribuyente', function () {
        $respuesta = $this->postJson(
            route('api.v1.comprobantes.emitir'),
            golden_payload('factura'),
            cabecera_on_behalf($this->gestionado),
        );

        $respuesta->assertSuccessful()
            ->assertJsonPath('emitido', true)
            ->assertJsonPath('autorizacion.estado', 'AUTORIZADO');

        $registro = Comprobante::where('uuid', $respuesta->json('id'))->first();
        expect($registro->contribuyente_id)->toBe($this->gestionado->id);
    });

    it('responde 400 si falta la cabecera X-Contribuyente', function () {
        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertStatus(400);
    });

    it('responde 404 si el contribuyente no es gestionado por el partner', function () {
        $ajeno = Contribuyente::factory()->conCertificado()->create(); // cuenta directa

        $this->postJson(
            route('api.v1.comprobantes.emitir'),
            golden_payload('factura'),
            cabecera_on_behalf($ajeno),
        )->assertNotFound();
    });

    it('sigue exigiendo que el RUC del payload sea el del contribuyente actuado', function () {
        $payload = golden_payload('factura');
        $payload['factura']['infoTributaria']['ruc'] = '1791411099001';

        $this->postJson(
            route('api.v1.comprobantes.emitir'),
            $payload,
            cabecera_on_behalf($this->gestionado),
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['comprobante.infoTributaria.ruc']);
    });

    it('responde 429 cuando la cuota pool del partner está agotada', function () {
        $this->partner->update(['cuota_mensual' => 1]);
        Comprobante::factory()->create(['contribuyente_id' => $this->gestionado->id]);

        $this->postJson(
            route('api.v1.comprobantes.emitir'),
            golden_payload('factura'),
            cabecera_on_behalf($this->gestionado),
        )->assertTooManyRequests();
    });

    it('responde 429 al agotar el sublímite del contribuyente aunque el pool tenga espacio', function () {
        $this->partner->update(['cuota_mensual' => 100]);
        $this->gestionado->update(['limite_mensual' => 1]);
        Comprobante::factory()->create(['contribuyente_id' => $this->gestionado->id]);

        $this->postJson(
            route('api.v1.comprobantes.emitir'),
            golden_payload('factura'),
            cabecera_on_behalf($this->gestionado),
        )->assertTooManyRequests();
    });
});

describe('gestión on-behalf', function () {
    it('consulta un comprobante del contribuyente gestionado', function () {
        $registro = Comprobante::factory()->create(['contribuyente_id' => $this->gestionado->id]);

        $this->getJson(
            route('api.v1.comprobantes.mostrar', $registro),
            cabecera_on_behalf($this->gestionado),
        )->assertSuccessful()
            ->assertJsonPath('data.id', $registro->uuid);
    });

    it('aísla a los gestionados entre sí: el comprobante de uno "no existe" para otro', function () {
        $otroGestionado = contribuyente_gestionado($this->partner, ['ruc' => '0992479248001']);
        $registro = Comprobante::factory()->create(['contribuyente_id' => $otroGestionado->id]);

        $this->getJson(
            route('api.v1.comprobantes.mostrar', $registro),
            cabecera_on_behalf($this->gestionado),
        )->assertNotFound();
    });

    it('carga el certificado del gestionado vía PUT /api/v1/contribuyente/certificado', function () {
        $sinCertificado = Contribuyente::factory()->create([
            'ruc' => '0992479248001',
            'partner_id' => $this->partner->id,
        ]);

        $this->putJson(
            route('api.v1.contribuyente.certificado'),
            ['p12' => base64_encode(p12_de_prueba()), 'clave' => 'clave-prueba'],
            cabecera_on_behalf($sinCertificado),
        )->assertNoContent();

        expect($sinCertificado->refresh()->tieneCertificado())->toBeTrue();
    });
});
