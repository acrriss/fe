<?php

use App\Models\Comprobante;
use App\Sri\Actions\AgregarLeyendasEmisor;
use App\Sri\Actions\ConstruirXml;
use App\Sri\Data\ComprobanteData;
use App\Sri\Exceptions\DatoInvalido;
use App\Sri\Pipeline\EmisionEnCurso;
use App\Sri\Support\ComprobanteXmlParser;
use App\Sri\ValueObjects\LeyendasEmisor;

/*
 * Ficha 2.34, Anexo 21: el comprobante de un agente de retención lleva el
 * número de resolución en <agenteRetencion>, dentro de infoTributaria y
 * tras dirMatriz. El contribuyente especial (Tabla 11, fila 8) va en el
 * bloque info* de cada tipo. Ambas son atributos del emisor: las inyecta
 * el pipeline desde su configuración, nunca el payload.
 */
const LEYENDAS_PRUEBA = ['agenteRetencion' => '6498', 'contribuyenteEspecial' => '5368'];

function con_leyendas(string $tipo): ComprobanteData
{
    $comprobante = comprobante_de_prueba($tipo);
    $emision = new EmisionEnCurso($comprobante, certificado_de_prueba(), leyendas: new LeyendasEmisor(...LEYENDAS_PRUEBA));
    (new AgregarLeyendasEmisor)($emision, fn (EmisionEnCurso $e): EmisionEnCurso => $e);

    return $comprobante;
}

/**
 * @return list<string>
 */
function nombres_de_hijos(SimpleXMLElement $elemento): array
{
    return array_map(fn (SimpleXMLElement $hijo): string => $hijo->getName(), iterator_to_array($elemento->children(), false));
}

dataset('tipos', ['factura', 'notaCredito', 'notaDebito', 'comprobanteRetencion', 'guiaRemision', 'liquidacionCompra']);

it('emite agenteRetencion como último tag de infoTributaria, tras dirMatriz, en :dataset', function (string $tipo) {
    $dom = simplexml_load_string(ConstruirXml::render(con_leyendas($tipo)));
    $tags = nombres_de_hijos($dom->infoTributaria);

    expect(array_slice($tags, -2))->toBe(['dirMatriz', 'agenteRetencion'])
        ->and((string) $dom->infoTributaria->agenteRetencion)->toBe('6498');
})->with('tipos');

/*
 * En todos los formatos XML de la ficha <contribuyenteEspecial> va pegado a
 * <obligadoContabilidad>: antes en cinco tipos y después en la guía de
 * remisión (Anexo 1, formato guiaRemision 1.0.0). Los payloads de prueba
 * de nota de débito, guía y liquidación omiten obligadoContabilidad (es
 * opcional), así que ahí el vecino es el tag siguiente del formato.
 */
it('coloca contribuyenteEspecial junto a obligadoContabilidad en :dataset', function (string $tipo, string $bloque, array $vecindad) {
    $dom = simplexml_load_string(ConstruirXml::render(con_leyendas($tipo)));
    $tags = nombres_de_hijos($dom->{$bloque});
    $posicion = array_search('contribuyenteEspecial', $tags, true);

    expect($posicion)->not->toBeFalse()
        ->and(array_slice($tags, $posicion - 1, 3))->toBe($vecindad)
        ->and((string) $dom->{$bloque}->contribuyenteEspecial)->toBe('5368');
})->with([
    'factura' => ['factura', 'infoFactura', ['dirEstablecimiento', 'contribuyenteEspecial', 'obligadoContabilidad']],
    'notaCredito' => ['notaCredito', 'infoNotaCredito', ['identificacionComprador', 'contribuyenteEspecial', 'obligadoContabilidad']],
    'notaDebito' => ['notaDebito', 'infoNotaDebito', ['identificacionComprador', 'contribuyenteEspecial', 'codDocModificado']],
    'comprobanteRetencion' => ['comprobanteRetencion', 'infoCompRetencion', ['dirEstablecimiento', 'contribuyenteEspecial', 'obligadoContabilidad']],
    'liquidacionCompra' => ['liquidacionCompra', 'infoLiquidacionCompra', ['fechaEmision', 'contribuyenteEspecial', 'tipoIdentificacionProveedor']],
    'guiaRemision' => ['guiaRemision', 'infoGuiaRemision', ['rucTransportista', 'contribuyenteEspecial', 'fechaIniTransporte']],
]);

