<?php

use App\Sri\Gateways\SoapSriGateway;

function soap(array $data): stdClass
{
    return json_decode(json_encode($data), false);
}

describe('parseRecepcion', function () {
    it('interpreta una recepción exitosa', function () {
        $respuesta = new SoapSriGateway()->parseRecepcion(soap(['estado' => 'RECIBIDA']));

        expect($respuesta->recibida())->toBeTrue()
            ->and($respuesta->mensajes)->toBeEmpty();
    });

    it('interpreta una devolución con un solo mensaje (objeto, no lista)', function () {
        $respuesta = new SoapSriGateway()->parseRecepcion(soap([
            'estado' => 'DEVUELTA',
            'comprobantes' => ['comprobante' => [
                'claveAcceso' => '123',
                'mensajes' => ['mensaje' => [
                    'identificador' => '45',
                    'mensaje' => 'ERROR SECUENCIAL REGISTRADO',
                    'tipo' => 'ERROR',
                ]],
            ]],
        ]));

        expect($respuesta->recibida())->toBeFalse()
            ->and($respuesta->mensajes)->toHaveCount(1)
            ->and((string) $respuesta->mensajes[0])->toBe('(ERROR - 45) ERROR SECUENCIAL REGISTRADO');
    });

    it('interpreta múltiples mensajes (lista)', function () {
        $respuesta = new SoapSriGateway()->parseRecepcion(soap([
            'estado' => 'DEVUELTA',
            'comprobantes' => ['comprobante' => [
                'mensajes' => ['mensaje' => [
                    ['identificador' => '45', 'mensaje' => 'ERROR A'],
                    ['identificador' => '46', 'mensaje' => 'ERROR B', 'informacionAdicional' => 'detalle'],
                ]],
            ]],
        ]));

        expect($respuesta->mensajes)->toHaveCount(2)
            ->and((string) $respuesta->mensajes[1])->toContain('detalle');
    });
});

describe('parseAutorizacion', function () {
    it('interpreta una autorización (objeto único)', function () {
        $respuesta = new SoapSriGateway()->parseAutorizacion(soap([
            'claveAccesoConsultada' => '123',
            'numeroComprobantes' => 1,
            'autorizaciones' => ['autorizacion' => [
                'estado' => 'AUTORIZADO',
                'numeroAutorizacion' => '0712202201092259678800110010010000043032256849615',
                'fechaAutorizacion' => '2022-12-07T14:30:00-05:00',
            ]],
        ]));

        expect($respuesta->autorizado())->toBeTrue()
            ->and($respuesta->numeroAutorizacion)->toBe('0712202201092259678800110010010000043032256849615')
            ->and($respuesta->fechaAutorizacion?->format('Y-m-d'))->toBe('2022-12-07');
    });

    it('prefiere la autorización AUTORIZADO entre varios envíos', function () {
        $respuesta = new SoapSriGateway()->parseAutorizacion(soap([
            'autorizaciones' => ['autorizacion' => [
                ['estado' => 'NO AUTORIZADO', 'mensajes' => ['mensaje' => ['identificador' => '60', 'mensaje' => 'CLAVE REGISTRADA']]],
                ['estado' => 'AUTORIZADO', 'numeroAutorizacion' => '999'],
            ]],
        ]));

        expect($respuesta->autorizado())->toBeTrue()
            ->and($respuesta->numeroAutorizacion)->toBe('999');
    });

    it('reporta rechazo con sus mensajes', function () {
        $respuesta = new SoapSriGateway()->parseAutorizacion(soap([
            'autorizaciones' => ['autorizacion' => [
                'estado' => 'NO AUTORIZADO',
                'mensajes' => ['mensaje' => ['identificador' => '60', 'mensaje' => 'CLAVE ACCESO REGISTRADA', 'tipo' => 'ERROR']],
            ]],
        ]));

        expect($respuesta->autorizado())->toBeFalse()
            ->and($respuesta->mensajes)->toHaveCount(1);
    });

    it('reporta "en procesamiento" cuando aún no hay autorizaciones', function () {
        $respuesta = new SoapSriGateway()->parseAutorizacion(soap(['numeroComprobantes' => 0]));

        expect($respuesta->enProcesamiento())->toBeTrue()
            ->and($respuesta->autorizado())->toBeFalse();
    });
});
