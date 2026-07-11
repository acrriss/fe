<?php

use App\Sri\Firma\VerificadorXades;

/*
 * El fixture factura-firmada-jar.xml es el XML golden firmado por el jar
 * heredado (la estructura que el SRI acepta en producción). Si nuestro
 * verificador lo valida al 100%, nuestra canonicalización C14N es
 * compatible con la del SRI — el prerequisito del firmador nativo.
 */

function xml_firmado_por_el_jar(): string
{
    return file_get_contents(base_path('tests/Fixtures/factura-firmada-jar.xml'));
}

it('ORÁCULO: la firma del jar verifica al 100% con nuestro C14N', function () {
    $resultado = new VerificadorXades()->verificar(xml_firmado_por_el_jar());

    expect($resultado->referencias)->toHaveCount(3)
        ->and($resultado->referencias)->each->toBeTrue()
        ->and($resultado->firmaValida)->toBeTrue()
        ->and($resultado->esValida())->toBeTrue();
});

it('detecta la manipulación del contenido del comprobante', function () {
    $adulterado = str_replace('<importeTotal>11.20</importeTotal>', '<importeTotal>99.99</importeTotal>', xml_firmado_por_el_jar());

    $resultado = new VerificadorXades()->verificar($adulterado);

    expect($resultado->referencias['#comprobante'])->toBeFalse()
        ->and($resultado->esValida())->toBeFalse();
});

it('detecta un SignatureValue corrupto', function () {
    // invierte los primeros caracteres del base64 de la firma
    $adulterado = preg_replace_callback(
        '/(<ds:SignatureValue[^>]*>\s*)(\w{4})/s',
        fn (array $m): string => $m[1].strrev($m[2]),
        xml_firmado_por_el_jar(),
        1,
    );

    $resultado = new VerificadorXades()->verificar($adulterado);

    expect($resultado->firmaValida)->toBeFalse()
        ->and($resultado->esValida())->toBeFalse();
});

it('rechaza documentos sin firma o malformados', function (string $xml) {
    new VerificadorXades()->verificar($xml);
})->with([
    'sin firma' => fn (): string => file_get_contents(golden_path('factura/comprobante.xml')),
    'malformado' => 'esto no es xml',
])->throws(InvalidArgumentException::class);
