<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaCredito\NotaCreditoData;
use App\Sri\Data\Retencion\ComprobanteRetencionData;
use App\Sri\Enums\Ambiente;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;

it('reproduce byte a byte el XML golden de la factura del legado', function () {
    $factura = FacturaData::from(golden_input('factura'));
    $factura->infoTributaria->claveAcceso = ClaveAcceso::fromString(
        trim(file_get_contents(golden_path('factura/claveAcceso.txt'))),
    );

    expect(ConstruirXml::render($factura))
        ->toBe(file_get_contents(golden_path('factura/comprobante.xml')));
});

/*
 * Para notaCredito y comprobanteRetencion hay una desviación DELIBERADA
 * respecto al golden: el legado copiaba el codDoc del payload (que venía
 * mal, siempre 01) y el nuevo dominio lo deriva del tipo (04 / 07). El test
 * parchea el golden con el codDoc correcto y la clave recalculada; todo lo
 * demás debe ser idéntico.
 */
it('reproduce el XML golden de :dataset salvo la corrección de codDoc', function (string $rootElement, string $dataClass, string $codDocCorrecto) {
    $comprobante = $dataClass::from(golden_input($rootElement));

    $claveCorrecta = ClaveAcceso::generar(
        fechaEmision: $comprobante->fechaEmision(),
        tipoComprobante: $comprobante::tipo(),
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '001',
        puntoEmision: '001',
        secuencial: Secuencial::fromString('000004303'),
        codigoNumerico: CodigoNumerico::fromString('22568496'),
    );
    $comprobante->infoTributaria->claveAcceso = $claveCorrecta;

    $claveGolden = trim(file_get_contents(golden_path("$rootElement/claveAcceso.txt")));
    $goldenCorregido = str_replace(
        ['<codDoc>01</codDoc>', $claveGolden],
        ["<codDoc>{$codDocCorrecto}</codDoc>", (string) $claveCorrecta],
        file_get_contents(golden_path("$rootElement/comprobante.xml")),
    );

    expect(ConstruirXml::render($comprobante))->toBe($goldenCorregido);
})->with([
    'notaCredito' => ['notaCredito', NotaCreditoData::class, '04'],
    'comprobanteRetencion' => ['comprobanteRetencion', ComprobanteRetencionData::class, '07'],
]);

it('exige la clave de acceso antes de construir el XML', function () {
    $factura = FacturaData::from(golden_input('factura'));

    ConstruirXml::render($factura);
})->throws(LogicException::class);

it('usa fechaEmision() polimórfica según el tipo', function () {
    expect(FacturaData::from(golden_input('factura'))->fechaEmision()->format('d/m/Y'))->toBe('07/12/2022')
        ->and(NotaCreditoData::from(golden_input('notaCredito'))->fechaEmision()->format('d/m/Y'))->toBe('07/12/2022')
        ->and(ComprobanteRetencionData::from(golden_input('comprobanteRetencion'))->fechaEmision()->format('d/m/Y'))->toBe('07/12/2022');
});
