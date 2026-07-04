<?php

namespace App\Sri\Actions;

use App\Sri\Contracts\XmlSigner;
use App\Sri\Pipeline\EmisionComprobante;
use Closure;

/**
 * Firma el XML con el certificado de la emisión (XAdES-BES).
 */
final class FirmarXml
{
    public function __construct(private readonly XmlSigner $signer) {}

    public function __invoke(EmisionComprobante $emision, Closure $next): mixed
    {
        $xml = $emision->xml
            ?? throw new \LogicException('El XML debe construirse antes de firmarse.');

        $emision->xmlFirmado = $this->signer->firmar($xml, $emision->certificado);

        return $next($emision);
    }
}
