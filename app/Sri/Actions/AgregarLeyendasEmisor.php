<?php

namespace App\Sri\Actions;

use App\Sri\Data\ComprobanteData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\ValueObjects\LeyendasEmisor;
use Closure;

/**
 * Vuelca en el comprobante las designaciones del emisor que la ficha obliga
 * a imprimir como leyenda (Anexo 21: agente de retención; Tabla 11 fila 8:
 * contribuyente especial).
 *
 * Como con el RUC del proveedor, van como etapa del pipeline y no en el
 * DTO que arma el cliente: son un atributo del contribuyente configurado
 * una vez, y así todos sus integradores cumplen sin tocar el payload.
 */
final class AgregarLeyendasEmisor
{
    public function __invoke(EmisionEnCurso $emision, Closure $next): mixed
    {
        self::agregar($emision->comprobante, $emision->leyendas);

        return $next($emision);
    }

    /**
     * Las leyendas son una afirmación sobre el emisor, no un dato de la
     * transacción: si el cliente las manda, se rechaza, para que no pueda
     * declararse agente de retención (o dejar de serlo) factura a factura.
     * Se comprueba sobre el payload y no al construir el XML, para no
     * romper la relectura del XML ya emitido (que sí las lleva) al generar
     * el RIDE.
     */
    public static function rechazarSiVieneEnElPayload(ComprobanteData $comprobante): void
    {
        if ($comprobante->infoTributaria->agenteRetencion !== null) {
            throw self::loFijaLaConfiguracion('agenteRetencion');
        }

        if ($comprobante->bloqueInfo()->contribuyenteEspecial !== null) {
            throw self::loFijaLaConfiguracion('contribuyenteEspecial');
        }
    }

    public static function agregar(ComprobanteData $comprobante, LeyendasEmisor $leyendas): void
    {
        $comprobante->infoTributaria->agenteRetencion = $leyendas->agenteRetencion;
        $comprobante->bloqueInfo()->contribuyenteEspecial = $leyendas->contribuyenteEspecial;
    }

    private static function loFijaLaConfiguracion(string $campo): DatoInvalido
    {
        return new DatoInvalido(
            "El campo «{$campo}» lo fija la configuración del contribuyente en el servicio "
            .'de facturación electrónica: no debe enviarse en el comprobante.',
        );
    }
}