it('sin designaciones no emite ningún tag y el XML es el de siempre', function (string $tipo) {
    $comprobante = comprobante_de_prueba($tipo);
    $emision = new EmisionEnCurso($comprobante, certificado_de_prueba());
    (new AgregarLeyendasEmisor)($emision, fn (EmisionEnCurso $e): EmisionEnCurso => $e);

    $xml = ConstruirXml::render($comprobante);

    expect($xml)->toBe(xml_de_prueba($tipo))
        ->not->toContain('<agenteRetencion')
        ->not->toContain('<contribuyenteEspecial');
})->with('tipos');

it('rechaza :dataset si viene en el payload', function (string $campo, Closure $contaminar) {
    $comprobante = comprobante_de_prueba('factura');
    $contaminar($comprobante);

    expect(fn () => AgregarLeyendasEmisor::rechazarSiVieneEnElPayload($comprobante))
        ->toThrow(DatoInvalido::class, "«{$campo}» lo fija la configuración del contribuyente");
})->with([
    'agenteRetencion' => ['agenteRetencion', fn (ComprobanteData $c) => $c->infoTributaria->agenteRetencion = '1'],
    'contribuyenteEspecial' => ['contribuyenteEspecial', fn (ComprobanteData $c) => $c->bloqueInfo()->contribuyenteEspecial = '5368'],
]);

it('deja pasar un comprobante sin leyendas', function () {
    AgregarLeyendasEmisor::rechazarSiVieneEnElPayload(comprobante_de_prueba('factura'));
})->throwsNoExceptions();

it('recupera ambas leyendas al releer el XML de :dataset', function (string $tipo) {
    $xml = ConstruirXml::render(con_leyendas($tipo));

    $releido = (new ComprobanteXmlParser)->parse($xml);

    expect($releido->infoTributaria->agenteRetencion)->toBe('6498')
        ->and($releido->bloqueInfo()->contribuyenteEspecial)->toBe('5368')
        ->and(ConstruirXml::render($releido))->toBe($xml);
})->with('tipos');

/*
 * Anexo 21, ejemplo 2 (formato RIDE): "Contribuyente Especial" y "Agente de
 * Retención Resolución No." en la caja del emisor.
 */
it('muestra las leyendas en la cabecera del RIDE', function () {
    $factura = con_leyendas('factura');
    $registro = Comprobante::factory()->autorizado()->make([
        'clave_acceso' => (string) $factura->infoTributaria->claveAcceso,
    ]);

    $html = view('ride.factura', [
        'registro' => $registro,
        'comprobante' => $factura,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    expect($html)->toContain('Agente de Retención Resolución No.')
        ->toContain('6498')
        ->toContain('Contribuyente Especial Nro.')
        ->toContain('5368');
});

it('no dibuja leyendas en el RIDE de un emisor sin designaciones', function () {
    $factura = comprobante_de_prueba('factura');
    $registro = Comprobante::factory()->autorizado()->make([
        'clave_acceso' => (string) $factura->infoTributaria->claveAcceso,
    ]);

    $html = view('ride.factura', [
        'registro' => $registro,
        'comprobante' => $factura,
        'logo' => null,
        'codigoBarras' => null,
    ])->render();

    expect($html)->not->toContain('Agente de Retención')
        ->not->toContain('Contribuyente Especial');
});

/*
 * Formatos de la ficha: la resolución de agente de retención es numérica,
 * máximo 8 dígitos y "omitiendo los ceros a la izquierda" (Anexo 21); la
 * de contribuyente especial, alfanumérica de 3 a 13 caracteres.
 */
it('normaliza la resolución de agente de retención quitando los ceros a la izquierda', function () {
    $leyendas = LeyendasEmisor::de('00006498', ' 5368 ');

    expect($leyendas->agenteRetencion)->toBe('6498')
        ->and($leyendas->contribuyenteEspecial)->toBe('5368');
});

it('trata la cadena vacía como "no designado"', function () {
    $leyendas = LeyendasEmisor::de('', null);

    expect($leyendas->agenteRetencion)->toBeNull()
        ->and($leyendas->contribuyenteEspecial)->toBeNull()
        ->and($leyendas->vacias())->toBeTrue();
});

it('rechaza una resolución de agente de retención inválida: :dataset', function (string $valor) {
    expect(fn () => LeyendasEmisor::de($valor, null))->toThrow(DatoInvalido::class, 'agenteRetencion');
})->with(['solo ceros' => '0000', 'con letras' => 'NAC-123', 'nueve dígitos' => '123456789']);

it('rechaza una resolución de contribuyente especial inválida: :dataset', function (string $valor) {
    expect(fn () => LeyendasEmisor::de(null, $valor))->toThrow(DatoInvalido::class, 'contribuyenteEspecial');
})->with(['muy corta' => '12', 'con guion' => 'NAC-1', 'catorce caracteres' => '12345678901234']);
