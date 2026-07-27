<?php

use App\Sri\Enums\TipoIdentificacion;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Support\ValidadorIdentificacion;

/**
 * Valida sin lanzar; devuelve true para poder expresarlo como expectativa.
 */
function identificacionValida(TipoIdentificacion $tipo, string $valor): bool
{
    ValidadorIdentificacion::validar($tipo, $valor, 'identificacionComprador');

    return true;
}

describe('cédula (05)', function () {
    it('acepta cédulas con dígito verificador correcto', function (string $cedula) {
        expect(identificacionValida(TipoIdentificacion::Cedula, $cedula))->toBeTrue();
    })->with([
        '1713328506',
        '0922596788',
        '3034567895', // provincia 30: registrados en el exterior
    ]);

    it('rechaza el dígito verificador incorrecto', function (string $cedula) {
        identificacionValida(TipoIdentificacion::Cedula, $cedula);
    })->with([
        '1713328505', // misma base, último dígito cambiado
        '1713328500',
        '1750863147',
        '1716849140',
    ])->throws(DatoInvalido::class);

    it('rechaza provincias fuera de rango', function (string $cedula) {
        identificacionValida(TipoIdentificacion::Cedula, $cedula);
    })->with(['0013328506', '2513328506', '3113328506', '9913328506'])
        ->throws(DatoInvalido::class);

    it('rechaza un tercer dígito de 6 en adelante', function (string $cedula) {
        identificacionValida(TipoIdentificacion::Cedula, $cedula);
    })->with(['1763328506', '1793328506'])->throws(DatoInvalido::class);

    it('rechaza longitudes y caracteres inválidos', function (string $cedula) {
        identificacionValida(TipoIdentificacion::Cedula, $cedula);
    })->with(['171332850', '17133285061', '', 'abcdefghij', '1713328506001'])
        ->throws(DatoInvalido::class);
});

describe('RUC (04)', function () {
    it('acepta RUC de persona natural, sociedad privada y sociedad pública', function (string $ruc) {
        expect(identificacionValida(TipoIdentificacion::Ruc, $ruc))->toBeTrue();
    })->with([
        '0922596788001', // natural
        '1713328506001', // natural
        '0992479248001', // privada (3er dígito 9)
        '1791411099001', // privada
        '1760000150001', // pública (3er dígito 6)
        '0160050020001', // pública
    ]);

    it('valida la cédula base del RUC de persona natural', function (string $ruc) {
        identificacionValida(TipoIdentificacion::Ruc, $ruc);
    })->with([
        '1713328505001', // base 1713328505: dígito verificador incorrecto
        '1716849140001',
    ])->throws(DatoInvalido::class);

    it('rechaza el dígito verificador incorrecto de sociedades', function (string $ruc) {
        identificacionValida(TipoIdentificacion::Ruc, $ruc);
    })->with([
        '0992223334001', // privada
        '1790012345001', // privada
        '0990000000001', // privada: base con residuo 1, dígito imposible
        '1760000110001', // pública
    ])->throws(DatoInvalido::class);

    it('acepta establecimientos distintos de 001', function (string $ruc) {
        expect(identificacionValida(TipoIdentificacion::Ruc, $ruc))->toBeTrue();
    })->with([
        '0922596788002',
        '0992479248017',
        '1760000150002',
    ]);

    it('rechaza el establecimiento cero', function (string $ruc) {
        identificacionValida(TipoIdentificacion::Ruc, $ruc);
    })->with([
        '0922596788000', // natural
        '0992479248000', // privada
        '1760000150000', // pública
    ])->throws(DatoInvalido::class);

    it('rechaza un tercer dígito de 7 u 8', function (string $ruc) {
        identificacionValida(TipoIdentificacion::Ruc, $ruc);
    })->with(['1770000150001', '1780000150001'])->throws(DatoInvalido::class);

    it('rechaza provincias fuera de rango', function (string $ruc) {
        identificacionValida(TipoIdentificacion::Ruc, $ruc);
    })->with(['0092259678001', '2592259678001', '9992259678001'])
        ->throws(DatoInvalido::class);

    it('rechaza longitudes inválidas', function (string $ruc) {
        identificacionValida(TipoIdentificacion::Ruc, $ruc);
    })->with(['092259678800', '09225967880011', '0922596788', ''])
        ->throws(DatoInvalido::class);
});

