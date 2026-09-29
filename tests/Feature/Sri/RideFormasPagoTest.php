<?php

use App\Models\Comprobante;
use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;

/*
 * Anexo 2: el RIDE de factura, nota de débito y liquidación de compra lleva
 * el cuadro «Forma de pago / Valor», con el código y el nombre de la Tabla
 * 24, a la izquierda de los totales.
 */

/**
 * Texto del cuadro de formas de pago del RIDE, fila por fila (sin cabecera).
 *
 * @return list<string>
 */
function filas_formas_pago_ride(string $vista, ComprobanteData $comprobante): array
{
    $html = view($vista, [
        'registro' => Comprobante::factory()->autorizado()->make(),
        'comprobante' => $comprobante,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    preg_match('/<table class="tabla formas-pago mt">.*?<tbody>(.*?)<\/tbody>/s', $html, $cuerpo);
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $cuerpo[1] ?? '', $filas);

    return array_map(
        fn (string $fila): string => trim((string) preg_replace('/\s+/', ' ', strip_tags($fila))),
        $filas[1],
    );
}

it('imprime la forma de pago de la factura con su nombre de la Tabla 24', function () {
    $filas = filas_formas_pago_ride('ride.factura', FacturaData::from(payload_factura()));

    expect($filas)->toBe(['01 - SIN UTILIZACIÓN DEL SISTEMA FINANCIERO 115.00']);
});

it('imprime una fila por cada forma de pago', function () {
    $factura = FacturaData::from(payload_factura(pagos: [
        ['formaPago' => '19', 'total' => '100.00'],
        ['formaPago' => '01', 'total' => '15.00'],
    ]));

    expect(filas_formas_pago_ride('ride.factura', $factura))->toBe([
        '19 - TARJETA DE CRÉDITO 100.00',
        '01 - SIN UTILIZACIÓN DEL SISTEMA FINANCIERO 15.00',
    ]);
});

it('imprime el plazo cuando el pago lo trae', function () {
    $factura = FacturaData::from(payload_factura(pagos: [
        ['formaPago' => '20', 'total' => '115.00', 'plazo' => '30', 'unidadTiempo' => 'dias'],
    ]));

    expect(filas_formas_pago_ride('ride.factura', $factura))->toBe([
        '20 - OTROS CON UTILIZACIÓN DEL SISTEMA FINANCIERO Plazo: 30 dias 115.00',
    ]);
});

it('imprime las formas de pago también en la :dataset', function (string $vista, string $tipo) {
    $filas = filas_formas_pago_ride($vista, data_class_de($tipo)::from(payload_comprobante($tipo)));

    expect($filas)->toBe(['01 - SIN UTILIZACIÓN DEL SISTEMA FINANCIERO 57.50']);
})->with([
    'nota de débito' => ['ride.nota-debito', 'notaDebito'],
    'liquidación de compra' => ['ride.liquidacion', 'liquidacionCompra'],
]);
