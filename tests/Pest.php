<?php

use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\User;
use App\Sri\ValueObjects\CertificadoFirma;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature usan el TestCase de Laravel (app booteada); los de
| Unit son PHPUnit puro. Los fixtures golden-master viven en fixtures/golden
| y se acceden con el helper golden_path().
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function golden_path(string $path = ''): string
{
    return dirname(__DIR__).'/fixtures/golden'.($path !== '' ? '/'.ltrim($path, '/') : '');
}

/**
 * Subárbol del comprobante dentro del input.json golden del tipo dado.
 */
function golden_input(string $tipo): array
{
    $payload = json_decode(file_get_contents(golden_path("$tipo/input.json")), true);

    return $payload[$tipo];
}

/**
 * Payload golden completo listo para POSTear. El bloque `info` del payload
 * legado se conserva (la API lo ignora: el certificado vive en el
 * contribuyente autenticado).
 */
function golden_payload(string $tipo): array
{
    $payload = json_decode(file_get_contents(golden_path("$tipo/input.json")), true);
    $payload['info']['p12'] = base64_encode('certificado-dummy');
    $payload['info']['clavep12'] = 'secreto';

    return $payload;
}

/**
 * Contenido binario del certificado .p12 de prueba (clave: clave-prueba).
 */
function p12_de_prueba(bool $legacy = false): string
{
    $archivo = $legacy ? 'certificado-prueba-legacy.p12' : 'certificado-prueba.p12';

    return file_get_contents(dirname(__DIR__)."/tests/Fixtures/{$archivo}");
}

/**
 * El certificado de prueba como value object listo para firmar.
 */
function certificado_de_prueba(string $clave = 'clave-prueba'): CertificadoFirma
{
    return CertificadoFirma::desdeBase64(base64_encode(p12_de_prueba()), $clave);
}

/**
 * Crea un contribuyente (con certificado por defecto) con un usuario
 * autenticado vía Sanctum, y lo devuelve.
 */
function actuar_como_contribuyente(bool $conCertificado = true, array $atributos = []): Contribuyente
{
    $factory = Contribuyente::factory();

    if ($conCertificado) {
        $factory = $factory->conCertificado();
    }

    // por defecto, el RUC de los fixtures golden: así los payloads de los
    // tests coinciden con el contribuyente autenticado
    $contribuyente = $factory->create($atributos + ['ruc' => '0922596788001']);

    Sanctum::actingAs(
        User::factory()->create(['contribuyente_id' => $contribuyente->id]),
    );

    return $contribuyente;
}

/**
 * Crea un partner autenticado vía Sanctum (plano de gestión y on-behalf)
 * y lo devuelve.
 */
function actuar_como_partner(array $atributos = []): Partner
{
    $partner = Partner::factory()->create($atributos);

    Sanctum::actingAs($partner);

    return $partner;
}

/**
 * Contribuyente gestionado por el partner (con certificado y, por defecto,
 * el RUC de los fixtures golden, como actuar_como_contribuyente()).
 */
function contribuyente_gestionado(Partner $partner, array $atributos = []): Contribuyente
{
    return Contribuyente::factory()
        ->conCertificado()
        ->create($atributos + ['ruc' => '0922596788001', 'partner_id' => $partner->id]);
}
