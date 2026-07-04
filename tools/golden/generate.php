<?php
/**
 * Generador de fixtures golden-master — Fase 0.
 *
 * Replica EXACTAMENTE la lógica del proyecto legado (legacy/) para pinnear su
 * comportamiento antes del refactor:
 *
 *  - claveDeAcceso(): copiado verbatim de legacy/app/ApiSRI/ApiSRI.php:87
 *  - construcción de la cadena de entrada: replica ApiController::store()
 *    (legacy/app/Http/Controllers/ApiController.php:46-49)
 *  - createXML(): replica legacy/app/ApiSRI/ApiSRI.php:62 (spatie/array-to-xml,
 *    rootElementName = tipo, id=comprobante, version 1.0.0 para retención /
 *    1.1.0 para el resto, formatOutput = true, UTF-8)
 *
 * Entrada:  legacy/exampleBody*.json
 * Salida:   fixtures/golden/<tipo>/{input.json, claveAcceso.txt, comprobante.xml, meta.json}
 *
 * El input.json se guarda SANITIZADO: los blobs base64 de info.p12/info.logo y
 * la clave del certificado se reemplazan por placeholders (no afectan al XML).
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Spatie\ArrayToXml\ArrayToXml;

// ---------------------------------------------------------------------------
// Copia VERBATIM de legacy/app/ApiSRI/ApiSRI.php::claveDeAcceso()
// (no modernizar: es el comportamiento a pinnear)
// ---------------------------------------------------------------------------
function claveDeAcceso($cadena)
{
    $cadena = trim($cadena);
    $caa= $cadena;
    $baseMultiplicador=7;
    $aux=new \SplFixedArray(strlen($cadena));
    $aux=$aux->toArray();
    $multiplicador = 2;
    $total=0;
    $verificador=0;

    for($i = count($aux)-1;$i >=0; --$i){
        $aux[$i]=substr($cadena,$i,1);
        @$aux[$i]*=$multiplicador;
        ++$multiplicador;
        if ($multiplicador>$baseMultiplicador){
            $multiplicador =2;
        }
        $total+=$aux[$i];
    }

    if (($total==0)||($total==1))$verificador=0;else{
        $verificador=(11-($total%11)==11)?0:11-($total%11);
    }
    if ($verificador==10){
        $verificador=1;
    }
    return $caa.$verificador;
}

// ---------------------------------------------------------------------------
// Réplica de legacy/app/ApiSRI/ApiSRI.php::createXML() — devuelve el XML string
// ---------------------------------------------------------------------------
function createXML(string $tipo, array $data): string
{
    $version = $tipo === 'comprobanteRetencion' ? '1.0.0' : '1.1.0';

    $artxml = new ArrayToXml($data, [
        'rootElementName' => $tipo,
        '_attributes' => [
            'id' => 'comprobante',
            'version' => $version,
        ],
    ], true, 'UTF-8');

    $dom = $artxml->toDom();
    $dom->formatOutput = true;

    return $dom->saveXML();
}

// ---------------------------------------------------------------------------
// Proceso principal
// ---------------------------------------------------------------------------
$legacyDir = dirname(__DIR__, 2) . '/legacy';
$outBase   = dirname(__DIR__, 2) . '/fixtures/golden';

$examples = glob($legacyDir . '/exampleBody*.json');
if ($examples === false || $examples === []) {
    fwrite(STDERR, "No se encontraron exampleBody*.json en {$legacyDir}\n");
    exit(1);
}

foreach ($examples as $file) {
    $payload = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

    // Réplica del contrato posicional del controller:
    //   array_keys($factura)[0]                  → tipo de comprobante
    //   array_keys($factura[tipo])[0]            → infoTributaria
    //   array_keys($factura[tipo])[1]            → infoFactura / infoNotaCredito / infoCompRetencion
    $tipo     = array_keys($payload)[0];
    $doc      = $payload[$tipo];
    $keyTrib  = array_keys($doc)[0];
    $keyInfo  = array_keys($doc)[1];
    $infoTrib = $doc[$keyTrib];
    $infoDoc  = $doc[$keyInfo];

    // Cadena de 48 dígitos — réplica de ApiController::store() líneas 46-48
    // (incluye el código numérico fijo "22568496" del legado)
    $cadena = str_replace('/', '', $infoDoc['fechaEmision'])
        . $infoTrib['codDoc']
        . $infoTrib['ruc']
        . $infoTrib['ambiente']
        . $infoTrib['estab']
        . $infoTrib['ptoEmi']
        . $infoTrib['secuencial']
        . '22568496'
        . $infoTrib['tipoEmision'];

    $claveAcceso = claveDeAcceso($cadena);

    // El controller inyecta la clave en infoTributaria antes de generar el XML
    $doc[$keyTrib]['claveAcceso'] = $claveAcceso;

    $xml = createXML($tipo, $doc);

    // ---- Sanitizar el input para el fixture (los blobs no afectan al XML) ----
    $sanitized = $payload;
    $sanitized[$tipo][$keyTrib]['claveAcceso'] = ''; // como llega en el request
    if (isset($sanitized['info'])) {
        $sanitized['info']['p12']      = '<<BASE64_P12_PLACEHOLDER>>';
        $sanitized['info']['logo']     = '<<BASE64_LOGO_PLACEHOLDER>>';
        $sanitized['info']['clavep12'] = '<<SECRET_PLACEHOLDER>>';
    }

    $outDir = $outBase . '/' . $tipo;
    if (!is_dir($outDir)) {
        mkdir($outDir, 0755, true);
    }

    file_put_contents($outDir . '/input.json', json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents($outDir . '/claveAcceso.txt', $claveAcceso . "\n");
    file_put_contents($outDir . '/comprobante.xml', $xml);
    file_put_contents($outDir . '/meta.json', json_encode([
        'source'            => basename($file),
        'tipo'              => $tipo,
        'infoKey'           => $keyInfo,
        'cadenaSinVerificador' => $cadena,
        'claveAcceso'       => $claveAcceso,
        'xmlVersionAttr'    => $tipo === 'comprobanteRetencion' ? '1.0.0' : '1.1.0',
        'xmlFileName'       => $claveAcceso . '.xml',
        'generator'         => 'tools/golden/generate.php (réplica del legado)',
        'arrayToXmlVersion' => \Composer\InstalledVersions::getVersion('spatie/array-to-xml'),
        'generatedAt'       => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    printf("✔ %-22s clave=%s  xml=%d bytes\n", $tipo, $claveAcceso, strlen($xml));
}

echo "\nFixtures escritos en fixtures/golden/\n";
