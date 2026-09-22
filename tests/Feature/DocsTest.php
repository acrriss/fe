<?php

use Symfony\Component\Yaml\Yaml;

it('sirve la página de documentación públicamente (sin auth)', function () {
    $this->get(route('docs'))
        ->assertSuccessful()
        ->assertSee('id="app"', false);
});

it('sirve el OpenAPI como YAML públicamente', function () {
    $respuesta = $this->get(route('docs.spec'));

    $respuesta->assertSuccessful()
        ->assertHeader('Content-Type', 'application/yaml; charset=utf-8');

    expect($respuesta->getContent())
        ->toContain('openapi: 3.1.0')
        ->toContain('/comprobantes')
        // el spec está al día con los seis tipos
        ->toContain('liquidacionCompra')
        ->toContain('notaDebito')
        ->toContain('guiaRemision');
});

it('el spec documenta las rutas reales de la API', function () {
    $spec = $this->get(route('docs.spec'))->getContent();

    foreach (['/tokens', '/contribuyente/certificado', '/comprobantes/{id}/reintentar', '/comprobantes/{id}/ride'] as $path) {
        expect($spec)->toContain($path);
    }
});

/*
 * El spec se edita a mano y la página de docs lo sirve tal cual: si deja de
 * ser YAML válido, el visor queda en blanco sin que nada falle antes.
 */
it('el spec es YAML válido y expone los catálogos', function () {
    $spec = Yaml::parse((string) $this->get(route('docs.spec'))->getContent());

    expect($spec)->toBeArray()
        ->and($spec['openapi'])->toBe('3.1.0')
        ->and($spec['paths'])->toHaveKeys(['/catalogos', '/catalogos/codigos-auxiliares']);
});
