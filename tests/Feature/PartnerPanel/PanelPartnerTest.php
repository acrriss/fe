<?php

use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEntrega;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Partner con credenciales de panel, autenticado por sesión (guard
 * partner-web).
 */
function partner_en_panel(array $atributos = []): Partner
{
    $partner = Partner::factory()->create($atributos + [
        'email' => 'pos@ejemplo.test',
        'password' => 'secreto-123',
    ]);

    test()->actingAs($partner, 'partner-web');

    return $partner;
}

describe('login del panel de partner', function () {
    it('entra con credenciales válidas', function () {
        Partner::factory()->create(['email' => 'pos@ejemplo.test', 'password' => 'secreto-123']);

        $this->post(route('partner.login.store'), [
            'email' => 'pos@ejemplo.test',
            'password' => 'secreto-123',
        ])->assertRedirect(route('partner.inicio'));

        $this->assertAuthenticated('partner-web');
    });

    it('devuelve al panel de partner a quien ya tiene sesión, no al login de contribuyentes', function () {
        partner_en_panel();

        $this->get(route('partner.login'))->assertRedirect(route('partner.inicio'));
    });

    it('rechaza credenciales inválidas', function () {
        Partner::factory()->create(['email' => 'pos@ejemplo.test', 'password' => 'secreto-123']);

        $this->from(route('partner.login'))->post(route('partner.login.store'), [
            'email' => 'pos@ejemplo.test',
            'password' => 'otra-cosa',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('partner-web');
    });

    it('redirige a los invitados al login de partner', function () {
        $this->get(route('partner.inicio'))->assertRedirect(route('partner.login'));
    });

    it('la sesión de partner no abre el panel de contribuyentes', function () {
        // login real por HTTP: la sesión queda solo en el guard partner-web
        Partner::factory()->create(['email' => 'pos@ejemplo.test', 'password' => 'secreto-123']);
        $this->post(route('partner.login.store'), [
            'email' => 'pos@ejemplo.test',
            'password' => 'secreto-123',
        ]);

        $this->get(route('panel.inicio'))->assertRedirect(route('login'));
    });

    it('la sesión de contribuyente no abre el panel de partner', function () {
        $contribuyente = Contribuyente::factory()->create();
        $this->actingAs(User::factory()->create(['contribuyente_id' => $contribuyente->id]));

        $this->get(route('partner.inicio'))->assertRedirect(route('partner.login'));
    });
});

describe('páginas del panel', function () {
    it('inicio muestra consumo pool y últimas emisiones', function () {
        $partner = partner_en_panel(['cuota_mensual' => 100]);
        $gestionado = contribuyente_gestionado($partner);
        Comprobante::factory()->autorizado()->create(['contribuyente_id' => $gestionado->id]);

        $this->get(route('partner.inicio'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PartnerPanel/Inicio')
                ->where('consumo.emisionesDelMes', 1)
                ->where('consumo.cuotaMensual', 100)
                ->where('totales.contribuyentes', 1)
                ->count('ultimos', 1));
    });

    it('contribuyentes lista los gestionados y genera el enlace de certificado', function () {
        $partner = partner_en_panel();
        $gestionado = contribuyente_gestionado($partner);

        $this->get(route('partner.contribuyentes'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PartnerPanel/Contribuyentes')
                ->count('contribuyentes.data', 1)
                ->where('contribuyentes.data.0.ruc', $gestionado->ruc));

        $this->post(route('partner.contribuyentes.enlace-certificado', $gestionado->uuid))
            ->assertRedirect(route('partner.contribuyentes'))
            ->assertSessionHas('enlace_certificado');
    });

    it('webhooks muestra endpoints y entregas del partner', function () {
        $partner = partner_en_panel();
        $endpoint = WebhookEndpoint::factory()->dePartner($partner)->create();
        WebhookEntrega::factory()->entregada()->create(['webhook_endpoint_id' => $endpoint->id]);

        $this->get(route('partner.webhooks'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PartnerPanel/Webhooks')
                ->count('endpoints', 1)
                ->count('entregas', 1));
    });

    it('vinculaciones permite solicitar desde el panel', function () {
        partner_en_panel();
        Contribuyente::factory()->create(['ruc' => '0992479248001']);

        $this->post(route('partner.vinculaciones.solicitar'), ['ruc' => '0992479248001'])
            ->assertRedirect(route('partner.vinculaciones'));

        $this->get(route('partner.vinculaciones'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PartnerPanel/Vinculaciones')
                ->count('vinculaciones.data', 1)
                ->where('vinculaciones.data.0.estado', 'pendiente'));
    });

    it('tokens crea (visible una vez) y revoca', function () {
        $partner = partner_en_panel();

        $this->post(route('partner.tokens.store'), ['nombre' => 'POS producción'])
            ->assertRedirect(route('partner.tokens'))
            ->assertSessionHas('token');

        expect($partner->tokens()->count())->toBe(1);

        $tokenId = $partner->tokens()->first()->id;
        $this->delete(route('partner.tokens.destroy', $tokenId))
            ->assertRedirect(route('partner.tokens'));

        expect($partner->tokens()->count())->toBe(0);
    });
});
