<?php

use App\Models\Comprobante;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Enums\Ambiente;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->contribuyente = actuar_como_contribuyente();
});

it('descarga el XML autorizado de una factura', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
    $registro->update([
        'numero_autorizacion' => $registro->clave_acceso,
        'autorizado_en' => now(),
        'ambiente' => Ambiente::Produccion,
    ]);

    $respuesta = $this->get(route('api.v1.comprobantes.xml', $registro));

    $respuesta->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertHeader('Content-Disposition', "attachment; filename=\"{$registro->clave_acceso}.xml\"");

    $xml = simplexml_load_string($respuesta->getContent());

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('autorizacion')
        ->and((string) $xml->estado)->toBe('AUTORIZADO')
        ->and((string) $xml->numeroAutorizacion)->toBe($registro->clave_acceso)
        ->and((string) $xml->ambiente)->toBe('PRODUCCIÓN')
        ->and((string) $xml->fechaAutorizacion)->toBe($registro->refresh()->autorizado_en->toIso8601String());
});

it('envuelve el XML firmado en CDATA sin alterarlo', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
    $firmado = Storage::get($registro->xml_path);

    $respuesta = $this->get(route('api.v1.comprobantes.xml', $registro));

    $xml = simplexml_load_string($respuesta->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);

    // el receptor debe poder extraer el comprobante y validar su firma
    expect((string) $xml->comprobante)->toBe($firmado);
});

it('usa el numero de autorizacion del SRI cuando difiere de la clave', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
    $registro->update(['numero_autorizacion' => '1234567890']);

    $respuesta = $this->get(route('api.v1.comprobantes.xml', $registro));

    $xml = simplexml_load_string($respuesta->getContent());

    expect((string) $xml->numeroAutorizacion)->toBe('1234567890');
});

it('descarga el XML autorizado de una nota de credito', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'notaCredito', NotaCreditoData::class);

    $respuesta = $this->get(route('api.v1.comprobantes.xml', $registro));

    $respuesta->assertSuccessful();

    $xml = simplexml_load_string($respuesta->getContent());

    expect((string) $xml->estado)->toBe('AUTORIZADO');
});

it('responde 409 si el comprobante no esta autorizado', function () {
    $registro = Comprobante::factory()->create([
        'contribuyente_id' => $this->contribuyente->id,
    ]); // pendiente

    $this->getJson(route('api.v1.comprobantes.xml', $registro))
        ->assertStatus(409);
});

it('responde 404 si el XML ya no esta disponible', function () {
    $registro = Comprobante::factory()->autorizado()->create([
        'contribuyente_id' => $this->contribuyente->id,
    ]);

    $this->getJson(route('api.v1.comprobantes.xml', $registro))
        ->assertNotFound();
});

it('responde 404 para un comprobante de otro contribuyente', function () {
    $ajeno = Comprobante::factory()->autorizado()->create();

    $this->getJson(route('api.v1.comprobantes.xml', $ajeno))
        ->assertNotFound();
});
