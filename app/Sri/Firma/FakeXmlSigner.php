<?php

namespace App\Sri\Firma;

use App\Sri\Contracts\XmlSigner;
use App\Sri\ValueObjects\CertificadoFirma;

/**
 * Doble de pruebas del firmador: añade una "firma" reconocible al XML sin
 * necesitar java ni certificados reales.
 */
final class FakeXmlSigner implements XmlSigner
{
    public ?string $xmlFirmado = null;

    public function firmar(string $xml, CertificadoFirma $certificado): string
    {
        return $this->xmlFirmado = $xml.'<!--firma-fake-->';
    }
}
