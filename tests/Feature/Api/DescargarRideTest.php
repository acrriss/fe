<?php

use App\Models\Comprobante;
use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\TipoComprobante;
use App\Sri\ValueObjects\ClaveAcceso;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->contribuyente = actuar_como_contribuyente();
});

/**
 * Crea un registro autorizado del contribuyente autenticado cuyo XML
 * firmado es el golden del tipo dado.
 */
function comprobante_autorizado_con_xml(string $tipo, ?string $dataClass = null): Comprobante
{
    $registro = Comprobante::factory()->autorizado()->create([
        'tipo' => TipoComprobante::fromRootElement($tipo),
        'contribuyente_id' => test()->contribuyente->id,
    ]);

    $xml = file_get_contents(golden_path("$tipo/comprobante.xml"));

    // el golden trae la clave del legado; para NC/retención regeneramos el
    // XML con la clave del registro para mantener coherencia
    if ($dataClass !== null) {
        $comprobante = $dataClass::from(golden_input($tipo));
        $comprobante->infoTributaria->claveAcceso = ClaveAcceso::fromString($registro->clave_acceso);
        $xml = ConstruirXml::render($comprobante);
    }

    Storage::put($path = "comprobantes/{$registro->clave_acceso}.xml", $xml);
    $registro->update(['xml_path' => $path]);

    return $registro;
}

it('genera y descarga el RIDE en PDF de una factura autorizada', function () {
    $registro = comprobante_autorizado_con_xml('factura');

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    $respuesta->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($respuesta->getContent())->toStartWith('%PDF')
        // queda cacheado para descargas futuras
        ->and($registro->refresh()->ride_path)->not->toBeNull();

    Storage::assertExists($registro->ride_path);
});

it('genera el RIDE de :dataset', function (string $tipo, string $dataClass) {
    $registro = comprobante_autorizado_con_xml($tipo, $dataClass);

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    $respuesta->assertSuccessful();
    expect($respuesta->getContent())->toStartWith('%PDF');
})->with([
    'notaCredito' => ['notaCredito', NotaCreditoData::class],
    'comprobanteRetencion' => ['comprobanteRetencion', ComprobanteRetencionData::class],
]);

it('sirve el RIDE cacheado sin regenerarlo', function () {
    $registro = comprobante_autorizado_con_xml('factura');
    Storage::put($ridePath = "rides/{$registro->clave_acceso}.pdf", '%PDF-cacheado');
    $registro->update(['ride_path' => $ridePath]);

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    expect($respuesta->getContent())->toBe('%PDF-cacheado');
});

it('responde 409 si el comprobante no está autorizado', function () {
    $registro = Comprobante::factory()->create([
        'contribuyente_id' => $this->contribuyente->id,
    ]); // pendiente

    $this->getJson(route('api.v1.comprobantes.ride', $registro))
        ->assertStatus(409);
});

it('responde 404 si el XML ya no está disponible', function () {
    $registro = Comprobante::factory()->autorizado()->create([
        'contribuyente_id' => $this->contribuyente->id,
    ]);

    $this->getJson(route('api.v1.comprobantes.ride', $registro))
        ->assertNotFound();
});
