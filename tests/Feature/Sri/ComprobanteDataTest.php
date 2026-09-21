<?php

use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Enums\TipoEmision;
use App\Sri\Enums\TipoIdentificacion;

it('convierte el payload de factura en un DTO tipado', function () {
    $factura = FacturaData::from(payload_factura());

    expect($factura::tipo())->toBe(TipoComprobante::Factura)
        ->and($factura->infoTributaria->ambiente)->toBe(Ambiente::Pruebas)
        ->and($factura->infoTributaria->tipoEmision)->toBe(TipoEmision::Normal)
        ->and((string) $factura->infoTributaria->ruc)->toBe(RUC_PRUEBA)
        ->and((string) $factura->infoTributaria->secuencial)->toBe('000000001')
        ->and($factura->infoTributaria->claveAcceso)->toBeNull() // no viene en el payload: la genera el servidor
        ->and($factura->infoFactura->fechaEmision->format('d/m/Y'))->toBe('10/07/2026')
        ->and($factura->infoFactura->tipoIdentificacionComprador)->toBe(TipoIdentificacion::ConsumidorFinal)
        ->and($factura->infoFactura->importeTotal)->toBe('115.00')
        ->and($factura->infoFactura->totalConImpuestos)->toHaveCount(1)
        ->and($factura->infoFactura->totalConImpuestos[0]->valor)->toBe('15.00')
        ->and($factura->detalles)->toHaveCount(1)
        ->and($factura->detalles[0]->descripcion)->toBe('Servicio de consultoría')
        // un impuesto como objeto (no lista) en el payload: debe normalizarse
        ->and($factura->detalles[0]->impuestos)->toHaveCount(1)
        ->and($factura->detalles[0]->impuestos[0]->tarifa)->toBe('15.00');
});

it('convierte el payload de nota de crédito en un DTO tipado', function () {
    $nota = NotaCreditoData::from(payload_nota_credito());

    expect($nota::tipo())->toBe(TipoComprobante::NotaCredito)
        ->and($nota->infoNotaCredito->codDocModificado)->toBe(TipoComprobante::Factura)
        ->and($nota->infoNotaCredito->numDocModificado)->toBe('001-001-000000001')
        ->and($nota->infoNotaCredito->valorModificacion)->toBe('87.50')
        ->and($nota->infoNotaCredito->motivo)->toBe('Devolución de productos o servicios')
        ->and($nota->infoNotaCredito->totalConImpuestos)->toHaveCount(2)
        ->and($nota->detalles)->toHaveCount(2)
        ->and($nota->detalles[0]->codigoInterno)->toBe('PV-0001')
        ->and($nota->detalles[1]->codigoInterno)->toBe('PV-0002')
        ->and($nota->detalles[0]->impuestos)->toHaveCount(1)
        ->and($nota->detalles[0]->impuestos[0]->baseImponible)->toBe('50.00')
        ->and($nota->detalles[1]->impuestos[0]->tarifa)->toBe('0.00');
});

it('convierte el payload de retención en un DTO tipado', function () {
    $retencion = ComprobanteRetencionData::from(payload_retencion());

    expect($retencion::tipo())->toBe(TipoComprobante::ComprobanteRetencion)
        ->and($retencion->infoCompRetencion->razonSocialSujetoRetenido)->toBe('PROVEEDOR S.A.')
        ->and($retencion->infoCompRetencion->periodoFiscal)->toBe('07/2026')
        ->and($retencion->impuestos)->toHaveCount(1)
        ->and($retencion->impuestos[0]->codigoRetencion)->toBe('2')
        ->and($retencion->impuestos[0]->porcentajeRetener)->toBe('70')
        ->and($retencion->impuestos[0]->codDocSustento)->toBe(TipoComprobante::Factura)
        ->and($retencion->impuestos[0]->fechaEmisionDocSustento->format('d/m/Y'))->toBe('01/07/2026');
});

it('los campos del DTO alimentan la clave de acceso con la fecha del tipo', function () {
    $clave = (string) clave_acceso_de_prueba('notaCredito');

    // fecha de infoNotaCredito, codDoc 04 derivado del tipo, RUC y serie de infoTributaria
    expect($clave)->toStartWith('10072026'.'04'.RUC_PRUEBA.'1'.'001001'.'000000002');
});

it('ignora codDoc y claveAcceso del payload: los deriva el dominio', function () {
    $payload = payload_nota_credito();
    $payload['infoTributaria']['codDoc'] = '01'; // erróneo a propósito
    $payload['infoTributaria']['claveAcceso'] = '';

    $nota = NotaCreditoData::from($payload);

    expect($nota::tipo()->value)->toBe('04')
        ->and(property_exists($nota->infoTributaria, 'codDoc'))->toBeFalse()
        ->and($nota->infoTributaria->claveAcceso)->toBeNull();
});
