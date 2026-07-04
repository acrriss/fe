<?php

namespace App\Sri\Gateways;

use App\Sri\Contracts\SriGateway;
use App\Sri\Enums\Ambiente;
use App\Sri\Respuestas\MensajeSri;
use App\Sri\Respuestas\RespuestaAutorizacion;
use App\Sri\Respuestas\RespuestaRecepcion;
use App\Sri\Support\Payload;
use App\Sri\ValueObjects\ClaveAcceso;
use Carbon\CarbonImmutable;
use SoapClient;
use stdClass;

/**
 * Implementación SOAP real contra los web services del SRI.
 *
 * El parseo tolera la peculiaridad de SOAP de devolver un objeto suelto
 * cuando hay un solo elemento y un array cuando hay varios.
 */
class SoapSriGateway implements SriGateway
{
    public function recibir(string $xmlFirmado, Ambiente $ambiente): RespuestaRecepcion
    {
        $respuesta = $this->cliente('recepcion', $ambiente)
            ->__soapCall('validarComprobante', [['xml' => $xmlFirmado]]);

        return $this->parseRecepcion(self::objeto(data_get($respuesta, 'RespuestaRecepcionComprobante')));
    }

    public function autorizar(ClaveAcceso $claveAcceso, Ambiente $ambiente): RespuestaAutorizacion
    {
        $respuesta = $this->cliente('autorizacion', $ambiente)
            ->__soapCall('autorizacionComprobante', [['claveAccesoComprobante' => (string) $claveAcceso]]);

        return $this->parseAutorizacion(self::objeto(data_get($respuesta, 'RespuestaAutorizacionComprobante')));
    }

    public function parseRecepcion(stdClass $respuesta): RespuestaRecepcion
    {
        $mensajes = [];

        foreach (Payload::lista(data_get($respuesta, 'comprobantes.comprobante')) as $comprobante) {
            $mensajes = [...$mensajes, ...$this->parseMensajes(data_get($comprobante, 'mensajes'))];
        }

        return new RespuestaRecepcion(
            estado: self::texto(data_get($respuesta, 'estado')),
            mensajes: $mensajes,
        );
    }

    public function parseAutorizacion(stdClass $respuesta): RespuestaAutorizacion
    {
        $autorizaciones = array_values(array_filter(
            Payload::lista(data_get($respuesta, 'autorizaciones.autorizacion')),
            fn (mixed $autorizacion): bool => $autorizacion instanceof stdClass,
        ));

        if ($autorizaciones === []) {
            return new RespuestaAutorizacion(estado: '');
        }

        // El SRI devuelve una autorización por cada envío de la misma clave;
        // la vigente es la primera AUTORIZADO si existe, o la última recibida.
        $vigente = collect($autorizaciones)
            ->first(fn (stdClass $autorizacion): bool => data_get($autorizacion, 'estado') === 'AUTORIZADO')
            ?? $autorizaciones[array_key_last($autorizaciones)];

        $fecha = self::texto(data_get($vigente, 'fechaAutorizacion'));
        $numero = self::texto(data_get($vigente, 'numeroAutorizacion'));

        return new RespuestaAutorizacion(
            estado: self::texto(data_get($vigente, 'estado')),
            numeroAutorizacion: $numero !== '' ? $numero : null,
            fechaAutorizacion: $fecha !== '' ? CarbonImmutable::parse($fecha) : null,
            mensajes: $this->parseMensajes(data_get($vigente, 'mensajes')),
        );
    }

    /**
     * @return array<int, MensajeSri>
     */
    private function parseMensajes(mixed $mensajes): array
    {
        $resultado = [];

        foreach (Payload::lista(data_get($mensajes, 'mensaje')) as $mensaje) {
            $informacionAdicional = self::texto(data_get($mensaje, 'informacionAdicional'));
            $tipo = self::texto(data_get($mensaje, 'tipo'));

            $resultado[] = new MensajeSri(
                identificador: self::texto(data_get($mensaje, 'identificador')),
                mensaje: self::texto(data_get($mensaje, 'mensaje')),
                tipo: $tipo !== '' ? $tipo : null,
                informacionAdicional: $informacionAdicional !== '' ? $informacionAdicional : null,
            );
        }

        return $resultado;
    }

    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? (string) $valor : '';
    }

    private static function objeto(mixed $valor): stdClass
    {
        return $valor instanceof stdClass ? $valor : new stdClass;
    }

    protected function cliente(string $servicio, Ambiente $ambiente): SoapClient
    {
        $entorno = $ambiente->esProduccion() ? 'produccion' : 'pruebas';

        return new SoapClient(config()->string("sri.wsdl.{$entorno}.{$servicio}"), [
            'exceptions' => true,
            'connection_timeout' => 30,
        ]);
    }
}
