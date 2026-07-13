<?php

use App\Models\Partner;
use Illuminate\Support\Facades\Hash;

describe('partner:crear', function () {
    it('crea el partner y muestra su token inicial', function () {
        $this->artisan('partner:crear', ['nombre' => 'POS Andino', '--cuota' => '500'])
            ->expectsOutputToContain('Token de API')
            ->assertSuccessful();

        $partner = Partner::where('slug', 'pos-andino')->first();

        expect($partner)->not->toBeNull()
            ->and($partner->cuota_mensual)->toBe(500)
            ->and($partner->limite_por_minuto)->toBe(60)
            ->and($partner->tokens()->count())->toBe(1);
    });

    it('sin --cuota el partner queda sin límite mensual', function () {
        $this->artisan('partner:crear', ['nombre' => 'POS Libre'])->assertSuccessful();

        expect(Partner::where('slug', 'pos-libre')->first()->cuota_mensual)->toBeNull();
    });

    it('falla si el slug ya existe', function () {
        Partner::factory()->create(['slug' => 'pos-andino']);

        $this->artisan('partner:crear', ['nombre' => 'POS Andino'])
            ->assertFailed();
    });
});

describe('partner:credenciales', function () {
    it('asigna email y contraseña para el panel', function () {
        Partner::factory()->create(['slug' => 'pos-andino']);

        $this->artisan('partner:credenciales', [
            'slug' => 'pos-andino',
            'email' => 'pos@ejemplo.test',
            '--password' => 'secreto-123',
        ])->assertSuccessful();

        $partner = Partner::where('slug', 'pos-andino')->first();

        expect($partner->email)->toBe('pos@ejemplo.test')
            ->and(Hash::check('secreto-123', $partner->password))->toBeTrue();
    });

    it('rechaza contraseñas cortas', function () {
        Partner::factory()->create(['slug' => 'pos-andino']);

        $this->artisan('partner:credenciales', [
            'slug' => 'pos-andino',
            'email' => 'pos@ejemplo.test',
            '--password' => 'corta',
        ])->assertFailed();
    });

    it('falla si el partner no existe', function () {
        $this->artisan('partner:credenciales', [
            'slug' => 'nadie',
            'email' => 'x@x.test',
            '--password' => 'secreto-123',
        ])->assertFailed();
    });
});

describe('partner:token', function () {
    it('emite un token adicional', function () {
        $partner = Partner::factory()->create(['slug' => 'pos-andino']);
        $partner->createToken('inicial');

        $this->artisan('partner:token', ['slug' => 'pos-andino'])
            ->expectsOutputToContain('Token de API')
            ->assertSuccessful();

        expect($partner->tokens()->count())->toBe(2);
    });

    it('con --revocar invalida los tokens anteriores', function () {
        $partner = Partner::factory()->create(['slug' => 'pos-andino']);
        $partner->createToken('inicial');
        $partner->createToken('viejo');

        $this->artisan('partner:token', ['slug' => 'pos-andino', '--revocar' => true])
            ->assertSuccessful();

        expect($partner->tokens()->count())->toBe(1);
    });

    it('falla si el partner no existe', function () {
        $this->artisan('partner:token', ['slug' => 'nadie'])->assertFailed();
    });
});
