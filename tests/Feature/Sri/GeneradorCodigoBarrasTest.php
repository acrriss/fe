<?php

use App\Sri\Ride\GeneradorCodigoBarras;

it('genera un data-uri SVG con el código de barras de la clave de acceso', function () {
    $clave = trim(file_get_contents(golden_path('factura/claveAcceso.txt')));

    $dataUri = new GeneradorCodigoBarras()->svgDataUri($clave);

    expect($dataUri)->toStartWith('data:image/svg+xml;base64,');

    $svg = base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')));

    expect($svg)->toContain('<svg')
        ->toContain('</svg>')
        ->toContain('<rect'); // las barras
});

it('codifica claves de acceso de 49 dígitos (longitud impar) sin fallar', function () {
    // el caso borde de Code 128-C: longitud impar exige cambio de code-set
    $clave = str_repeat('1', 49);

    $svg = base64_decode(substr(
        new GeneradorCodigoBarras()->svgDataUri($clave),
        strlen('data:image/svg+xml;base64,'),
    ));

    expect($svg)->toContain('<svg')
        ->and(substr_count($svg, '<rect'))->toBeGreaterThan(10);
});
