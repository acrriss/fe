<?php

namespace App\Sri\Contracts;

use App\Sri\ValueObjects\CertificadoFirma;

/**
 * Firma XAdES-BES del XML del comprobante. La implementación real delega en
 * el jar heredado (java); la fake evita esa dependencia en los tests.
 */
interface XmlSigner
{
    /**
     * @return string el XML firmado
     */
    public function firmar(string $xml, CertificadoFirma $certificado): string;
}
