<?php

use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;
use Carbon\CarbonImmutable;

/**
 * Módulo 11 tal como lo describe la ficha técnica (§5.2): pesos 2..7 de
 * derecha a izquierda, 11 - (suma % 11); 11 → 0, 10 → 1. Implementación
 * independiente de la del dominio, para contrastarla.
 */
function modulo11_segun_la_ficha(string $cadena): int
{
    $peso = 2;
    $suma = 0;

    for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
        $suma += (int) $cadena[$i] * $peso;
        $peso = $peso === 7 ? 2 : $peso + 1;
    }

    $verificador = 11 - ($suma % 11);

    return match ($verificador) {
        11 => 0,
        10 => 1,
        default => $verificador,
    };
}

function clave_de_ejemplo(): ClaveAcceso
{
    return ClaveAcceso::generar(
        fechaEmision: CarbonImmutable::createFromFormat('d/m/Y', '10/07/2026'),
        tipoComprobante: TipoComprobante::Factura,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '001',
        puntoEmision: '001',
        secuencial: Secuencial::fromString('000000001'),
        codigoNumerico: CodigoNumerico::fromString('12345678'),
    );
}

it('compone los 49 dígitos según la Tabla 1 de la ficha (§5.2)', function () {
    $clave = clave_de_ejemplo()->value;

    expect($clave)->toHaveLength(49)
        ->and(substr($clave, 0, 8))->toBe('10072026')       // fecha ddmmaaaa
        ->and(substr($clave, 8, 2))->toBe('01')             // tipo de comprobante (Tabla 3)
        ->and(substr($clave, 10, 13))->toBe('0922596788001') // RUC
        ->and(substr($clave, 23, 1))->toBe('1')             // ambiente (Tabla 4)
        ->and(substr($clave, 24, 6))->toBe('001001')        // serie estab + ptoEmi
        ->and(substr($clave, 30, 9))->toBe('000000001')     // secuencial
        ->and(substr($clave, 39, 8))->toBe('12345678')      // código numérico
        ->and(substr($clave, 47, 1))->toBe('1')             // tipo de emisión (Tabla 2)
        ->and((int) $clave[48])->toBe(modulo11_segun_la_ficha(substr($clave, 0, 48)));
});

it('reproduce el ejemplo de módulo 11 de la ficha (§5.2)', function () {
    expect(ClaveAcceso::digitoVerificador('41261533'))->toBe(6);
});

it('resuelve los casos borde del módulo 11: :dataset', function (string $cadena, int $esperado) {
    expect(ClaveAcceso::digitoVerificador($cadena))->toBe($esperado)
        ->and(modulo11_segun_la_ficha($cadena))->toBe($esperado);
})->with([
    'resto 11 → 0' => ['071220220109225967880011001001000000001225684961', 0],
    'resto 10 → 1' => ['071220220109225967880011001001000000010225684961', 1],
    'resto ordinario' => ['071220220109225967880011001001000000000225684961', 5],
]);

it('acepta una clave válida con fromString', function () {
    $valida = clave_de_ejemplo()->value;

    expect(ClaveAcceso::fromString($valida)->value)->toBe($valida);
});

it('rechaza una clave con dígito verificador incorrecto', function () {
    $valida = clave_de_ejemplo()->value;
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
        fechaEmision: CarbonImmutable::createFromFormat('d/m/Y', '10/07/2026'),
        tipoComprobante: TipoComprobante::Factura,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '1', // inválido: debe ser 3 dígitos
        puntoEmision: '001',
        secuencial: Secuencial::fromInt(1),
        codigoNumerico: CodigoNumerico::aleatorio(),
    );
})->throws(DatoInvalido::class);
