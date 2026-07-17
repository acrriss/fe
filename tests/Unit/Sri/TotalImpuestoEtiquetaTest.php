<?php

use App\Sri\Data\TotalImpuestoData;

function impuesto_total(string $codigo, string $codigoPorcentaje): TotalImpuestoData
{
    return new TotalImpuestoData(
        codigo: $codigo,
        codigoPorcentaje: $codigoPorcentaje,
        baseImponible: '100.00',
        valor: '15.00',
    );
}

it('etiqueta las tarifas de IVA con su porcentaje legible: :dataset', function (string $codigoPorcentaje, string $esperada) {
    expect(impuesto_total('2', $codigoPorcentaje)->etiqueta())->toBe($esperada);
})->with([
    'IVA 0%' => ['0', 'IVA 0%'],
    'IVA 12%' => ['2', 'IVA 12%'],
    'IVA 14%' => ['3', 'IVA 14%'],
    'IVA 15%' => ['4', 'IVA 15%'],
    'IVA 5%' => ['5', 'IVA 5%'],
    'no objeto' => ['6', 'No objeto de IVA'],
    'exento' => ['7', 'Exento de IVA'],
    'IVA 8%' => ['8', 'IVA 8%'],
    'IVA 13%' => ['10', 'IVA 13%'],
]);

it('etiqueta ICE e IRBPNR por nombre', function () {
    expect(impuesto_total('3', '3072')->etiqueta())->toBe('ICE')
        ->and(impuesto_total('5', '1')->etiqueta())->toBe('IRBPNR');
});

it('cae al formato crudo ante combinaciones desconocidas', function () {
    expect(impuesto_total('9', '99')->etiqueta())->toBe('Impuesto 9 (99)')
        ->and(impuesto_total('2', '99')->etiqueta())->toBe('Impuesto 2 (99)');
});
