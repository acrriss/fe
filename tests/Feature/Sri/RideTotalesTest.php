<?php

use App\Models\Comprobante;
use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;

/*
 * Tabla de totales del RIDE en el orden del Anexo 2: subtotal de cada
 * tarifa, subtotal sin impuestos, descuento (solo factura), importe de cada
 * impuesto y total. La propina no sale en el ejemplo del Anexo 2; va justo
 * antes del total (§9.19). Es el mismo orden que el ticket del POS, para
 * que el cliente lea los mismos números en el papel y en el PDF.
 */

/**
 * Texto de la tabla de totales del RIDE, fila por fila.
 *
 * @return list<string>
 */
function filas_totales_ride(string $vista, ComprobanteData $comprobante): array
{
    $html = view($vista, [
        'registro' => Comprobante::factory()->autorizado()->make(),
        'comprobante' => $comprobante,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    preg_match('/<table class="totales mt">(.*?)<\/table>/s', $html, $tabla);
    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $tabla[1] ?? '', $filas);

    return array_map(
        fn (string $fila): string => trim((string) preg_replace('/\s+/', ' ', strip_tags($fila))),
        $filas[1],
    );
}

it('ordena los totales de la factura como el Anexo 2', function () {
    $filas = filas_totales_ride('ride.factura', FacturaData::from(payload_factura_dos_tarifas()));

    expect($filas)->toBe([
        'Subtotal IVA 15% 1.76',
        'Subtotal IVA 0% 3.50',
        'Subtotal sin impuestos 5.26',
        'Descuento 0.00',
        'IVA 15% 0.26',
        'Propina 0.00',
        'VALOR TOTAL (DOLAR) 5.52',
    ]);
});

it('pone la propina después de los impuestos, justo antes del total', function () {
    $filas = filas_totales_ride('ride.factura', FacturaData::from(payload_factura_dos_tarifas(propina: '0.50')));

    expect(array_slice($filas, -3))->toBe([
        'IVA 15% 0.26',
        'Propina 0.50',
        'VALOR TOTAL (DOLAR) 6.02',
    ]);
});

it('no dibuja la propina si la factura no la trae', function () {
    $payload = payload_factura_dos_tarifas();
    unset($payload['infoFactura']['propina']);

    $filas = filas_totales_ride('ride.factura', FacturaData::from($payload));

    expect(implode("\n", $filas))->not->toContain('Propina');
});

it('ordena los totales de la nota de crédito como el Anexo 2, sin descuento', function () {
    expect(filas_totales_ride('ride.nota-credito', comprobante_de_prueba('notaCredito')))->toBe([
        'Subtotal IVA 15% 50.00',
        'Subtotal IVA 0% 30.00',
        'Subtotal sin impuestos 80.00',
        'IVA 15% 7.50',
        'VALOR MODIFICACIÓN (DOLAR) 87.50',
    ]);
});

it('ordena los totales de la liquidación de compra como el Anexo 2, sin descuento', function () {
    expect(filas_totales_ride('ride.liquidacion', comprobante_de_prueba('liquidacionCompra')))->toBe([
        'Subtotal IVA 15% 50.00',
        'Subtotal sin impuestos 50.00',
        'IVA 15% 7.50',
        'VALOR TOTAL (DOLAR) 57.50',
    ]);
});
