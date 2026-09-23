<?php

use App\Sri\Catalogos\FormasPago;

/*
 * Tabla 24 publicada como catálogo: el integrador construye su selector
 * desde aquí en vez de transcribir la tabla, y sabe que la lista es cerrada.
 */

it('publica la Tabla 24 completa, sin autenticación', function () {
    $respuesta = $this->getJson(route('api.v1.catalogos.formas-pago'));

    $respuesta->assertSuccessful()
        ->assertJsonPath('ficha', '2.34')
        ->assertJsonPath('tabla', 24)
        // la lista es cerrada y el servicio la valida: el integrador lo sabe
        ->assertJsonPath('validado', true);

    $codigos = array_column($respuesta->json('codigos'), 'codigo');

    expect($codigos)->toBe(['01', '15', '16', '17', '18', '19', '20', '21']);
});

it('cada código trae su nombre y la fecha desde la que rige', function () {
    $codigos = collect($this->getJson(route('api.v1.catalogos.formas-pago'))->json('codigos'))
        ->keyBy('codigo');

    expect($codigos['01']['nombre'])->toBe('Sin utilización del sistema financiero')
        ->and($codigos['19']['nombre'])->toBe('Tarjeta de crédito')
        ->and($codigos['19']['desde'])->toBe('2016-06-01');
});

it('aparece en el índice de catálogos', function () {
    $claves = array_column($this->getJson(route('api.v1.catalogos.index'))->json('data'), 'clave');

    expect($claves)->toContain('formas-pago');
});

it('se revalida con ETag en vez de descargarse entero', function () {
    $etag = $this->getJson(route('api.v1.catalogos.formas-pago'))->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson(route('api.v1.catalogos.formas-pago'))
        ->assertStatus(304);
});

/*
 * El catálogo es la fuente de la validación, no una copia suya: si alguien
 * añade un código a la tabla, el validador lo acepta sin tocar nada más.
 */
it('el catálogo y el validador son la misma tabla', function () {
    $publicados = array_column($this->getJson(route('api.v1.catalogos.formas-pago'))->json('codigos'), 'codigo');

    expect($publicados)->toBe(FormasPago::todos());
});
