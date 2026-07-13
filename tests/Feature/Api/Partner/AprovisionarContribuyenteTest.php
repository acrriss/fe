<?php

use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Models\Partner;

describe('aprovisionamiento (§11)', function () {
    it('aprovisiona un contribuyente gestionado sin usuario ni plan', function () {
        $partner = actuar_como_partner();

        $respuesta = $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), [
            'ruc' => '0992223334001',
            'razon_social' => 'Mi Cliente S.A.',
            'nombre_comercial' => 'MiCliente',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.ruc', '0992223334001')
            ->assertJsonPath('data.razonSocial', 'Mi Cliente S.A.')
            ->assertJsonPath('data.certificado.configurado', false);

        $contribuyente = Contribuyente::where('ruc', '0992223334001')->first();

        expect($contribuyente->partner_id)->toBe($partner->id)
            ->and($contribuyente->plan_id)->toBeNull()
            ->and($contribuyente->users)->toBeEmpty()
            ->and($respuesta->json('data.id'))->toBe($contribuyente->uuid);
    });

    it('es idempotente por RUC dentro del partner', function () {
        actuar_como_partner();
        $payload = ['ruc' => '0992223334001', 'razon_social' => 'Mi Cliente S.A.'];

        $primera = $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), $payload);
        $segunda = $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), $payload);

        $primera->assertCreated();
        $segunda->assertOk()
            ->assertJsonPath('data.id', $primera->json('data.id'));

        expect(Contribuyente::where('ruc', '0992223334001')->count())->toBe(1);
    });

    it('responde 409 si el RUC ya está registrado en otra cuenta', function () {
        Contribuyente::factory()->create(['ruc' => '0992223334001']); // cuenta directa

        actuar_como_partner();

        $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), [
            'ruc' => '0992223334001',
            'razon_social' => 'Mi Cliente S.A.',
        ])->assertStatus(409);
    });

    it('valida el payload: :dataset', function (array $payload) {
        actuar_como_partner();

        $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), $payload)
            ->assertUnprocessable();
    })->with([
        'vacío' => [[]],
        'ruc malformado' => [['ruc' => '123', 'razon_social' => 'X']],
        'sin razón social' => [['ruc' => '0992223334001']],
    ]);
});

describe('listado de gestionados', function () {
    it('lista los contribuyentes del partner con su consumo del mes', function () {
        $partner = actuar_como_partner();
        $gestionado = contribuyente_gestionado($partner);
        Comprobante::factory()->count(2)->create(['contribuyente_id' => $gestionado->id]);

        // el gestionado de otro partner no aparece
        contribuyente_gestionado(Partner::factory()->create(), ['ruc' => '0992223334001']);

        $respuesta = $this->getJson(route('api.partner.v1.contribuyentes.index'));

        $respuesta->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $gestionado->uuid)
            ->assertJsonPath('data.0.emisionesDelMes', 2)
            ->assertJsonPath('data.0.certificado.configurado', true);
    });

    it('filtra por RUC exacto', function () {
        $partner = actuar_como_partner();
        contribuyente_gestionado($partner);
        $buscado = contribuyente_gestionado($partner, ['ruc' => '0992223334001']);

        $this->getJson(route('api.partner.v1.contribuyentes.index', ['ruc' => '0992223334001']))
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $buscado->uuid);
    });
});

describe('acceso al plano de gestión', function () {
    it('rechaza tokens de usuario directo (403)', function () {
        actuar_como_contribuyente();

        $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), [
            'ruc' => '0992223334001',
            'razon_social' => 'X',
        ])->assertForbidden();

        $this->getJson(route('api.partner.v1.contribuyentes.index'))->assertForbidden();
    });

    it('requiere autenticación (401)', function () {
        $this->getJson(route('api.partner.v1.contribuyentes.index'))->assertUnauthorized();
    });
});
