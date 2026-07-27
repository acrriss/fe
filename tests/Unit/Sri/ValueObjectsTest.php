<?php

use App\Sri\Exceptions\DatoInvalido;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;

describe('Ruc', function () {
    it('acepta 13 dígitos', function () {
        expect((string) Ruc::fromString('0922596788001'))->toBe('0922596788001');
    });

    it('rechaza formatos inválidos', function (string $valor) {
        Ruc::fromString($valor);
    })->with(['092259678800', '09225967880011', 'abcdefghijklm', ''])
        ->throws(DatoInvalido::class);

    it('rechaza 13 dígitos con dígito verificador incorrecto', function (string $valor) {
        Ruc::fromString($valor);
    })->with([
        '0922596789001', // persona natural: módulo 10
        '0992223334001', // sociedad privada: módulo 11
        '0922596788000', // establecimiento cero
    ])->throws(DatoInvalido::class);
});

describe('Secuencial', function () {
    it('acepta 9 dígitos', function () {
        expect((string) Secuencial::fromString('000004303'))->toBe('000004303');
    });

    it('rellena con ceros desde un entero', function () {
        expect((string) Secuencial::fromInt(42))->toBe('000000042');
    });

    it('rechaza enteros fuera de rango', function (int $numero) {
        Secuencial::fromInt($numero);
    })->with([0, -1, 1_000_000_000])->throws(DatoInvalido::class);
});

describe('CodigoNumerico', function () {
    it('acepta 8 dígitos', function () {
        expect((string) CodigoNumerico::fromString('22568496'))->toBe('22568496');
    });

    it('genera aleatorios válidos de 8 dígitos', function () {
        expect((string) CodigoNumerico::aleatorio())->toMatch('/^\d{8}$/');
    });

    it('rechaza formatos inválidos', function (string $valor) {
        CodigoNumerico::fromString($valor);
    })->with(['1234567', '123456789', 'abcdefgh'])->throws(DatoInvalido::class);
});
