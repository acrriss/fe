<?php

use App\Models\Comprobante;
use App\Models\Contribuyente;
use App\Models\Partner;
use App\Models\User;
use App\Sri\Actions\ConstruirXml;
use App\Sri\Enums\TipoComprobante;
use App\Sri\ValueObjects\CertificadoFirma;
use App\Sri\ValueObjects\ClaveAcceso;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Los tests de Feature usan el TestCase de Laravel (app booteada); los de
| Unit son PHPUnit puro. Los payloads de comprobantes de prueba viven en
| tests/Payloads.php (un builder por tipo).
|
*/

require_once __DIR__.'/Payloads.php';

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

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

    // por defecto, el RUC de los payloads de prueba: así coinciden con el
    // contribuyente autenticado
    $contribuyente = $factory->create($atributos + ['ruc' => RUC_PRUEBA]);

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
 * el RUC de los payloads de prueba, como actuar_como_contribuyente()).
 */
function contribuyente_gestionado(Partner $partner, array $atributos = []): Contribuyente
{
    return Contribuyente::factory()
        ->conCertificado()
        ->create($atributos + ['ruc' => RUC_PRUEBA, 'partner_id' => $partner->id]);
}

/**
 * Crea un comprobante autorizado del contribuyente dado cuyo XML firmado es
 * el del comprobante de prueba del tipo indicado (con la clave de acceso
 * del registro), ya guardado en el disco (requiere Storage::fake()). Base
 * común de las descargas de RIDE y XML.
 */
function comprobante_autorizado_con_xml(Contribuyente $contribuyente, string $tipo): Comprobante
{
    $registro = Comprobante::factory()->autorizado()->create([
        'tipo' => TipoComprobante::fromRootElement($tipo),
        'contribuyente_id' => $contribuyente->id,
    ]);

    $comprobante = comprobante_de_prueba($tipo);
    $comprobante->infoTributaria->claveAcceso = ClaveAcceso::fromString($registro->clave_acceso);

    Storage::put($path = "comprobantes/{$registro->clave_acceso}.xml", ConstruirXml::render($comprobante));
    $registro->update(['xml_path' => $path]);

    return $registro;
}
