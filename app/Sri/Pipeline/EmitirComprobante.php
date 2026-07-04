<?php

namespace App\Sri\Pipeline;

use App\Sri\Actions\ConstruirXml;
use App\Sri\Actions\EnviarRecepcion;
use App\Sri\Actions\FirmarXml;
use App\Sri\Actions\GenerarClaveAcceso;
use App\Sri\Actions\SolicitarAutorizacion;
use Illuminate\Support\Facades\Pipeline;

/**
 * Orquesta la emisión completa de un comprobante electrónico.
 *
 * Este pipeline es el núcleo compartido: el endpoint síncrono lo ejecuta
 * inline y el flujo asíncrono lo ejecutará desde un Job con el mismo código.
 */
final class EmitirComprobante
{
    /**
     * @var list<class-string>
     */
    private const array ETAPAS = [
        GenerarClaveAcceso::class,
        ConstruirXml::class,
        FirmarXml::class,
        EnviarRecepcion::class,
        SolicitarAutorizacion::class,
    ];

    public function emitir(EmisionComprobante $emision): EmisionComprobante
    {
        $resultado = Pipeline::send($emision)
            ->through(self::ETAPAS)
            ->thenReturn();

        assert($resultado instanceof EmisionComprobante);

        return $resultado;
    }
}
