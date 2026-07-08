<?php

use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Models\Plan;
use App\Models\User;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->app->instance(SriGateway::class, new FakeSriGateway);
    $this->app->instance(XmlSigner::class, new FakeXmlSigner);
    config()->set('sri.autorizacion.espera_ms', 0);
    Storage::fake();
});

describe('autenticación', function () {
    it('rechaza peticiones sin token en todos los endpoints protegidos', function () {
        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertUnauthorized();

        $this->getJson(route('api.v1.comprobantes.mostrar', 'cualquier-uuid'))
            ->assertUnauthorized();

        $this->putJson(route('api.v1.contribuyente.certificado'), [])
            ->assertUnauthorized();
    });

    it('emite tokens a cambio de credenciales válidas', function () {
        $contribuyente = Contribuyente::factory()->create();
        User::factory()->create([
            'email' => 'facturacion@empresa.ec',
            'password' => 'clave-segura',
            'contribuyente_id' => $contribuyente->id,
        ]);

        $respuesta = $this->postJson(route('api.v1.tokens.emitir'), [
            'email' => 'facturacion@empresa.ec',
            'password' => 'clave-segura',
            'device_name' => 'erp-cliente',
        ]);

        $respuesta->assertCreated()->assertJsonStructure(['token']);

        // el token funciona contra un endpoint protegido
        $token = $respuesta->json('token');
        $this->getJson(route('api.v1.comprobantes.mostrar', 'no-existe'), [
            'Authorization' => "Bearer {$token}",
        ])->assertNotFound(); // 404 = autenticado (401 sería rechazo)
    });

    it('rechaza credenciales incorrectas', function () {
        User::factory()->create(['email' => 'alguien@empresa.ec']);

        $this->postJson(route('api.v1.tokens.emitir'), [
            'email' => 'alguien@empresa.ec',
            'password' => 'incorrecta',
            'device_name' => 'x',
        ])->assertUnprocessable();
    });
});

describe('certificado del contribuyente', function () {
    it('valida, guarda cifrado con sus metadatos y habilita la emisión', function () {
        $contribuyente = actuar_como_contribuyente(conCertificado: false);

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertStatus(409); // sin certificado aún

        $this->putJson(route('api.v1.contribuyente.certificado'), [
            'p12' => base64_encode(p12_de_prueba()),
            'clave' => 'clave-prueba',
        ])->assertNoContent();

        $contribuyente->refresh();
        expect($contribuyente->tieneCertificado())->toBeTrue()
            // metadatos extraídos al validar
            ->and($contribuyente->certificado_titular)->toBe('CERTIFICADO DE PRUEBA')
            ->and($contribuyente->certificado_valido_hasta->isFuture())->toBeTrue()
            // cifrado en reposo: el valor crudo en BD no es el base64
            ->and($contribuyente->getRawOriginal('certificado_p12'))
            ->not->toContain(base64_encode(p12_de_prueba()));

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertSuccessful();
    });

    it('rechaza la clave incorrecta al cargar el certificado', function () {
        actuar_como_contribuyente(conCertificado: false);

        $this->putJson(route('api.v1.contribuyente.certificado'), [
            'p12' => base64_encode(p12_de_prueba()),
            'clave' => 'clave-equivocada',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['p12' => 'La clave del certificado no es correcta.']);
    });

    it('acepta un .p12 legacy (RC2/3DES) vía el fallback', function () {
        $contribuyente = actuar_como_contribuyente(conCertificado: false);

        $this->putJson(route('api.v1.contribuyente.certificado'), [
            'p12' => base64_encode(p12_de_prueba(legacy: true)),
            'clave' => 'clave-prueba',
        ])->assertNoContent();

        expect($contribuyente->refresh()->certificado_titular)->toBe('CERTIFICADO DE PRUEBA');
    });

    it('rechaza archivos que no son un p12: :dataset', function (string $p12) {
        actuar_como_contribuyente(conCertificado: false);

        $this->putJson(route('api.v1.contribuyente.certificado'), [
            'p12' => $p12,
            'clave' => 'x',
        ])->assertUnprocessable()->assertJsonValidationErrors(['p12']);
    })->with([
        'no es base64' => '***no-es-base64***',
        'base64 de basura' => fn (): string => base64_encode('no soy un pkcs12'),
    ]);
});

describe('aislamiento entre contribuyentes', function () {
    it('no expone comprobantes de otro contribuyente', function () {
        $ajeno = Comprobante::factory()->autorizado()->create();

        actuar_como_contribuyente();

        $this->getJson(route('api.v1.comprobantes.mostrar', $ajeno))
            ->assertNotFound();

        $this->getJson(route('api.v1.comprobantes.ride', $ajeno))
            ->assertNotFound();
    });
});

describe('cuota por plan', function () {
    it('bloquea la emisión cuando la cuota mensual está agotada', function () {
        $plan = Plan::factory()->conCuota(1)->create();
        $contribuyente = actuar_como_contribuyente(atributos: ['plan_id' => $plan->id]);

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertSuccessful();

        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertTooManyRequests();
    });

    it('sin plan no hay cuota (uso interno)', function () {
        actuar_como_contribuyente();

        // el código numérico aleatorio hace única cada clave de acceso
        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertSuccessful();
        $this->postJson(route('api.v1.comprobantes.emitir'), golden_payload('factura'))
            ->assertSuccessful();
    });
});
