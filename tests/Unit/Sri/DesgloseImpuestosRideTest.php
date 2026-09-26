<?php

use App\Sri\Data\TotalImpuestoData;
use App\Sri\Ride\DesgloseImpuestosRide;

function total_impuesto(string $codigoPorcentaje, string $baseImponible, string $valor): TotalImpuestoData
{
    return new TotalImpuestoData(
        codigo: '2',
        codigoPorcentaje: $codigoPorcentaje,
        baseImponible: $baseImponible,
        valor: $valor,
    );
}

it('da la base imponible de cada tarifa en el orden del XML', function () {
    $desglose = new DesgloseImpuestosRide([
        total_impuesto('4', '1.76', '0.26'),
        total_impuesto('0', '3.50', '0.00'),
    ]);

    expect($desglose->subtotalesPorTarifa())->toBe([
        ['etiqueta' => 'Subtotal IVA 15%', 'valor' => '1.76'],
        ['etiqueta' => 'Subtotal IVA 0%', 'valor' => '3.50'],
    ]);
});

it('muestra el subtotal de una tarifa aunque su impuesto valga cero', function (string $codigoPorcentaje, string $etiqueta) {
    $desglose = new DesgloseImpuestosRide([total_impuesto($codigoPorcentaje, '10.00', '0.00')]);

    expect($desglose->subtotalesPorTarifa())->toBe([['etiqueta' => $etiqueta, 'valor' => '10.00']]);
})->with([
    'IVA 0%' => ['0', 'Subtotal IVA 0%'],
    'no objeto' => ['6', 'Subtotal No objeto de impuesto'],
    'exento' => ['7', 'Subtotal Exento de IVA'],
]);

it('da el importe de cada impuesto que suma algo', function () {
    $desglose = new DesgloseImpuestosRide([
        total_impuesto('4', '100.00', '15.00'),
        total_impuesto('5', '100.00', '5.00'),
    ]);

    expect($desglose->impuestosConValor())->toBe([
        ['etiqueta' => 'IVA 15%', 'valor' => '15.00'],
        ['etiqueta' => 'IVA 5%', 'valor' => '5.00'],
    ]);
});

it('omite los impuestos que valen cero, escritos como sea: «:dataset»', function (string $valor) {
    $desglose = new DesgloseImpuestosRide([
        total_impuesto('4', '1.76', '0.26'),
        total_impuesto('0', '3.50', $valor),
    ]);

    expect($desglose->impuestosConValor())->toBe([['etiqueta' => 'IVA 15%', 'valor' => '0.26']]);
})->with(['0.00', '0', '0.000000']);

it('no redondea: un céntimo de impuesto se imprime', function () {
    $desglose = new DesgloseImpuestosRide([total_impuesto('4', '0.07', '0.01')]);

    expect($desglose->impuestosConValor())->toBe([['etiqueta' => 'IVA 15%', 'valor' => '0.01']]);
});

it('devuelve los importes tal cual viajan en el XML', function () {
    $desglose = new DesgloseImpuestosRide([total_impuesto('4', '100.10', '15.00')]);

    expect($desglose->subtotalesPorTarifa()[0]['valor'])->toBe('100.10')
        ->and($desglose->impuestosConValor()[0]['valor'])->toBe('15.00');
});
