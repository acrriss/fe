<?php

use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;
use Carbon\CarbonImmutable;

it('reproduce la clave de acceso golden de la factura del legado', function () {
    $esperada = trim(file_get_contents(golden_path('factura/claveAcceso.txt')));

    $clave = ClaveAcceso::generar(
        fechaEmision: CarbonImmutable::createFromFormat('d/m/Y', '07/12/2022'),
        tipoComprobante: TipoComprobante::Factura,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '001',
        puntoEmision: '001',
        secuencial: Secuencial::fromString('000004303'),
        codigoNumerico: CodigoNumerico::fromString('22568496'), // el hardcodeado del legado
    );

    expect($clave->value)->toBe($esperada)->toHaveLength(49);
});

it('reproduce todos los vectores golden del módulo 11', function () {
    $vectores = json_decode(file_get_contents(golden_path('claveAcceso-vectors.json')), true);

    foreach ($vectores as $vector) {
        expect(ClaveAcceso::digitoVerificador($vector['cadena']))
            ->toBe($vector['verificadorLegado'], "caso {$vector['caso']}");
    }
});

it('acepta una clave válida con fromString', function () {
    $valida = trim(file_get_contents(golden_path('factura/claveAcceso.txt')));

    expect(ClaveAcceso::fromString($valida)->value)->toBe($valida);
});

it('rechaza una clave con dígito verificador incorrecto', function () {
    $valida = trim(file_get_contents(golden_path('factura/claveAcceso.txt')));
    $corrupta = substr($valida, 0, 48).((int) $valida[48] === 9 ? '0' : (string) ((int) $valida[48] + 1));

    ClaveAcceso::fromString($corrupta);
})->throws(DatoInvalido::class);

it('rechaza claves con longitud o caracteres inválidos', function (string $valor) {
    ClaveAcceso::fromString($valor);
})->with([
    'muy corta' => '123',
    'con letras' => str_repeat('a', 49),
    'vacía' => '',
])->throws(DatoInvalido::class);

it('valida el formato de establecimiento y punto de emisión', function () {
    ClaveAcceso::generar(
        fechaEmision: CarbonImmutable::createFromFormat('d/m/Y', '07/12/2022'),
        tipoComprobante: TipoComprobante::Factura,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '1', // inválido: debe ser 3 dígitos
        puntoEmision: '001',
        secuencial: Secuencial::fromInt(1),
        codigoNumerico: CodigoNumerico::aleatorio(),
    );
})->throws(DatoInvalido::class);
