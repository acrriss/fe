<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Support\ComprobanteXmlParser;

/*
 * Guardia contra el descarte silencioso: laravel-data ignora las claves que
 * el DTO no declara, así que hasta ahora un campo mal escrito —o uno del
 * SRI que no soportábamos— desaparecía sin avisar y el comprobante se
 * autorizaba incompleto. Este es el problema de fondo que destapó la
 * revisión de los Anexos 21-26 (§14).
 */

it('rechaza una clave desconocida en :dataset', function (Closure $sabotear, string $bloque, string $clave) {
    $payload = payload_factura();
    $sabotear($payload);

    expect(fn () => FacturaData::from($payload))
        ->toThrow(DatoInvalido::class, "El bloque «{$bloque}» no reconoce la clave «{$clave}»");
})->with([
    'infoTributaria' => [fn (array &$p) => $p['infoTributaria']['razonSocia'] = 'X', 'infoTributaria', 'razonSocia'],
    'infoFactura' => [fn (array &$p) => $p['infoFactura']['totalDescuentos'] = '0.00', 'infoFactura', 'totalDescuentos'],
    'detalle' => [fn (array &$p) => $p['detalles']['detalle'][0]['codigoAuxiliarr'] = 'F010101', 'detalle', 'codigoAuxiliarr'],
    'impuesto del detalle' => [fn (array &$p) => $p['detalles']['detalle'][0]['impuestos']['impuesto']['tarifaa'] = '15.00', 'impuesto', 'tarifaa'],
    'totalImpuesto' => [fn (array &$p) => $p['infoFactura']['totalConImpuestos']['totalImpuesto'][0]['descuentoAdicional'] = '0.00', 'totalImpuesto', 'descuentoAdicional'],
    'raíz del comprobante' => [fn (array &$p) => $p['infoAdicionall'] = [], 'factura', 'infoAdicionall'],
]);

it('nombra todas las claves desconocidas y las admitidas, para distinguir errata de campo no soportado', function () {
    $payload = payload_factura();
    $payload['infoFactura']['placaa'] = 'ABC1234';
    $payload['infoFactura']['guiaRemision'] = '001-001-000000001';

    expect(fn () => FacturaData::from($payload))->toThrow(
        DatoInvalido::class,
        'no reconoce las claves «placaa», «guiaRemision»',
    );

    try {
        FacturaData::from($payload);
    } catch (DatoInvalido $excepcion) {
        expect($excepcion->getMessage())->toContain('Se admiten: ')
            ->toContain('placa')
            ->toContain('importeTotal');
    }
});

/*
 * El codDoc viaja en el formato del SRI y muchos integradores lo envían; la
 * ficha manda derivarlo del tipo, así que se acepta y se descarta. Es la
 * única excepción, y es explícita.
 */
it('sigue aceptando el codDoc del payload y lo deriva del tipo', function () {
    $payload = payload_factura();
    $payload['infoTributaria']['codDoc'] = '99'; // valor absurdo a propósito

    $factura = FacturaData::from($payload);
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');

    expect(ConstruirXml::render($factura))->toContain('<codDoc>01</codDoc>');
});

it('los seis payloads de prueba pasan la guardia', function (string $tipo) {
    expect(comprobante_de_prueba($tipo))->toBeInstanceOf(ComprobanteData::class);
})->with(['factura', 'notaCredito', 'notaDebito', 'comprobanteRetencion', 'guiaRemision', 'liquidacionCompra']);

/*
 * El RIDE se genera releyendo el XML almacenado: si la guardia rechazara
 * algún tag que nosotros mismos emitimos, rompería la descarga de RIDE de
 * comprobantes ya emitidos.
 */
it('el XML que genera el sistema se puede releer con la guardia activa', function (string $tipo) {
    $releido = (new ComprobanteXmlParser)->parse(xml_de_prueba($tipo));

    expect(ConstruirXml::render($releido))->toBe(xml_de_prueba($tipo));
})->with(['factura', 'notaCredito', 'notaDebito', 'comprobanteRetencion', 'guiaRemision', 'liquidacionCompra']);
