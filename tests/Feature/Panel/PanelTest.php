<?php

use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake();
});

/**
 * Usuario de panel (sesión web) perteneciente a un contribuyente.
 */
function entrar_al_panel(array $atributosContribuyente = []): Contribuyente
{
    $contribuyente = Contribuyente::factory()->conCertificado()->create($atributosContribuyente);
    $user = User::factory()->create(['contribuyente_id' => $contribuyente->id]);

    test()->actingAs($user);

    return $contribuyente;
}

describe('inicio', function () {
    it('muestra consumo, plan y últimas emisiones', function () {
        $plan = Plan::factory()->create(['nombre' => 'Emprendedor', 'cuota_mensual' => 100]);
        $contribuyente = entrar_al_panel(['plan_id' => $plan->id]);
        Comprobante::factory()->count(3)->autorizado()->create(['contribuyente_id' => $contribuyente->id]);

        $this->get(route('panel.inicio'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Panel/Inicio')
                ->where('consumo.emisionesDelMes', 3)
                ->where('consumo.cuotaMensual', 100)
                ->where('consumo.plan', 'Emprendedor')
                ->where('totales.autorizados', 3)
                ->has('ultimos.data', 3),
        );
    });
});

describe('comprobantes', function () {
    it('lista solo los comprobantes del contribuyente', function () {
        Comprobante::factory()->count(2)->create(); // ajenos
        $contribuyente = entrar_al_panel();
        Comprobante::factory()->autorizado()->create([
            'contribuyente_id' => $contribuyente->id,
            'secuencial' => '000000042',
        ]);

        $this->get(route('panel.comprobantes'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Panel/Comprobantes')
                ->has('comprobantes.data', 1)
                ->where('comprobantes.data.0.secuencial', '000000042'),
        );
    });

    it('descarga el XML del comprobante propio', function () {
        $contribuyente = entrar_al_panel();
        $registro = Comprobante::factory()->autorizado()->create(['contribuyente_id' => $contribuyente->id]);
        Storage::put($path = "comprobantes/{$registro->clave_acceso}.xml", '<factura/>');
        $registro->update(['xml_path' => $path]);

        $this->get(route('panel.comprobantes.xml', $registro))
            ->assertSuccessful()
            ->assertDownload("comprobante-{$registro->clave_acceso}.xml");
    });

    it('no descarga XML ajeno', function () {
        $ajeno = Comprobante::factory()->autorizado()->create(['xml_path' => 'x.xml']);
        entrar_al_panel();

        $this->get(route('panel.comprobantes.xml', $ajeno))->assertNotFound();
    });
});

describe('tokens', function () {
    it('crea un token y lo muestra una sola vez', function () {
        entrar_al_panel();

        $this->post(route('panel.tokens.store'), ['nombre' => 'ERP contable'])
            ->assertRedirect(route('panel.tokens'))
            ->assertSessionHas('token');

        $this->get(route('panel.tokens'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Panel/Tokens')
                ->has('tokens', 1)
                ->where('tokens.0.nombre', 'ERP contable'),
        );
    });

    it('revoca un token', function () {
        $contribuyente = entrar_al_panel();
        $user = $contribuyente->users()->first() ?? auth()->user();
        $token = auth()->user()->createToken('viejo');

        $this->delete(route('panel.tokens.destroy', $token->accessToken->id))
            ->assertRedirect(route('panel.tokens'));

        expect(auth()->user()->tokens()->count())->toBe(0);
    });
});

describe('configuración', function () {
    it('actualiza los datos del contribuyente', function () {
        $contribuyente = entrar_al_panel();

        $this->put(route('panel.configuracion.update'), [
            'razon_social' => 'Razón Nueva S.A.',
            'nombre_comercial' => 'La Nueva',
            'dir_matriz' => 'Av. Siempre Viva 123',
        ])->assertRedirect(route('panel.configuracion'));

        expect($contribuyente->refresh()->razon_social)->toBe('Razón Nueva S.A.');
    });

    it('sube el certificado .p12 desde el panel y muestra sus metadatos', function () {
        $contribuyente = entrar_al_panel(); // factory ya trae certificado; lo reemplazamos
        $archivo = UploadedFile::fake()->createWithContent('firma.p12', p12_de_prueba());

        $this->put(route('panel.configuracion.certificado'), [
            'certificado' => $archivo,
            'clave' => 'clave-prueba',
        ])->assertRedirect(route('panel.configuracion'));

        $contribuyente->refresh();
        expect($contribuyente->certificado_p12)->toBe(base64_encode(p12_de_prueba()))
            ->and($contribuyente->certificado_titular)->toBe('CERTIFICADO DE PRUEBA');

        $this->get(route('panel.configuracion'))->assertInertia(
            fn (Assert $page) => $page
                ->where('certificado.titular', 'CERTIFICADO DE PRUEBA')
                ->where('certificado.vencido', false),
        );
    });

    it('rechaza desde el panel un certificado con clave incorrecta', function () {
        entrar_al_panel();
        $archivo = UploadedFile::fake()->createWithContent('firma.p12', p12_de_prueba());

        $this->put(route('panel.configuracion.certificado'), [
            'certificado' => $archivo,
            'clave' => 'clave-equivocada',
        ])->assertInvalid(['certificado' => 'La clave del certificado no es correcta.']);
    });

    it('sube el logo del RIDE y lo sirve como vista previa', function () {
        $contribuyente = entrar_al_panel();

        $this->post(route('panel.configuracion.logo'), [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertRedirect(route('panel.configuracion'));

        $contribuyente->refresh();
        expect($contribuyente->logo_path)->not->toBeNull();
        Storage::assertExists($contribuyente->logo_path);

        // la página expone la URL y la ruta sirve la imagen
        $this->get(route('panel.configuracion'))->assertInertia(
            fn (Assert $page) => $page->whereNot('logo_url', null),
        );
        $this->get(route('panel.configuracion.logo.mostrar'))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'image/png');
    });

    it('la vista previa del logo responde 404 si no hay logo', function () {
        entrar_al_panel();

        $this->get(route('panel.configuracion.logo.mostrar'))->assertNotFound();
    });

    it('el estado del comprobante queda etiquetado en la página', function () {
        $contribuyente = entrar_al_panel();

        $this->get(route('panel.configuracion'))->assertInertia(
            fn (Assert $page) => $page
                ->component('Panel/Configuracion')
                ->where('contribuyente.tiene_certificado', true)
                ->where('contribuyente.ruc', $contribuyente->ruc),
        );
    });
});