describe('consumidor final (07)', function () {
    it('acepta exactamente 9999999999999', function () {
        expect(identificacionValida(TipoIdentificacion::ConsumidorFinal, '9999999999999'))->toBeTrue();
    });

    it('rechaza cualquier otro valor', function (string $valor) {
        identificacionValida(TipoIdentificacion::ConsumidorFinal, $valor);
    })->with(['999999999999', '99999999999999', '0922596788001', ''])
        ->throws(DatoInvalido::class);
});

describe('documentos sin dígito verificador (06 y 08)', function () {
    it('acepta pasaporte con cualquier forma no vacía', function (string $valor) {
        expect(identificacionValida(TipoIdentificacion::Pasaporte, $valor))->toBeTrue();
    })->with(['AB123456', 'X1', '1750863147', 'PA-99/2026']);

    it('acepta identificación del exterior', function () {
        expect(identificacionValida(TipoIdentificacion::IdentificacionExterior, 'FR-8891233'))->toBeTrue();
    });

    it('rechaza vacío, solo espacios o más de 20 caracteres', function (string $valor) {
        identificacionValida(TipoIdentificacion::Pasaporte, $valor);
    })->with(['', '   ', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'])->throws(DatoInvalido::class);
});

describe('sin tipo declarado (destinatario de guía de remisión)', function () {
    it('infiere RUC con 13 dígitos y cédula con 10', function (string $valor) {
        ValidadorIdentificacion::validarSinTipo($valor, 'identificacionDestinatario');
    })->with([
        '1713328506001',
        '0992479248001',
        '1713328506',
        '9999999999999', // consumidor final también viaja sin tipo
    ])->throwsNoExceptions();

    it('rechaza dígitos verificadores inválidos cuando la forma es reconocible', function (string $valor) {
        ValidadorIdentificacion::validarSinTipo($valor, 'identificacionDestinatario');
    })->with(['1716849140001', '1750863147', '0992223334001'])
        ->throws(DatoInvalido::class);

    it('deja pasar documentos libres, incluidos los numéricos de otra longitud', function (string $valor) {
        ValidadorIdentificacion::validarSinTipo($valor, 'identificacionDestinatario');
    })->with(['AB123456', '12345678', 'X1'])->throwsNoExceptions();

    it('rechaza el valor vacío', function () {
        ValidadorIdentificacion::validarSinTipo('', 'identificacionDestinatario');
    })->throws(DatoInvalido::class);
});

describe('dígitos verificadores', function () {
    it('calcula el módulo 10 de una cédula', function () {
        expect(ValidadorIdentificacion::digitoVerificadorModulo10('171332850'))->toBe(6)
            ->and(ValidadorIdentificacion::digitoVerificadorModulo10('092259678'))->toBe(8);
    });

    it('devuelve 0 cuando el módulo 11 da residuo 0', function () {
        // suma ponderada 55, múltiplo de 11: el dígito sale 11 y se normaliza a 0
        expect(ValidadorIdentificacion::digitoVerificadorModulo11('099002000', [4, 3, 2, 7, 6, 5, 4, 3, 2]))->toBe(0);
    });

    it('devuelve null cuando el dígito saldría 10', function () {
        expect(ValidadorIdentificacion::digitoVerificadorModulo11('099000000', [4, 3, 2, 7, 6, 5, 4, 3, 2]))->toBeNull();
    });

    it('mensaje de error nombra el campo y el valor recibido', function () {
        expect(fn () => ValidadorIdentificacion::validar(TipoIdentificacion::Cedula, '1713328505', 'identificacionComprador'))
            ->toThrow(DatoInvalido::class, 'identificacionComprador');
    });
});
