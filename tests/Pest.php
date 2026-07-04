<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature usan el TestCase de Laravel (app booteada); los de
| Unit son PHPUnit puro. Los fixtures golden-master viven en fixtures/golden
| y se acceden con el helper golden_path().
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function golden_path(string $path = ''): string
{
    return dirname(__DIR__).'/fixtures/golden'.($path !== '' ? '/'.ltrim($path, '/') : '');
}

/**
 * Subárbol del comprobante dentro del input.json golden del tipo dado.
 */
function golden_input(string $tipo): array
{
    $payload = json_decode(file_get_contents(golden_path("$tipo/input.json")), true);

    return $payload[$tipo];
}

/**
 * Payload golden completo listo para POSTear (con certificado dummy en
 * lugar de los placeholders de sanitización).
 */
function golden_payload(string $tipo): array
{
    $payload = json_decode(file_get_contents(golden_path("$tipo/input.json")), true);
    $payload['info']['p12'] = base64_encode('certificado-dummy');
    $payload['info']['clavep12'] = 'secreto';

    return $payload;
}
