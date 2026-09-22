<?php

namespace App\Sri\Actions;

use App\Sri\Data\ComprobanteData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Pipeline\EmisionEnCurso;
use Closure;

/**
 * Añade el RUC del proveedor del sistema al bloque de información
 * adicional (Resolución NAC-DGERCGC26-00000027, Art. 5).
 *
 * El nombre del campo es literal y lo fija la Ficha Técnica: cualquier
 * variación deja de cumplir. Va como etapa del pipeline y no dentro del
 * DTO para que el comprobante siga siendo lo que el cliente envió: el
 * campo lo aporta el servicio en la emisión, no el payload.
 */
final class AgregarRucProveedor
{
    public const string NOMBRE_CAMPO = 'RUC Proveedor';

    public function __invoke(EmisionEnCurso $emision, Closure $next): mixed
    {
        $ruc = self::rucConfigurado();

        if ($ruc !== null) {
            self::agregar($emision->comprobante, $ruc);
        }

        return $next($emision);
    }

    /**
     * El campo es una afirmación del SERVICIO sobre quién provee el sistema,
     * no un dato del emisor: si el cliente lo manda, se rechaza.
     *
     * Sin esto, cualquier integrador desactivaba la declaración con una
     * línea de JSON —y nadie lo validaba, porque para el SRI `infoAdicional`
     * es texto libre—. Se comprueba sobre el payload de entrada y no al
     * construir el XML, para no romper la relectura del XML ya emitido (que
     * sí lleva el campo) al generar el RIDE.
     */
    public static function rechazarSiVieneEnElPayload(ComprobanteData $comprobante): void
    {
        if ($comprobante->tieneCampoAdicional(self::NOMBRE_CAMPO)) {
            throw new DatoInvalido(
                'El campo adicional «'.self::NOMBRE_CAMPO.'» lo fija el servicio de '
                .'facturación electrónica: no debe enviarse en el comprobante.',
            );
        }
    }

    public static function agregar(ComprobanteData $comprobante, string $ruc): void
    {
        $comprobante->agregarCampoAdicional(self::NOMBRE_CAMPO, $ruc);
    }

    private static function rucConfigurado(): ?string
    {
        $ruc = config()->string('sri.ruc_proveedor');

        return $ruc !== '' ? $ruc : null;
    }
}
