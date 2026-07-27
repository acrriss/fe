<?php

use App\Models\Partner;

it('actualiza datos y sublímite de un gestionado', function () {
    $partner = actuar_como_partner();
    $gestionado = contribuyente_gestionado($partner);

    $this->patchJson(route('api.partner.v1.contribuyentes.actualizar', $gestionado->uuid), [
        'razon_social' => 'Nueva Razón S.A.',
        'limite_mensual' => 50,
    ])->assertSuccessful()
        ->assertJsonPath('data.razonSocial', 'Nueva Razón S.A.')
        ->assertJsonPath('data.limiteMensual', 50);

    expect($gestionado->refresh()->limite_mensual)->toBe(50);
});

it('permite quitar el sublímite con null', function () {
    $partner = actuar_como_partner();
    $gestionado = contribuyente_gestionado($partner, ['limite_mensual' => 50]);

    $this->patchJson(route('api.partner.v1.contribuyentes.actualizar', $gestionado->uuid), [
        'limite_mensual' => null,
    ])->assertSuccessful();

    expect($gestionado->refresh()->limite_mensual)->toBeNull();
});

it('responde 404 para un contribuyente ajeno', function () {
    actuar_como_partner();
    $ajeno = contribuyente_gestionado(Partner::factory()->create(), ['ruc' => '0992479248001']);

    $this->patchJson(route('api.partner.v1.contribuyentes.actualizar', $ajeno->uuid), [
        'razon_social' => 'X',
    ])->assertNotFound();
});

it('el aprovisionamiento acepta limite_mensual', function () {
    actuar_como_partner();

    $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), [
        'ruc' => '0992479248001',
        'razon_social' => 'Mi Cliente S.A.',
        'limite_mensual' => 200,
    ])->assertCreated()
        ->assertJsonPath('data.limiteMensual', 200);
});
