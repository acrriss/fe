<?php

namespace App\Sri\Actions;

use App\Sri\Pipeline\EmisionComprobante;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use Closure;

/**
 * Genera la clave de acceso de 49 dígitos y la asigna al comprobante.
 *
 * Si la emisión no trae un código numérico explícito se usa uno aleatorio
 * por comprobante (el legado hardcodeaba "22568496" para todos).
 */
final class GenerarClaveAcceso
{
    public function __invoke(EmisionComprobante $emision, Closure $next): mixed
    {
        $comprobante = $emision->comprobante;

        $emision->claveAcceso = ClaveAcceso::generar(
            fechaEmision: $comprobante->fechaEmision(),
            tipoComprobante: $comprobante::tipo(),
            ruc: $comprobante->infoTributaria->ruc,
            ambiente: $comprobante->infoTributaria->ambiente,
            establecimiento: $comprobante->infoTributaria->estab,
            puntoEmision: $comprobante->infoTributaria->ptoEmi,
            secuencial: $comprobante->infoTributaria->secuencial,
            codigoNumerico: $emision->codigoNumerico ?? CodigoNumerico::aleatorio(),
            tipoEmision: $comprobante->infoTributaria->tipoEmision,
        );

        $comprobante->infoTributaria->claveAcceso = $emision->claveAcceso;

        return $next($emision);
    }
}
