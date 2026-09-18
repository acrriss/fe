<?php

use App\Models\Comprobante;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->contribuyente = actuar_como_contribuyente();
});

it('genera y descarga el RIDE en PDF de una factura autorizada', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    $respuesta->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($respuesta->getContent())->toStartWith('%PDF')
        // queda cacheado para descargas futuras
        ->and($registro->refresh()->ride_path)->not->toBeNull();

    Storage::assertExists($registro->ride_path);
});

it('genera el RIDE de :dataset', function (string $tipo, string $dataClass) {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, $tipo, $dataClass);

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    $respuesta->assertSuccessful();
    expect($respuesta->getContent())->toStartWith('%PDF');
})->with([
    'notaCredito' => ['notaCredito', NotaCreditoData::class],
    'comprobanteRetencion' => ['comprobanteRetencion', ComprobanteRetencionData::class],
]);

/*
 * El RIDE viaja adjunto en cada correo al comprador, así que su peso es
 * coste por factura emitida. Sin subsetting de fuentes se incrustaba DejaVu
 * Sans entera y el PDF pasaba de ~29 KB a ~863 KB; el umbral generoso deja
 * sitio al contenido pero atrapa una fuente completa.
 */
it('genera un RIDE liviano, sin incrustar la fuente entera', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    expect(strlen($respuesta->getContent()))->toBeLessThan(150 * 1024);
});

it('sirve el RIDE cacheado sin regenerarlo', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
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
