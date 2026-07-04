<?php

use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Enums\TipoEmision;
use App\Sri\Enums\TipoIdentificacion;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;

it('convierte el payload golden de factura en un DTO tipado', function () {
    $factura = FacturaData::from(golden_input('factura'));

    expect($factura::tipo())->toBe(TipoComprobante::Factura)
        ->and($factura->infoTributaria->ambiente)->toBe(Ambiente::Pruebas)
        ->and($factura->infoTributaria->tipoEmision)->toBe(TipoEmision::Normal)
        ->and((string) $factura->infoTributaria->ruc)->toBe('0922596788001')
        ->and((string) $factura->infoTributaria->secuencial)->toBe('000004303')
        ->and($factura->infoTributaria->claveAcceso)->toBeNull() // llega vacía: la genera el servidor
        ->and($factura->infoFactura->fechaEmision->format('d/m/Y'))->toBe('07/12/2022')
        ->and($factura->infoFactura->tipoIdentificacionComprador)->toBe(TipoIdentificacion::ConsumidorFinal)
        ->and($factura->infoFactura->importeTotal)->toBe('11.20')
        ->and($factura->infoFactura->totalConImpuestos)->toHaveCount(1)
        ->and($factura->infoFactura->totalConImpuestos[0]->valor)->toBe('1.20')
        ->and($factura->detalles)->toHaveCount(1)
        ->and($factura->detalles[0]->descripcion)->toBe('Servicio de Mantenimiento')
        ->and($factura->detalles[0]->impuestos)->toHaveCount(1)
        ->and($factura->detalles[0]->impuestos[0]->tarifa)->toBe('12.00');
});

it('convierte el payload golden de nota de crédito en un DTO tipado', function () {
    $nota = NotaCreditoData::from(golden_input('notaCredito'));

    expect($nota::tipo())->toBe(TipoComprobante::NotaCredito)
        ->and($nota->infoNotaCredito->codDocModificado)->toBe(TipoComprobante::Factura)
        ->and($nota->infoNotaCredito->numDocModificado)->toBe('001-002-000000004')
        ->and($nota->infoNotaCredito->valorModificacion)->toBe('783.11')
        ->and($nota->infoNotaCredito->motivo)->toBe('Devolución de productos o servicios')
        ->and($nota->detalles)->toHaveCount(2)
        ->and($nota->detalles[0]->codigoInterno)->toBe('PV-0002')
        ->and($nota->detalles[1]->codigoInterno)->toBe('PV-0003')
        // cada detalle golden trae UN impuesto como objeto (no lista): debe normalizarse
        ->and($nota->detalles[0]->impuestos)->toHaveCount(1)
        ->and($nota->detalles[0]->impuestos[0]->baseImponible)->toBe('435.36')
        ->and($nota->detalles[1]->impuestos[0]->tarifa)->toBe('0');
});

it('convierte el payload golden de retención en un DTO tipado', function () {
    $retencion = ComprobanteRetencionData::from(golden_input('comprobanteRetencion'));

    expect($retencion::tipo())->toBe(TipoComprobante::ComprobanteRetencion)
        ->and($retencion->infoCompRetencion->razonSocialSujetoRetenido)->not->toBeEmpty()
        ->and($retencion->infoCompRetencion->periodoFiscal)->not->toBeEmpty()
        ->and($retencion->impuestos)->toHaveCount(1)
        ->and($retencion->impuestos[0]->codigoRetencion)->toBe('2')
        ->and($retencion->impuestos[0]->porcentajeRetener)->toBe('70')
        ->and($retencion->impuestos[0]->codDocSustento)->toBe(TipoComprobante::Factura)
        ->and($retencion->impuestos[0]->fechaEmisionDocSustento->format('d/m/Y'))->toBe('07/12/2022');
});

it('genera desde el DTO la misma clave de acceso que el legado (golden factura)', function () {
    $factura = FacturaData::from(golden_input('factura'));
    $esperada = trim(file_get_contents(golden_path('factura/claveAcceso.txt')));

    $clave = ClaveAcceso::generar(
        fechaEmision: $factura->infoFactura->fechaEmision,
        tipoComprobante: $factura::tipo(),
        ruc: $factura->infoTributaria->ruc,
        ambiente: $factura->infoTributaria->ambiente,
        establecimiento: $factura->infoTributaria->estab,
        puntoEmision: $factura->infoTributaria->ptoEmi,
        secuencial: $factura->infoTributaria->secuencial,
        codigoNumerico: CodigoNumerico::fromString('22568496'),
        tipoEmision: $factura->infoTributaria->tipoEmision,
    );

    expect((string) $clave)->toBe($esperada);
});

it('ignora el codDoc del payload: el tipo lo define la clase', function () {
    // los ejemplos del legado traían codDoc=01 hasta en la nota de crédito;
    // el DTO no expone codDoc y el enum del tipo manda
    $nota = NotaCreditoData::from(golden_input('notaCredito'));

    expect($nota::tipo()->value)->toBe('04')
        ->and(property_exists($nota->infoTributaria, 'codDoc'))->toBeFalse();
});
