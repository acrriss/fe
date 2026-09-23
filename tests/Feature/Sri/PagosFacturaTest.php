<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\Factura\FacturaData;
use App\Sri\Data\NotaDebito\NotaDebitoData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Support\ComprobanteXmlParser;

/*
 * Ficha 2.34, formato de factura: el bloque <pagos><pago> es *Obligatorio*,
 * con formaPago (Tabla 24) y total; plazo y unidadTiempo cuando corresponda.
 * Va después de <moneda> —y de <placa>, que el Anexo 25 §2 ubica «entre los
 * tags moneda y formas de pago»— y antes de <valorRetIva>.
 *
 * Aquí es opcional a propósito: la ficha lo exige, pero volverlo obligatorio
 * de golpe dejaría sin emitir a todo integrador que hoy no lo manda.
 */

/**
 * @param  array<int, array<string, string>>|null  $pagos
 */
function factura_con_pagos(?array $pagos, ?string $placa = null): FacturaData
{
    $factura = FacturaData::from(payload_factura(placa: $placa, pagos: $pagos));
    $factura->infoTributaria->claveAcceso = clave_acceso_de_prueba('factura');

    return $factura;
}

/**
 * @param  array<int, array<string, string>>|null  $pagos
 */
function xml_con_pagos(?array $pagos, ?string $placa = null): string
{
    return ConstruirXml::render(factura_con_pagos($pagos, $placa));
}

it('escribe el bloque de pagos con su forma de pago y su total', function () {
    $xml = xml_con_pagos([['formaPago' => '01', 'total' => '115.00']]);

    expect($xml)->toContain('<pagos>')
        ->toContain('<formaPago>01</formaPago>')
        ->toContain('<total>115.00</total>');
});

it('acepta varios pagos y conserva su orden', function () {
    $dom = simplexml_load_string(xml_con_pagos([
        ['formaPago' => '01', 'total' => '15.00'],
        ['formaPago' => '19', 'total' => '100.00'],
    ]));

    $pagos = iterator_to_array($dom->infoFactura->pagos->pago, false);

    expect($pagos)->toHaveCount(2)
        ->and((string) $pagos[0]->formaPago)->toBe('01')
        ->and((string) $pagos[1]->formaPago)->toBe('19')
        ->and((string) $pagos[1]->total)->toBe('100.00');
});

/*
 * El caso que más integradores envían: un solo pago como objeto y no como
 * lista. Payload::lista() lo normaliza, igual que en los demás bloques.
 */
it('normaliza un pago único enviado como objeto', function () {
    $payload = payload_factura();
    $payload['infoFactura']['pagos'] = ['pago' => ['formaPago' => '20', 'total' => '115.00']];

    $factura = FacturaData::from($payload);

    expect($factura->infoFactura->pagos)->toHaveCount(1)
        ->and($factura->infoFactura->pagos[0]->formaPago)->toBe('20');
});

it('escribe plazo y unidadTiempo solo cuando vienen', function () {
    $conPlazo = xml_con_pagos([
        ['formaPago' => '01', 'total' => '115.00', 'plazo' => '30', 'unidadTiempo' => 'dias'],
    ]);
    $sinPlazo = xml_con_pagos([['formaPago' => '01', 'total' => '115.00']]);

    expect($conPlazo)->toContain('<plazo>30</plazo>')->toContain('<unidadTiempo>dias</unidadTiempo>')
        ->and($sinPlazo)->not->toContain('<plazo>')->not->toContain('<unidadTiempo>');
});

/*
 * La ficha lo marca *Obligatorio* en factura, así que el servicio lo exige:
 * emitir sin formas de pago produciría un comprobante que no cumple el
 * formato. Se aceptó opcional solo mientras el integrador se ponía al día.
 */
it('rechaza una factura sin formas de pago («:dataset»)', function (mixed $pagos) {
    $payload = payload_factura();

    if ($pagos === null) {
        unset($payload['infoFactura']['pagos']);
    } else {
        $payload['infoFactura']['pagos'] = $pagos;
    }

    // DatoInvalido y no el error genérico de laravel-data: el mensaje nombra
    // el campo y dice qué falta, y el Form Request lo convierte en 422
    expect(fn (): FacturaData => FacturaData::from($payload))
        ->toThrow(DatoInvalido::class, 'al menos una forma de pago');
})->with([
    'ausente' => [null],
    'bloque vacío' => [['pago' => []]],
    'bloque nulo' => [['pago' => null]],
]);

/*
 * El orden de los tags es parte del contrato: el XSD del SRI los valida en
 * secuencia.
 */
it('coloca los pagos tras la moneda y la placa, al cerrar infoFactura', function () {
    $dom = simplexml_load_string(xml_con_pagos(
        [['formaPago' => '01', 'total' => '115.00']],
        placa: 'ABC1234',
    ));

    $tags = array_map(
        fn (SimpleXMLElement $hijo): string => $hijo->getName(),
        iterator_to_array($dom->infoFactura->children(), false),
    );

    expect(array_slice($tags, -3))->toBe(['moneda', 'placa', 'pagos']);
});

it('sobrevive al roundtrip parse → render', function () {
    $xml = xml_con_pagos([
        ['formaPago' => '19', 'total' => '115.00', 'plazo' => '30', 'unidadTiempo' => 'dias'],
    ]);

    $releido = ConstruirXml::render(app(ComprobanteXmlParser::class)->parse($xml));

    expect($releido)->toBe($xml);
});

/*
 * El guardia de §14: una clave desconocida dentro de <pago> es 422, no un
 * descarte silencioso.
 */
it('rechaza una clave desconocida dentro de un pago', function () {
    $llamada = fn (): FacturaData => factura_con_pagos([
        ['formaPago' => '01', 'total' => '115.00', 'formaDePago' => '02'],
    ]);

    expect($llamada)->toThrow(DatoInvalido::class);
});

/*
 * Tabla 24: lista cerrada. Los códigos 02-14 existieron y fueron retirados,
 * así que enviarlos es un error que el SRI solo reportaría al autorizar.
 */
it('rechaza una forma de pago que no está en la Tabla 24', function (string $codigo) {
    $llamada = fn (): FacturaData => factura_con_pagos([
        ['formaPago' => $codigo, 'total' => '115.00'],
    ]);

    expect($llamada)->toThrow(DatoInvalido::class);
})->with([
    'retirada' => ['02'],
    'inexistente' => ['99'],
    'sin cero a la izquierda' => ['1'],
    'vacía' => [''],
]);

it('acepta «:dataset», que sí está en la Tabla 24', function (string $codigo) {
    expect(xml_con_pagos([['formaPago' => $codigo, 'total' => '115.00']]))
        ->toContain("<formaPago>{$codigo}</formaPago>");
})->with(['01', '15', '16', '17', '18', '19', '20', '21']);

/*
 * La misma tabla rige en nota de débito y liquidación de compra, que usan
 * el mismo PagoData.
 */
it('valida la forma de pago también en la nota de débito', function () {
    $payload = payload_comprobante('notaDebito');
    $payload['infoNotaDebito']['pagos'] = ['pago' => ['formaPago' => '02', 'total' => '10.00']];

    expect(fn () => NotaDebitoData::from($payload))
        ->toThrow(DatoInvalido::class);
});
