<?php

/*
|--------------------------------------------------------------------------
| Red de seguridad — fixtures golden-master (Fase 0)
|--------------------------------------------------------------------------
|
| Estos tests protegen los snapshots del comportamiento del legado que viven
| en fixtures/golden. En la Fase 2, los value objects y builders del nuevo
| dominio se testearán CONTRA estos mismos fixtures: para cada input.json el
| nuevo código debe producir exactamente claveAcceso.txt y comprobante.xml.
|
*/

$tipos = ['factura', 'notaCredito', 'comprobanteRetencion'];

/**
 * Módulo 11 según la ficha técnica del SRI: pesos 2..7 de derecha a
 * izquierda; 11 - (total % 11); 11 → 0, 10 → 1.
 */
function sri_mod11(string $cadena): int
{
    $peso = 2;
    $total = 0;

    for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
        $total += (int) $cadena[$i] * $peso;
        $peso = $peso === 7 ? 2 : $peso + 1;
    }

    $verificador = 11 - ($total % 11);

    return match ($verificador) {
        11 => 0,
        10 => 1,
        default => $verificador,
    };
}

it('tiene el set completo de fixtures para :dataset', function (string $tipo) {
    expect(golden_path("$tipo/input.json"))->toBeReadableFile()
        ->and(golden_path("$tipo/claveAcceso.txt"))->toBeReadableFile()
        ->and(golden_path("$tipo/comprobante.xml"))->toBeReadableFile()
        ->and(golden_path("$tipo/meta.json"))->toBeReadableFile();
})->with($tipos);

it('la clave de acceso golden de :dataset tiene 49 dígitos y verificador módulo 11 válido', function (string $tipo) {
    $clave = trim(file_get_contents(golden_path("$tipo/claveAcceso.txt")));

    expect($clave)->toMatch('/^\d{49}$/');

    $cuerpo = substr($clave, 0, 48);
    $verificador = (int) $clave[48];

    expect(sri_mod11($cuerpo))->toBe($verificador);
})->with($tipos);

it('el XML golden de :dataset está bien formado y con la raíz esperada', function (string $tipo) {
    $xml = simplexml_load_file(golden_path("$tipo/comprobante.xml"));
    $meta = json_decode(file_get_contents(golden_path("$tipo/meta.json")), true);

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe($tipo)
        ->and((string) $xml['id'])->toBe('comprobante')
        ->and((string) $xml['version'])->toBe($meta['xmlVersionAttr'])
        ->and((string) $xml->infoTributaria->claveAcceso)->toBe($meta['claveAcceso']);
})->with($tipos);

it('los vectores de casos borde del módulo 11 son consistentes', function () {
    $vectors = json_decode(file_get_contents(golden_path('claveAcceso-vectors.json')), true);

    expect($vectors)->not->toBeEmpty();

    foreach ($vectors as $vector) {
        expect(sri_mod11($vector['cadena']))
            ->toBe($vector['verificadorLegado'], "caso {$vector['caso']}")
            ->toBe($vector['verificadorIndependiente']);
    }
});
