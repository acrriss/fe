<?php

namespace App\Sri\Actions;

use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Pipeline\EmisionComprobante;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use Closure;

/**
 * Genera la clave de acceso de 49 dígitos y la asigna al comprobante.
 *
 * Si la emisión ya trae una clave (reintento conforme a §5.10 de la ficha:
 * un comprobante rechazado se reenvía con LA MISMA clave y secuencial), se
 * respeta — verificando antes que corresponda a los datos del comprobante.
 *
 * Si no trae código numérico explícito se usa uno aleatorio por
 * comprobante (el legado hardcodeaba "22568496" para todos).
 */
final class GenerarClaveAcceso
{
    public function __invoke(EmisionComprobante $emision, Closure $next): mixed
    {
        $comprobante = $emision->comprobante;

        $prefijoEsperado = ClaveAcceso::prefijo(
            fechaEmision: $comprobante->fechaEmision(),
            tipoComprobante: $comprobante::tipo(),
            ruc: $comprobante->infoTributaria->ruc,
            ambiente: $comprobante->infoTributaria->ambiente,
            establecimiento: $comprobante->infoTributaria->estab,
            puntoEmision: $comprobante->infoTributaria->ptoEmi,
            secuencial: $comprobante->infoTributaria->secuencial,
        );

        if ($emision->claveAcceso instanceof ClaveAcceso) {
            if (! str_starts_with($emision->claveAcceso->value, $prefijoEsperado)) {
                throw DatoInvalido::porFormato(
                    'claveAcceso',
                    'una clave cuyo prefijo corresponda al comprobante (fecha, tipo, RUC, ambiente, serie y secuencial no pueden cambiar al reintentar)',
                    $emision->claveAcceso->value,
                );
            }
        } else {
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
        }

        $comprobante->infoTributaria->claveAcceso = $emision->claveAcceso;

        return $next($emision);
    }
}
