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

/*
 * Ficha 2.34, Anexo 21: el partner configura las designaciones del SRI
 * de su gestionado; `fe` las imprime como leyenda en cada comprobante.
 */
it('configura y borra las designaciones del emisor', function () {
    $partner = actuar_como_partner();
    $gestionado = contribuyente_gestionado($partner);

    $this->patchJson(route('api.partner.v1.contribuyentes.actualizar', $gestionado->uuid), [
        'agente_retencion_resolucion' => '00006498', // se guarda sin ceros a la izquierda
        'contribuyente_especial_resolucion' => '5368',
        'regimen_rimpe' => 'negocio_popular',
        'gran_contribuyente_resolucion' => 'NAC-GCFOIOC21-00000868-E',
    ])->assertSuccessful()
        ->assertJsonPath('data.agenteRetencionResolucion', '6498')
        ->assertJsonPath('data.contribuyenteEspecialResolucion', '5368')
        ->assertJsonPath('data.regimenRimpe', 'negocio_popular')
        ->assertJsonPath('data.granContribuyenteResolucion', 'NAC-GCFOIOC21-00000868-E');

    $this->patchJson(route('api.partner.v1.contribuyentes.actualizar', $gestionado->uuid), [
        'agente_retencion_resolucion' => null,
        'regimen_rimpe' => null,
    ])->assertSuccessful()
        ->assertJsonPath('data.agenteRetencionResolucion', null)
        ->assertJsonPath('data.contribuyenteEspecialResolucion', '5368')
        ->assertJsonPath('data.regimenRimpe', null);
});

it('rechaza designaciones con formato inválido: :dataset', function (array $payload, string $campo) {
    $partner = actuar_como_partner();
    $gestionado = contribuyente_gestionado($partner);

    $this->patchJson(route('api.partner.v1.contribuyentes.actualizar', $gestionado->uuid), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($campo);
})->with([
    'agente de retención con letras' => [['agente_retencion_resolucion' => 'NAC-1'], 'agente_retencion_resolucion'],
    'agente de retención de 9 dígitos' => [['agente_retencion_resolucion' => '123456789'], 'agente_retencion_resolucion'],
    'contribuyente especial muy corto' => [['contribuyente_especial_resolucion' => '12'], 'contribuyente_especial_resolucion'],
    'régimen desconocido' => [['regimen_rimpe' => 'rise'], 'regimen_rimpe'],
    'resolución de gran contribuyente con símbolos' => [['gran_contribuyente_resolucion' => 'NAC/2021'], 'gran_contribuyente_resolucion'],
]);

it('el aprovisionamiento acepta las designaciones del emisor', function () {
    actuar_como_partner();

    $this->postJson(route('api.partner.v1.contribuyentes.aprovisionar'), [
        'ruc' => '0992479248001',
        'razon_social' => 'Mi Cliente S.A.',
        'agente_retencion_resolucion' => '6498',
    ])->assertCreated()
        ->assertJsonPath('data.agenteRetencionResolucion', '6498')
        ->assertJsonPath('data.contribuyenteEspecialResolucion', null);
});
