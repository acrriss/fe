<?php

namespace App\Sri\Actions;

use App\Sri\Data\ComprobanteData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use Closure;

/**
 * Genera la clave de acceso de 49 dígitos y la asigna al comprobante.
 *
 * Si la emisión ya trae una clave se respeta, verificando antes que
 * corresponda a los datos del comprobante. Dos casos la traen: el
 * reintento de §5.10 de la ficha (un comprobante rechazado se reenvía con
 * LA MISMA clave y secuencial) y la emisión asíncrona, donde el endpoint
 * ya se la entregó al cliente al encolar.
 *
 * Si no trae código numérico explícito se usa uno aleatorio por
 * comprobante (el legado hardcodeaba "22568496" para todos).
 */
final class GenerarClaveAcceso
{
    public function __invoke(EmisionEnCurso $emision, Closure $next): mixed
    {
        $emision->claveAcceso = self::para(
            $emision->comprobante,
            $emision->claveAcceso,
            $emision->codigoNumerico,
        );

        $emision->comprobante->infoTributaria->claveAcceso = $emision->claveAcceso;

        return $next($emision);
    }

    /**
     * La clave de un comprobante depende SOLO de datos que el payload ya
     * trae: fecha, tipo, RUC, ambiente, serie, secuencial y un código
     * numérico. Ni red, ni certificado, ni SRI. Por eso puede calcularse
     * antes de encolar la emisión y devolverse al cliente en el acto —que
     * es lo que permite imprimir un RIDE en el momento de la venta— y por
     * eso vive en un estático además de en la etapa del pipeline.
     *
     * Fijarla al crear el registro también evita que un reintento técnico
     * del job (tras un timeout del SRI que quizá sí llegó) sortee un
     * código numérico nuevo y acabe emitiendo dos claves distintas para el
     * mismo secuencial.
     */
    public static function para(
        ComprobanteData $comprobante,
        ?ClaveAcceso $existente = null,
        ?CodigoNumerico $codigoNumerico = null,
    ): ClaveAcceso {
        $infoTributaria = $comprobante->infoTributaria;

        $prefijoEsperado = ClaveAcceso::prefijo(
            fechaEmision: $comprobante->fechaEmision(),
            tipoComprobante: $comprobante::tipo(),
            ruc: $infoTributaria->ruc,
            ambiente: $infoTributaria->ambiente,
            establecimiento: $infoTributaria->estab,
            puntoEmision: $infoTributaria->ptoEmi,
            secuencial: $infoTributaria->secuencial,
        );

        if ($existente instanceof ClaveAcceso) {
            if (! str_starts_with($existente->value, $prefijoEsperado)) {
                throw DatoInvalido::porFormato(
                    'claveAcceso',
                    'una clave cuyo prefijo corresponda al comprobante (fecha, tipo, RUC, ambiente, serie y secuencial no pueden cambiar al reintentar)',
                    $existente->value,
                );
            }

            return $existente;
        }

        return ClaveAcceso::generar(
            fechaEmision: $comprobante->fechaEmision(),
            tipoComprobante: $comprobante::tipo(),
            ruc: $infoTributaria->ruc,
            ambiente: $infoTributaria->ambiente,
            establecimiento: $infoTributaria->estab,
            puntoEmision: $infoTributaria->ptoEmi,
            secuencial: $infoTributaria->secuencial,
            codigoNumerico: $codigoNumerico ?? CodigoNumerico::aleatorio(),
            tipoEmision: $infoTributaria->tipoEmision,
        );
    }
}
