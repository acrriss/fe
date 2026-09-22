<?php

use App\Sri\Data\CampoAdicionalData;
use App\Sri\Data\DetalleData;
use App\Sri\Data\Factura\InfoFacturaData;
use App\Sri\Data\ImpuestoData;
use App\Sri\Data\InfoTributariaData;
use App\Sri\Data\NotaCredito\InfoNotaCreditoData;
use App\Sri\Data\TotalImpuestoData;
use Symfony\Component\Yaml\Yaml;

/*
 * El payload documentado debe ser exactamente el que aceptan los DTOs.
 *
 * Desde que una clave desconocida responde 422 (§14), el esquema dejó de
 * ser orientativo: es la única lista de la que un integrador puede
 * deducir qué enviar. Si alguien añade un campo al DTO y no al YAML, el
 * campo existe pero nadie sabe que existe; si lo quita del DTO y no del
 * YAML, el que lo envíe recibe un 422 documentado como válido.
 */

/**
 * @return list<string>
 */
function propiedades_del_dto(string $dataClass): array
{
    return array_map(
        fn (ReflectionProperty $propiedad): string => $propiedad->getName(),
        (new ReflectionClass($dataClass))->getProperties(ReflectionProperty::IS_PUBLIC),
    );
}

/**
 * @return list<string>
 */
function propiedades_del_esquema(string $esquema): array
{
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    return array_keys($spec['components']['schemas'][$esquema]['properties'] ?? []);
}

it('documenta exactamente las claves que acepta :dataset', function (string $esquema, string $dataClass, array $ignoradas) {
    $documentadas = propiedades_del_esquema($esquema);
    $admitidas = [...propiedades_del_dto($dataClass), ...$ignoradas];

    sort($documentadas);
    sort($admitidas);

    expect($documentadas)->toBe($admitidas);
})->with([
    'infoTributaria' => ['InfoTributaria', InfoTributariaData::class, ['codDoc']],
    'infoFactura' => ['InfoFactura', InfoFacturaData::class, []],
    'infoNotaCredito' => ['InfoNotaCredito', InfoNotaCreditoData::class, []],
    'detalle' => ['Detalle', DetalleData::class, []],
    'impuesto' => ['Impuesto', ImpuestoData::class, []],
    'totalImpuesto' => ['TotalImpuesto', TotalImpuestoData::class, []],
    'campoAdicional' => ['CampoAdicional', CampoAdicionalData::class, []],
]);

/*
 * `additionalProperties: false` es lo que traduce la guardia al lenguaje
 * del esquema: sin eso, un generador de clientes aceptaría claves que el
 * servicio rechaza.
 */
it(':dataset declara que no admite claves adicionales', function (string $esquema) {
    $spec = Yaml::parseFile(base_path('docs/openapi.yaml'));

    expect($spec['components']['schemas'][$esquema]['additionalProperties'] ?? null)->toBeFalse();
})->with(['InfoTributaria', 'InfoFactura', 'InfoNotaCredito', 'Detalle', 'Impuesto', 'TotalImpuesto', 'CampoAdicional']);
