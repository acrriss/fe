<?php

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
