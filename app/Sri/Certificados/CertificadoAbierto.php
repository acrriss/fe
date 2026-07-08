<?php

namespace App\Sri\Certificados;

use Carbon\CarbonImmutable;

/**
 * Contenido de un PKCS#12 ya abierto: el certificado X.509, su clave
 * privada y los metadatos de identidad y vigencia.
 *
 * Es la pieza compartida entre la validación en la carga (hoy) y el
 * firmador XAdES nativo (futuro): ambos parten de aquí.
 */
final readonly class CertificadoAbierto
{
    public function __construct(
        public string $certificadoPem,
        public string $clavePrivadaPem,
        public string $titular,
        public string $emisor,
        public CarbonImmutable $validoDesde,
        public CarbonImmutable $validoHasta,
    ) {}

    public function vencido(): bool
    {
        return $this->validoHasta->isPast();
    }

    public function venceDentroDe(int $dias): bool
    {
        return ! $this->vencido() && $this->validoHasta->lessThan(now()->addDays($dias));
    }
}
