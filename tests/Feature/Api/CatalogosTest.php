<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Catalogos\CodigosAuxiliares;

/*
 * Los catálogos son tablas de la ficha técnica: norma publicada, sin datos
 * de ningún contribuyente. Se sirven sin token y con ETag para que el
 * integrador los consulte al abrir sus formularios.
 */

it('sirve el catálogo de códigos auxiliares sin autenticación', function () {
    $this->getJson(route('api.v1.catalogos.codigos-auxiliares'))
        ->assertSuccessful()
        ->assertJsonPath('ficha', '2.34')
        ->assertJsonPath('grupos.0.clave', 'materiales_construccion')
        ->assertJsonPath('grupos.1.clave', 'transporte_comercial')
        ->assertJsonStructure(['ficha', 'version', 'grupos' => [['clave', 'nombre', 'anexo', 'tabla', 'baseLegal', 'obligatorioDesde', 'tagXml', 'codigos' => [['codigo', 'descripcion']]]]]);
});

it('transcribe las tablas 31 y 32 completas', function () {
    $respuesta = $this->getJson(route('api.v1.catalogos.codigos-auxiliares'));

    expect($respuesta->json('grupos.0.codigos'))->toHaveCount(18)
        ->and($respuesta->json('grupos.1.codigos'))->toHaveCount(2)
        ->and(CodigosAuxiliares::todos())->toHaveCount(20);

    // primero y último de la Tabla 31, y los dos de la Tabla 32
    expect($respuesta->json('grupos.0.codigos.0.codigo'))->toBe('F010101')
        ->and($respuesta->json('grupos.0.codigos.17.codigo'))->toBe('F010804')
        ->and(array_column($respuesta->json('grupos.1.codigos'), 'codigo'))->toBe(['H492001', 'H492002']);
});

/*
 * El dato que evita la trampa del Anexo 23: la ficha nombra solo
 * <codigoAuxiliar>, pero el formato de la nota de crédito llama
 * <codigoAdicional> a ese mismo campo.
 */
it('dice en qué tag va el código según el tipo de comprobante', function () {
    $this->getJson(route('api.v1.catalogos.codigos-auxiliares'))
        ->assertJsonPath('grupos.0.tagXml.factura', 'codigoAuxiliar')
        ->assertJsonPath('grupos.0.tagXml.liquidacionCompra', 'codigoAuxiliar')
        ->assertJsonPath('grupos.0.tagXml.notaCredito', 'codigoAdicional');
});

it('anota la vigencia y la base legal de cada grupo', function () {
    $this->getJson(route('api.v1.catalogos.codigos-auxiliares'))
        ->assertJsonPath('grupos.0.baseLegal', 'NAC-DGERCGC24-00000013')
        // la ficha no cita resolución para los códigos de transporte (§1),
        // solo su fecha de obligatoriedad
        ->assertJsonPath('grupos.1.baseLegal', null)
        ->assertJsonPath('grupos.1.obligatorioDesde', '2025-11-01');
});

it('se puede revalidar con ETag y responde 304 sin cuerpo', function () {
    $etag = $this->getJson(route('api.v1.catalogos.codigos-auxiliares'))->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson(route('api.v1.catalogos.codigos-auxiliares'))
        ->assertStatus(304);
});

it('lista los catálogos disponibles con su URL', function () {
    $this->getJson(route('api.v1.catalogos.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.clave', 'codigos-auxiliares')
        ->assertJsonPath('data.0.url', route('api.v1.catalogos.codigos-auxiliares'));
});

/*
 * El catálogo es informativo: la obligación es por ítem y solo el emisor
 * sabe cuándo aplica, así que el servicio no rechaza otros valores (el
 * campo es el código auxiliar de uso general del emisor).
 */
it('no convierte el catálogo en una lista cerrada al emitir', function () {
    $factura = comprobante_de_prueba('factura');
    $factura->detalles[0]->codigoAuxiliar = 'BARRAS-778899';

    expect(ConstruirXml::render($factura))
        ->toContain('<codigoAuxiliar>BARRAS-778899</codigoAuxiliar>');
});
