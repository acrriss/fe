<?php

use App\Sri\Catalogos\TarifasIva;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Exceptions\DatoInvalido;

/*
 * Tabla 17 de la ficha: los codigoPorcentaje del IVA. Lista cerrada.
 *
 * Importa más de lo que parece porque TRES códigos valen cero y no son lo
 * mismo —0 (0%), 6 (no objeto) y 7 (exento)—: el importe del impuesto sale
 * 0.00 en los tres, así que el SRI autoriza el comprobante aunque el código
 * esté mal y el error solo aparece en una auditoría.
 */

/**
 * @param  array<string, string>  $overrides
 */
function factura_con_impuesto(array $overrides): FacturaData
{
    $payload = payload_factura();
    $payload['infoFactura']['totalConImpuestos']['totalImpuesto'][0] = [
        ...$payload['infoFactura']['totalConImpuestos']['totalImpuesto'][0],
        ...$overrides,
    ];

    return FacturaData::from($payload);
}

it('acepta «:dataset», que está en la Tabla 17', function (string $codigo) {
    expect(factura_con_impuesto(['codigoPorcentaje' => $codigo])->infoFactura->totalConImpuestos[0]->codigoPorcentaje)
        ->toBe($codigo);
})->with(['0', '2', '3', '4', '5', '6', '7', '8', '10']);

it('rechaza un codigoPorcentaje de IVA fuera de la Tabla 17 («:dataset»)', function (string $codigo) {
    expect(fn (): FacturaData => factura_con_impuesto(['codigoPorcentaje' => $codigo]))
        ->toThrow(DatoInvalido::class, 'Tabla 17');
})->with([
    'inexistente' => ['99'],
    'retirado' => ['1'],
    'vacío' => [''],
]);

/*
 * El ICE y el IRBPNR tienen sus propias tablas de tarifas; validar la 17
 * contra ellos los dejaría fuera. Se ignoran a propósito hasta que se
 * soporten.
 */
it('no valida la Tabla 17 cuando el impuesto no es IVA', function () {
    $factura = factura_con_impuesto(['codigo' => '3', 'codigoPorcentaje' => '3072']);

    expect($factura->infoFactura->totalConImpuestos[0]->codigoPorcentaje)->toBe('3072');
});

it('valida también el impuesto de cada línea de detalle', function () {
    $payload = payload_factura();
    $payload['detalles']['detalle'][0]['impuestos']['impuesto']['codigoPorcentaje'] = '99';

    expect(fn (): FacturaData => FacturaData::from($payload))
        ->toThrow(DatoInvalido::class, 'Tabla 17');
});

it('el catálogo y el validador son la misma tabla', function () {
    $publicados = array_column($this->getJson(route('api.v1.catalogos.tarifas-iva'))->json('codigos'), 'codigo');

    expect($publicados)->toBe(TarifasIva::todos());
});

it('publica la Tabla 17 con sus nombres y porcentajes', function () {
    $respuesta = $this->getJson(route('api.v1.catalogos.tarifas-iva'));

    $respuesta->assertSuccessful()
        ->assertJsonPath('tabla', 17)
        ->assertJsonPath('validado', true)
        ->assertJsonPath('codigoImpuesto', '2');

    $codigos = collect($respuesta->json('codigos'))->keyBy('codigo');

    expect($codigos['4']['nombre'])->toBe('IVA 15%')
        // cadena, como viaja <tarifa> en el XML: JSON publicaría 15.0 como 15
        ->and($codigos['4']['porcentaje'])->toBe('15.00')
        // las tres categorías que valen cero, cada una con su nombre
        ->and($codigos['0']['nombre'])->toBe('IVA 0%')
        ->and($codigos['6']['nombre'])->toBe('No objeto de impuesto')
        ->and($codigos['7']['nombre'])->toBe('Exento de IVA')
        // sin porcentaje: no son una tarifa, son una categoría
        ->and($codigos['6']['porcentaje'])->toBeNull()
        ->and($codigos['7']['porcentaje'])->toBeNull();
});

it('aparece en el índice de catálogos', function () {
    $claves = array_column($this->getJson(route('api.v1.catalogos.index'))->json('data'), 'clave');

    expect($claves)->toContain('tarifas-iva');
});

it('se revalida con ETag', function () {
    $etag = $this->getJson(route('api.v1.catalogos.tarifas-iva'))->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson(route('api.v1.catalogos.tarifas-iva'))
        ->assertStatus(304);
});
