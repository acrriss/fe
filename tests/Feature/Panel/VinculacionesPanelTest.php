<?php

use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\User;
use App\Models\Vinculacion;
use App\Sri\Enums\EstadoVinculacion;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Dueño de cuenta directa autenticado por sesión web (no Sanctum).
 */
function dueno_de_cuenta(): array
{
    $contribuyente = Contribuyente::factory()->conCertificado()->create();
    $user = User::factory()->create(['contribuyente_id' => $contribuyente->id]);
    test()->actingAs($user);

    return [$contribuyente, $user];
}

it('muestra las solicitudes pendientes en Configuración', function () {
    [$contribuyente] = dueno_de_cuenta();
    $partner = Partner::factory()->create(['nombre' => 'POS Andino']);
    Vinculacion::factory()->create([
        'partner_id' => $partner->id,
        'contribuyente_id' => $contribuyente->id,
    ]);

    $this->get(route('panel.configuracion'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Panel/Configuracion')
            ->count('vinculaciones_pendientes', 1)
            ->where('vinculaciones_pendientes.0.partner', 'POS Andino'));
});

it('al aprobar, el contribuyente pasa a ser gestionado por el partner', function () {
    [$contribuyente] = dueno_de_cuenta();
    $vinculacion = Vinculacion::factory()->create(['contribuyente_id' => $contribuyente->id]);

    $this->post(route('panel.vinculaciones.aprobar', $vinculacion->uuid))
        ->assertRedirect(route('panel.configuracion'));

    expect($vinculacion->refresh()->estado)->toBe(EstadoVinculacion::Aprobada)
        ->and($contribuyente->refresh()->partner_id)->toBe($vinculacion->partner_id);
});

it('al rechazar no cambia nada salvo el estado', function () {
    [$contribuyente] = dueno_de_cuenta();
    $vinculacion = Vinculacion::factory()->create(['contribuyente_id' => $contribuyente->id]);

    $this->post(route('panel.vinculaciones.rechazar', $vinculacion->uuid))
        ->assertRedirect(route('panel.configuracion'));

    expect($vinculacion->refresh()->estado)->toBe(EstadoVinculacion::Rechazada)
        ->and($contribuyente->refresh()->partner_id)->toBeNull();
});

it('no puede resolver solicitudes de otros contribuyentes (404)', function () {
    dueno_de_cuenta();
    $ajena = Vinculacion::factory()->create(); // de otro contribuyente

    $this->post(route('panel.vinculaciones.aprobar', $ajena->uuid))->assertNotFound();

    expect($ajena->refresh()->estado)->toBe(EstadoVinculacion::Pendiente);
});

it('una solicitud ya resuelta responde 409', function () {
    [$contribuyente] = dueno_de_cuenta();
    $resuelta = Vinculacion::factory()->rechazada()->create(['contribuyente_id' => $contribuyente->id]);

    $this->post(route('panel.vinculaciones.aprobar', $resuelta->uuid))->assertStatus(409);
});
