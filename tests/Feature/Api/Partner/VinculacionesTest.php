<?php

use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\Vinculacion;

describe('solicitud de vinculación (§11, 7d)', function () {
    it('crea la solicitud pendiente para un RUC de cuenta directa', function () {
        $directo = Contribuyente::factory()->create(['ruc' => '0992479248001']);
        actuar_como_partner();

        $respuesta = $this->postJson(route('api.partner.v1.vinculaciones.solicitar'), [
            'ruc' => '0992479248001',
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.ruc', '0992479248001')
            ->assertJsonPath('data.estado', 'pendiente');

        expect(Vinculacion::count())->toBe(1)
            ->and($directo->refresh()->partner_id)->toBeNull(); // aún sin aprobar
    });

    it('es idempotente mientras la solicitud siga pendiente', function () {
        Contribuyente::factory()->create(['ruc' => '0992479248001']);
        actuar_como_partner();

        $primera = $this->postJson(route('api.partner.v1.vinculaciones.solicitar'), ['ruc' => '0992479248001']);
        $segunda = $this->postJson(route('api.partner.v1.vinculaciones.solicitar'), ['ruc' => '0992479248001']);

        $primera->assertCreated();
        $segunda->assertOk()->assertJsonPath('data.id', $primera->json('data.id'));

        expect(Vinculacion::count())->toBe(1);
    });

    it('responde 404 si el RUC no está registrado', function () {
        actuar_como_partner();

        $this->postJson(route('api.partner.v1.vinculaciones.solicitar'), ['ruc' => '0992479248001'])
            ->assertNotFound();
    });

    it('responde 409 si ya gestiona ese contribuyente o lo gestiona otro partner', function () {
        $partner = actuar_como_partner();
        $propio = contribuyente_gestionado($partner);
        $deOtro = contribuyente_gestionado(Partner::factory()->create(), ['ruc' => '0992479248001']);

        $this->postJson(route('api.partner.v1.vinculaciones.solicitar'), ['ruc' => $propio->ruc])
            ->assertStatus(409);
        $this->postJson(route('api.partner.v1.vinculaciones.solicitar'), ['ruc' => $deOtro->ruc])
            ->assertStatus(409);
    });

    it('lista sus solicitudes con estado', function () {
        $partner = actuar_como_partner();
        Vinculacion::factory()->aprobada()->create(['partner_id' => $partner->id]);
        Vinculacion::factory()->create(['partner_id' => $partner->id]);
        Vinculacion::factory()->create(); // de otro partner

        $this->getJson(route('api.partner.v1.vinculaciones.index'))
            ->assertSuccessful()
            ->assertJsonCount(2, 'data');
    });
});
