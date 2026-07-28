<?php

use App\Models\Contribuyente;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

describe('registro', function () {
    it('crea contribuyente + usuario administrador y entra al panel', function () {
        $respuesta = $this->post(route('registro.store'), [
            'razon_social' => 'Mi Empresa S.A.',
            'ruc' => '0992479248001',
            'name' => 'Ana Pérez',
            'email' => 'ana@miempresa.ec',
            'password' => 'clave-segura',
            'password_confirmation' => 'clave-segura',
        ]);

        $respuesta->assertRedirect(route('panel.inicio'));
        $this->assertAuthenticated();

        $contribuyente = Contribuyente::where('ruc', '0992479248001')->first();
        expect($contribuyente)->not->toBeNull()
            ->and($contribuyente->razon_social)->toBe('Mi Empresa S.A.')
            ->and($contribuyente->users)->toHaveCount(1)
            ->and($contribuyente->users->first()->email)->toBe('ana@miempresa.ec');
    });

    it('valida ruc y correo únicos', function () {
        $existente = Contribuyente::factory()->create();
        User::factory()->create(['email' => 'repetido@x.ec']);

        $this->post(route('registro.store'), [
            'razon_social' => 'Otra',
            'ruc' => $existente->ruc,
            'name' => 'X',
            'email' => 'repetido@x.ec',
            'password' => 'clave-segura',
            'password_confirmation' => 'clave-segura',
        ])->assertInvalid(['ruc', 'email']);
    });
});

describe('login', function () {
    it('autentica con credenciales válidas', function () {
        User::factory()->create(['email' => 'ana@x.ec', 'password' => 'clave-segura']);

        $this->post(route('login.store'), [
            'email' => 'ana@x.ec',
            'password' => 'clave-segura',
        ])->assertRedirect(route('panel.inicio'));

        $this->assertAuthenticated();
    });

    it('rechaza credenciales inválidas', function () {
        User::factory()->create(['email' => 'ana@x.ec']);

        $this->post(route('login.store'), [
            'email' => 'ana@x.ec',
            'password' => 'incorrecta',
        ])->assertInvalid(['email']);

        $this->assertGuest();
    });

    it('devuelve al panel a quien ya tiene sesión', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('panel.inicio'));
    });

    it('cierra sesión', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    });
});

it('el panel exige sesión', function () {
    $this->get(route('panel.inicio'))->assertRedirect(route('login'));
});
