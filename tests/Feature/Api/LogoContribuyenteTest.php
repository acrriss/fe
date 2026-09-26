<?php

use App\Http\Middleware\ResolverContribuyente;
use App\Models\Contribuyente;
use App\Sri\Ride\LogoRide;
use App\Sri\Ride\NormalizadorLogo;
use Illuminate\Support\Facades\Storage;

/*
 * Logo del emisor para el RIDE (ficha, Tabla 11 fila 10: «Imagen —
 * Opcional»). La ficha no fija formato: se normaliza a un PNG de 600 × 300.
 */

beforeEach(function () {
    Storage::fake();
});

function logo_en_base64(int $ancho = 400, int $alto = 200, string $tipo = 'png'): string
{
    return base64_encode(imagen_de_prueba($ancho, $alto, $tipo));
}

describe('PUT /contribuyente/logo', function () {
    it('guarda el logo normalizado a un PNG de 600 × 300', function () {
        $contribuyente = actuar_como_contribuyente();

        $this->putJson(route('api.v1.contribuyente.logo'), ['logo' => logo_en_base64(1000, 1000, 'jpeg')])
            ->assertNoContent();

        $ruta = (string) $contribuyente->refresh()->logo_path;
        $info = getimagesizefromstring((string) Storage::get($ruta));

        expect($ruta)->toStartWith("logos/{$contribuyente->uuid}-")->toEndWith('.png')
            ->and([$info[0], $info[1], $info[2]])->toBe([NormalizadorLogo::ANCHO, NormalizadorLogo::ALTO, IMAGETYPE_PNG]);
    });

    it('acepta el logo como data-uri, como lo entrega el recortador del navegador', function () {
        $contribuyente = actuar_como_contribuyente();

        $this->putJson(route('api.v1.contribuyente.logo'), ['logo' => 'data:image/png;base64,'.logo_en_base64()])
            ->assertNoContent();

        expect($contribuyente->refresh()->logo_path)->not->toBeNull();
    });

    it('al reemplazarlo borra el archivo del logo anterior', function () {
        $contribuyente = actuar_como_contribuyente();
        $contribuyente->guardarLogo(LogoRide::desdeImagen(imagen_de_prueba(300, 300)));
        $anterior = (string) $contribuyente->logo_path;

        $this->putJson(route('api.v1.contribuyente.logo'), ['logo' => logo_en_base64(800, 200)])
            ->assertNoContent();

        expect($contribuyente->refresh()->logo_path)->not->toBe($anterior);
        Storage::assertMissing($anterior);
        Storage::assertExists((string) $contribuyente->logo_path);
    });

    it('rechaza sin logo', function () {
        actuar_como_contribuyente();

        $this->putJson(route('api.v1.contribuyente.logo'), [])->assertInvalid(['logo']);
    });

    it('rechaza un base64 inválido', function () {
        actuar_como_contribuyente();

        $this->putJson(route('api.v1.contribuyente.logo'), ['logo' => '%%%no-es-base64%%%'])
            ->assertInvalid(['logo' => 'base64']);
    });

    it('rechaza lo que no es PNG, JPEG o WebP', function () {
        actuar_como_contribuyente();

        $this->putJson(route('api.v1.contribuyente.logo'), ['logo' => logo_en_base64(tipo: 'gif')])
            ->assertInvalid(['logo' => 'PNG, JPEG o WebP']);
    });

    it('rechaza un logo de más de 2 MB', function () {
        actuar_como_contribuyente();

        $this->putJson(route('api.v1.contribuyente.logo'), ['logo' => base64_encode(str_repeat('a', NormalizadorLogo::MAXIMO_BYTES + 3))])
            ->assertInvalid(['logo' => '2 MB']);
    });

    it('rechaza peticiones sin token', function () {
        $this->putJson(route('api.v1.contribuyente.logo'), ['logo' => logo_en_base64()])->assertUnauthorized();
    });
});

describe('DELETE /contribuyente/logo', function () {
    it('quita el logo y borra su archivo', function () {
        $contribuyente = actuar_como_contribuyente();
        $contribuyente->guardarLogo(LogoRide::desdeImagen(imagen_de_prueba()));
        $ruta = (string) $contribuyente->logo_path;

        $this->deleteJson(route('api.v1.contribuyente.logo.quitar'))->assertNoContent();

        expect($contribuyente->refresh()->logo_path)->toBeNull();
        Storage::assertMissing($ruta);
    });

    it('es idempotente: sin logo también responde 204', function () {
        actuar_como_contribuyente();

        $this->deleteJson(route('api.v1.contribuyente.logo.quitar'))->assertNoContent();
    });
});

describe('a nombre de un contribuyente gestionado (partner)', function () {
    it('guarda el logo del contribuyente de la cabecera X-Contribuyente', function () {
        $partner = actuar_como_partner();
        $gestionado = contribuyente_gestionado($partner);

        $this->putJson(
            route('api.v1.contribuyente.logo'),
            ['logo' => logo_en_base64()],
            [ResolverContribuyente::CABECERA => $gestionado->uuid],
        )->assertNoContent();

        expect($gestionado->refresh()->logo_path)->not->toBeNull();
    });

    it('responde 404 con un contribuyente que el partner no gestiona, sin tocar su logo', function () {
        actuar_como_partner();
        $ajeno = Contribuyente::factory()->create();

        $this->putJson(
            route('api.v1.contribuyente.logo'),
            ['logo' => logo_en_base64()],
            [ResolverContribuyente::CABECERA => $ajeno->uuid],
        )->assertNotFound();

        $this->deleteJson(
            route('api.v1.contribuyente.logo.quitar'),
            [],
            [ResolverContribuyente::CABECERA => $ajeno->uuid],
        )->assertNotFound();

        expect($ajeno->refresh()->logo_path)->toBeNull();
    });
});
