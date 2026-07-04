<?php

use App\Sri\Enums\TipoComprobante;

it('mapea cada tipo a su codDoc oficial (tabla 3 del SRI)', function () {
    expect(TipoComprobante::Factura->value)->toBe('01')
        ->and(TipoComprobante::NotaCredito->value)->toBe('04')
        ->and(TipoComprobante::NotaDebito->value)->toBe('05')
        ->and(TipoComprobante::GuiaRemision->value)->toBe('06')
        ->and(TipoComprobante::ComprobanteRetencion->value)->toBe('07');
});

it('resuelve el tipo desde el elemento raíz del payload', function () {
    expect(TipoComprobante::fromRootElement('factura'))->toBe(TipoComprobante::Factura)
        ->and(TipoComprobante::fromRootElement('notaCredito'))->toBe(TipoComprobante::NotaCredito)
        ->and(TipoComprobante::fromRootElement('comprobanteRetencion'))->toBe(TipoComprobante::ComprobanteRetencion);
});

it('rechaza elementos raíz desconocidos', function () {
    TipoComprobante::fromRootElement('recibo');
})->throws(ValueError::class);

it('usa la versión de esquema del legado (retención 1.0.0, resto 1.1.0)', function () {
    expect(TipoComprobante::ComprobanteRetencion->versionEsquema())->toBe('1.0.0')
        ->and(TipoComprobante::Factura->versionEsquema())->toBe('1.1.0')
        ->and(TipoComprobante::NotaCredito->versionEsquema())->toBe('1.1.0');
});
