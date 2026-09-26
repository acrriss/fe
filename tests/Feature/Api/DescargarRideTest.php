<?php

use App\Models\Comprobante;
use App\Sri\Contracts\RideGenerator;
use App\Sri\Ride\LogoRide;
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

it('genera el RIDE de :dataset', function (string $tipo) {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, $tipo);

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    $respuesta->assertSuccessful();
    expect($respuesta->getContent())->toStartWith('%PDF');
})->with([
    'notaCredito',
    'comprobanteRetencion',
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

function ride_cacheado_vigente(Comprobante $registro, string $contenido = '%PDF-cacheado'): string
{
    $huella = app(RideGenerator::class)->huella($registro);
    Storage::put($ridePath = "rides/{$registro->clave_acceso}-{$huella}.pdf", $contenido);
    $registro->update(['ride_path' => $ridePath]);

    return $ridePath;
}

it('sirve el RIDE cacheado sin regenerarlo', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
    ride_cacheado_vigente($registro);

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    expect($respuesta->getContent())->toBe('%PDF-cacheado');
});

/*
 * El RIDE es una representación del XML autorizado, que no cambia: si
 * cambian la plantilla o el logo, el cacheado queda viejo y se regenera.
 */
it('regenera el RIDE cacheado cuando el contribuyente cambia su logo, y borra el viejo', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
    $viejo = ride_cacheado_vigente($registro);

    $this->contribuyente->guardarLogo(LogoRide::desdeImagen(imagen_de_prueba()));

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    expect($respuesta->getContent())->toStartWith('%PDF')->not->toBe('%PDF-cacheado')
        ->and($registro->refresh()->ride_path)->not->toBe($viejo);
    Storage::assertMissing($viejo);
    Storage::assertExists((string) $registro->ride_path);
});

it('regenera los RIDE cacheados antes de que la ruta llevara huella', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
    Storage::put($legado = "rides/{$registro->clave_acceso}.pdf", '%PDF-legado');
    $registro->update(['ride_path' => $legado]);

    $respuesta = $this->get(route('api.v1.comprobantes.ride', $registro));

    expect($respuesta->getContent())->not->toBe('%PDF-legado');
    Storage::assertMissing($legado);
});

it('la huella cambia con la versión de la plantilla y con el logo', function () {
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');
    $generador = app(RideGenerator::class);
    $sinLogo = $generador->huella($registro);

    $this->contribuyente->guardarLogo(LogoRide::desdeImagen(imagen_de_prueba()));
    $conLogo = $generador->huella($registro->refresh());

    $this->contribuyente->guardarLogo(LogoRide::desdeImagen(imagen_de_prueba(300, 300)));
    $otroLogo = $generador->huella($registro->refresh());

    expect($sinLogo)->not->toBe($conLogo)
        ->and($conLogo)->not->toBe($otroLogo)
        ->and($generador->huella($registro))->toBe($otroLogo);
});

it('incrusta el logo del contribuyente en el RIDE', function () {
    $this->contribuyente->guardarLogo(LogoRide::desdeImagen(imagen_de_prueba()));
    $registro = comprobante_autorizado_con_xml($this->contribuyente, 'factura');

    $conLogo = $this->get(route('api.v1.comprobantes.ride', $registro))->getContent();

    $this->contribuyente->quitarLogo();
    $sinLogo = $this->get(route('api.v1.comprobantes.ride', $registro->refresh()))->getContent();

    // dompdf embebe el PNG como XObject de imagen
    expect($conLogo)->toContain('/Subtype /Image')
        ->and($sinLogo)->not->toContain('/Subtype /Image')
        // el logo normalizado no dispara el peso del adjunto
        ->and(strlen($conLogo))->toBeLessThan(150 * 1024);
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
