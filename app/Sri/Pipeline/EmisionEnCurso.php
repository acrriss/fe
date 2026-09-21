<?php

namespace App\Sri\Pipeline;

use App\Sri\Data\ComprobanteData;
use App\Sri\Respuestas\RespuestaAutorizacion;
use App\Sri\Respuestas\RespuestaRecepcion;
use App\Sri\ValueObjects\CertificadoFirma;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\LeyendasEmisor;

/**
 * Estado que viaja por el pipeline de emisión: entra con el comprobante,
 * el certificado y las leyendas del emisor; cada Action añade su resultado
 * (clave, XML, firma, respuestas del SRI).
 */
final class EmisionEnCurso
{
    public ?ClaveAcceso $claveAcceso = null;

    public ?string $xml = null;

    public ?string $xmlFirmado = null;

    public ?RespuestaRecepcion $recepcion = null;

    public ?RespuestaAutorizacion $autorizacion = null;

    public function __construct(
        public readonly ComprobanteData $comprobante,
        public readonly CertificadoFirma $certificado,
        public readonly ?CodigoNumerico $codigoNumerico = null,
        public readonly LeyendasEmisor $leyendas = new LeyendasEmisor,
    ) {}

    public function claveAcceso(): ClaveAcceso
    {
        return $this->claveAcceso
            ?? throw new \LogicException('La clave de acceso aún no fue generada.');
    }

    public function xmlFirmado(): string
    {
        return $this->xmlFirmado
            ?? throw new \LogicException('El comprobante aún no fue firmado.');
    }
}
