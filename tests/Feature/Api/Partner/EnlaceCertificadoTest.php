<?php

use App\Models\Contribuyente;
use App\Models\Partner;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

describe('generación del enlace (API de partner)', function () {
    it('genera una URL firmada temporal para un gestionado', function () {
        $partner = actuar_como_partner();
        $gestionado = contribuyente_gestionado($partner);

        $respuesta = $this->postJson(
            route('api.partner.v1.contribuyentes.enlace-certificado', $gestionado->uuid),
        );

        $respuesta->assertCreated()->assertJsonStructure(['url', 'expiraEn']);

        expect($respuesta->json('url'))
            ->toContain("/certificado/{$gestionado->uuid}")
            ->toContain('signature=');
    });

    it('responde 404 para un contribuyente ajeno', function () {
        actuar_como_partner();
        $ajeno = contribuyente_gestionado(Partner::factory()->create(), ['ruc' => '0992223334001']);

        $this->postJson(route('api.partner.v1.contribuyentes.enlace-certificado', $ajeno->uuid))
            ->assertNotFound();
    });
});

describe('página hospedada de carga (§11, 7d)', function () {
    function enlace_hospedado(Contribuyente $contribuyente): string
    {
        return URL::temporarySignedRoute('certificado.hospedado', now()->addHours(72), [
            'contribuyente' => $contribuyente->uuid,
        ]);
    }

    it('muestra la página con los datos del contribuyente', function () {
        $contribuyente = Contribuyente::factory()->create();

        $this->get(enlace_hospedado($contribuyente))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Certificado/Subir')
                ->where('contribuyente.ruc', $contribuyente->ruc)
                ->where('contribuyente.tiene_certificado', false)
                ->has('url_guardar'));
    });

    it('rechaza URLs sin firma o expiradas (403)', function () {
        $contribuyente = Contribuyente::factory()->create();

        $this->get(route('certificado.hospedado', $contribuyente->uuid))->assertForbidden();

        $enlace = enlace_hospedado($contribuyente);
        $this->travel(73)->hours();
        $this->get($enlace)->assertForbidden();
    });

    it('el cliente final sube su .p12 y queda cifrado en el servicio', function () {
        $contribuyente = Contribuyente::factory()->create();

        $respuesta = $this->post(enlace_hospedado($contribuyente), [
            'certificado' => UploadedFile::fake()->createWithContent('firma.p12', p12_de_prueba()),
            'clave' => 'clave-prueba',
        ]);

        $respuesta->assertRedirect();

        $contribuyente->refresh();
        expect($contribuyente->tieneCertificado())->toBeTrue()
            ->and($contribuyente->certificado_titular)->not->toBeNull();
    });

    it('reporta como error de validación una clave incorrecta', function () {
        $contribuyente = Contribuyente::factory()->create();

        $this->from(enlace_hospedado($contribuyente))
            ->post(enlace_hospedado($contribuyente), [
                'certificado' => UploadedFile::fake()->createWithContent('firma.p12', p12_de_prueba()),
                'clave' => 'clave-incorrecta',
            ])
            ->assertSessionHasErrors('certificado');

        expect($contribuyente->refresh()->tieneCertificado())->toBeFalse();
    });
});
