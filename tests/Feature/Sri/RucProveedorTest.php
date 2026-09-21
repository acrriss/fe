<?php

use App\Models\Comprobante;
use App\Sri\Actions\AgregarRucProveedor;
use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\CampoAdicionalData;
use App\Sri\Data\ComprobanteData;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\Support\ComprobanteXmlParser;

/**
 * Resolución NAC-DGERCGC26-00000027, Art. 5: el comprobante lleva el RUC
 * del proveedor del sistema en la información adicional.
 */
const RUC_PROVEEDOR = '0993205451001';

function factura_de_prueba(): FacturaData
{
    $factura = FacturaData::from(payload_factura());
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');

    return $factura;
}

function emitir_etapa(FacturaData $factura): FacturaData
{
    $emision = new EmisionEnCurso($factura, certificado_de_prueba());
    (new AgregarRucProveedor)($emision, fn (EmisionEnCurso $e): EmisionEnCurso => $e);

    return $factura;
}

it('emite el campo con el nombre literal que fija la ficha técnica', function () {
    config(['sri.ruc_proveedor' => RUC_PROVEEDOR]);

    $xml = ConstruirXml::render(emitir_etapa(factura_de_prueba()));

    expect($xml)->toContain('<campoAdicional nombre="RUC Proveedor">'.RUC_PROVEEDOR.'</campoAdicional>');
});

it('coloca infoAdicional como último elemento del comprobante', function () {
    config(['sri.ruc_proveedor' => RUC_PROVEEDOR]);

    $xml = ConstruirXml::render(emitir_etapa(factura_de_prueba()));
    $documento = simplexml_load_string($xml);
    $hijos = array_map(fn ($hijo): string => $hijo->getName(), iterator_to_array($documento->children()));

    expect(end($hijos))->toBe('infoAdicional');
});

it('sin RUC configurado no añade nada y el XML no lleva infoAdicional', function () {
    config(['sri.ruc_proveedor' => '']);

    $factura = emitir_etapa(factura_de_prueba());

    expect($factura->infoAdicional)->toBe([])
        ->and(ConstruirXml::render($factura))->not->toContain('<infoAdicional')
        ->and(ConstruirXml::render($factura))->toBe(xml_de_prueba('factura'));
});

/*
 * El campo es una afirmación del servicio sobre quién provee el sistema, no
 * un dato del emisor. Sin esta guarda, cualquier integrador desactivaba la
 * declaración con una línea de JSON, y nadie lo detectaba: para el SRI
 * `infoAdicional` es texto libre y no valida su contenido.
 */
it('rechaza el RUC de proveedor que venga en el payload', function () {
    $factura = factura_de_prueba();
    $factura->infoAdicional = [new CampoAdicionalData('RUC Proveedor', '9999999999999')];

    expect(fn () => AgregarRucProveedor::rechazarSiVieneEnElPayload($factura))
        ->toThrow(DatoInvalido::class, 'lo fija el servicio');
});

it('deja pasar los demás campos adicionales del emisor', function () {
    $factura = factura_de_prueba();
    $factura->infoAdicional = [new CampoAdicionalData('Email', 'cliente@ejemplo.test')];

    AgregarRucProveedor::rechazarSiVieneEnElPayload($factura);
})->throwsNoExceptions();

it('conserva los campos adicionales del emisor y añade el suyo al final', function () {
    config(['sri.ruc_proveedor' => RUC_PROVEEDOR]);

    $factura = factura_de_prueba();
    $factura->infoAdicional = [new CampoAdicionalData('Email', 'cliente@ejemplo.test')];
    emitir_etapa($factura);

    expect($factura->infoAdicional)->toHaveCount(2)
        ->and($factura->infoAdicional[1]->nombre)->toBe('RUC Proveedor');
});

it('falla claramente si no cabe otro campo adicional', function () {
    config(['sri.ruc_proveedor' => RUC_PROVEEDOR]);

    $factura = factura_de_prueba();
    $factura->infoAdicional = array_map(
        fn (int $i): CampoAdicionalData => new CampoAdicionalData("Campo {$i}", (string) $i),
        range(1, ComprobanteData::MAXIMO_CAMPOS_ADICIONALES),
    );

    expect(fn () => emitir_etapa($factura))
        ->toThrow(DatoInvalido::class, 'campos adicionales que admite el esquema del SRI');
});

/*
 * json_encode() sobre SimpleXML descarta los atributos de un elemento que
 * además tiene texto: sin extraerlo a mano, el nombre del campo se perdía
 * y el RIDE mostraba filas sin etiqueta.
 */
it('recupera nombre y valor al leer el XML de vuelta', function () {
    config(['sri.ruc_proveedor' => RUC_PROVEEDOR]);

    $factura = factura_de_prueba();
    $factura->infoAdicional = [new CampoAdicionalData('Email', 'cliente@ejemplo.test')];
    $xml = ConstruirXml::render(emitir_etapa($factura));

    $releido = (new ComprobanteXmlParser)->parse($xml);

    expect($releido->infoAdicional)->toHaveCount(2)
        ->and($releido->infoAdicional[0]->nombre)->toBe('Email')
        ->and($releido->infoAdicional[0]->valor)->toBe('cliente@ejemplo.test')
        ->and($releido->infoAdicional[1]->nombre)->toBe('RUC Proveedor')
        ->and($releido->infoAdicional[1]->valor)->toBe(RUC_PROVEEDOR);
});

/*
 * El RIDE también debe mostrarlo (bloque de información adicional, junto a
 * Teléfono/Email). Se comprueba sobre el HTML de la plantilla y no sobre el
 * PDF: dompdf comprime los streams y el texto no es buscable en la salida.
 */
it('muestra la información adicional en el RIDE', function () {
    config(['sri.ruc_proveedor' => RUC_PROVEEDOR]);

    $factura = factura_de_prueba();
    $factura->infoAdicional = [new CampoAdicionalData('Email', 'cliente@ejemplo.test')];
    emitir_etapa($factura);

    $registro = Comprobante::factory()->autorizado()->make([
        'clave_acceso' => (string) $factura->infoTributaria->claveAcceso,
    ]);

    $html = view('ride.factura', [
        'registro' => $registro,
        'comprobante' => $factura,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    expect($html)->toContain('INFORMACIÓN ADICIONAL')
        ->toContain('RUC Proveedor')
        ->toContain(RUC_PROVEEDOR)
        ->toContain('cliente@ejemplo.test');
});

it('no dibuja el bloque de información adicional si no hay campos', function () {
    config(['sri.ruc_proveedor' => '']);

    $factura = emitir_etapa(factura_de_prueba());
    $registro = Comprobante::factory()->autorizado()->make([
        'clave_acceso' => (string) $factura->infoTributaria->claveAcceso,
    ]);

    $html = view('ride.factura', [
        'registro' => $registro,
        'comprobante' => $factura,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    expect($html)->not->toContain('INFORMACIÓN ADICIONAL');
});
