<?php

use App\Sri\Certificados\CertificadoAbierto;
use App\Sri\Certificados\LectorPkcs12;
use App\Sri\Exceptions\CertificadoInvalido;
use App\Sri\ValueObjects\CertificadoFirma;
use Carbon\CarbonImmutable;

function certificado_firma(string $p12, string $clave = 'clave-prueba'): CertificadoFirma
{
    return CertificadoFirma::desdeBase64(base64_encode($p12), $clave);
}

it('abre un .p12 moderno y extrae los metadatos', function () {
    $abierto = new LectorPkcs12()->abrir(certificado_firma(p12_de_prueba()));

    expect($abierto->titular)->toBe('CERTIFICADO DE PRUEBA')
        ->and($abierto->emisor)->toBe('CERTIFICADO DE PRUEBA') // autofirmado
        ->and($abierto->validoHasta->isFuture())->toBeTrue()
        ->and($abierto->vencido())->toBeFalse()
        ->and($abierto->certificadoPem)->toContain('BEGIN CERTIFICATE')
        ->and($abierto->clavePrivadaPem)->toContain('PRIVATE KEY');
});

it('abre un .p12 legacy (RC2/3DES) vía el fallback al binario openssl', function () {
    // este fixture NO puede abrirse con openssl_pkcs12_read bajo OpenSSL 3
    $nativo = openssl_pkcs12_read(p12_de_prueba(legacy: true), $r, 'clave-prueba');
    while (openssl_error_string() !== false); // drenar

    $abierto = new LectorPkcs12()->abrir(certificado_firma(p12_de_prueba(legacy: true)));

    expect($nativo)->toBeFalse() // el fallback era realmente necesario
        ->and($abierto->titular)->toBe('CERTIFICADO DE PRUEBA')
        ->and($abierto->clavePrivadaPem)->toContain('PRIVATE KEY');
});

it('rechaza la clave incorrecta con un mensaje claro: :dataset', function (bool $legacy) {
    try {
        new LectorPkcs12()->abrir(certificado_firma(p12_de_prueba($legacy), 'clave-equivocada'));
        $this->fail('Debió lanzar CertificadoInvalido');
    } catch (CertificadoInvalido $excepcion) {
        expect($excepcion->getMessage())->toContain('clave del certificado no es correcta')
            ->and($excepcion->getMessage())->not->toContain('clave-equivocada');
    }
})->with(['moderno' => false, 'legacy' => true]);

it('rechaza un archivo que no es un p12', function () {
    new LectorPkcs12()->abrir(certificado_firma('esto no es un pkcs12'));
})->throws(CertificadoInvalido::class, 'no es un certificado .p12 válido');

it('detecta vencimiento y proximidad de vencimiento', function () {
    $vencido = new CertificadoAbierto(
        certificadoPem: 'x', clavePrivadaPem: 'x', titular: 'T', emisor: 'E',
        validoDesde: CarbonImmutable::now()->subYears(2),
        validoHasta: CarbonImmutable::now()->subDay(),
    );
    $porVencer = new CertificadoAbierto(
        certificadoPem: 'x', clavePrivadaPem: 'x', titular: 'T', emisor: 'E',
        validoDesde: CarbonImmutable::now()->subYear(),
        validoHasta: CarbonImmutable::now()->addDays(10),
    );

    expect($vencido->vencido())->toBeTrue()
        ->and($vencido->venceDentroDe(30))->toBeFalse() // ya vencido ≠ por vencer
        ->and($porVencer->vencido())->toBeFalse()
        ->and($porVencer->venceDentroDe(30))->toBeTrue()
        ->and($porVencer->venceDentroDe(5))->toBeFalse();
});
