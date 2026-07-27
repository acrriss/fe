<?php

use App\Sri\Actions\ConstruirXml;
use App\Sri\Contracts\SriGateway;
use App\Sri\Contracts\XmlSigner;
use App\Sri\Data\GuiaRemision\GuiaRemisionData;
use App\Sri\Data\Liquidacion\LiquidacionCompraData;
use App\Sri\Data\NotaDebito\NotaDebitoData;
use App\Sri\Enums\Ambiente;
use App\Sri\Enums\TipoComprobante;
use App\Sri\Firma\FakeXmlSigner;
use App\Sri\Gateways\FakeSriGateway;
use App\Sri\Support\ComprobanteXmlParser;
use App\Sri\ValueObjects\ClaveAcceso;
use App\Sri\ValueObjects\CodigoNumerico;
use App\Sri\ValueObjects\Ruc;
use App\Sri\ValueObjects\Secuencial;
use Illuminate\Support\Facades\Storage;

/**
 * infoTributaria común (RUC = el del contribuyente de prueba).
 *
 * @return array<string, string>
 */
function info_tributaria(string $secuencial): array
{
    return [
        'ambiente' => '1',
        'tipoEmision' => '1',
        'razonSocial' => 'EMPRESA DE PRUEBA S.A.',
        'ruc' => '0922596788001',
        'estab' => '001',
        'ptoEmi' => '001',
        'secuencial' => $secuencial,
        'dirMatriz' => 'Av. Principal 100',
    ];
}

/**
 * @return array<string, mixed>
 */
function payload_nota_debito(): array
{
    return [
        'infoTributaria' => info_tributaria('000000010'),
        'infoNotaDebito' => [
            'fechaEmision' => '10/07/2026',
            'tipoIdentificacionComprador' => '04',
            'razonSocialComprador' => 'CLIENTE S.A.',
            'identificacionComprador' => '1713328506001',
            'codDocModificado' => '01',
            'numDocModificado' => '001-001-000000001',
            'fechaEmisionDocSustento' => '01/07/2026',
            'totalSinImpuestos' => '50.00',
            'impuestos' => ['impuesto' => [
                'codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '50.00', 'valor' => '7.50',
            ]],
            'valorTotal' => '57.50',
            'pagos' => ['pago' => ['formaPago' => '01', 'total' => '57.50']],
        ],
        'motivos' => ['motivo' => ['razon' => 'Interés por mora', 'valor' => '50.00']],
    ];
}

/**
 * @return array<string, mixed>
 */
function payload_guia_remision(): array
{
    return [
        'infoTributaria' => info_tributaria('000000011'),
        'infoGuiaRemision' => [
            'dirPartida' => 'Bodega Central s/n',
            'razonSocialTransportista' => 'TRANSPORTES S.A.',
            'tipoIdentificacionTransportista' => '04',
            'rucTransportista' => '1792146739001',
            'fechaIniTransporte' => '10/07/2026',
            'fechaFinTransporte' => '11/07/2026',
            'placa' => 'MCL0827',
        ],
        'destinatarios' => ['destinatario' => [
            'identificacionDestinatario' => '1713328506001',
            'razonSocialDestinatario' => 'DESTINO S.A.',
            'dirDestinatario' => 'Av. Simón Bolívar s/n',
            'motivoTraslado' => 'Venta de mercadería',
            'ruta' => 'Quito - Otavalo',
            'detalles' => ['detalle' => [
                'codigoInterno' => 'ART-001', 'descripcion' => 'Caja de repuestos', 'cantidad' => '10.00',
            ]],
        ]],
    ];
}

/**
 * @return array<string, mixed>
 */
function payload_liquidacion(): array
{
    return [
        'infoTributaria' => info_tributaria('000000012'),
        'infoLiquidacionCompra' => [
            'fechaEmision' => '10/07/2026',
            'tipoIdentificacionProveedor' => '05',
            'razonSocialProveedor' => 'PROVEEDOR ARTESANAL',
            'identificacionProveedor' => '1713328506',
            'totalSinImpuestos' => '50.00',
            'totalDescuento' => '0.00',
            'totalConImpuestos' => ['totalImpuesto' => [
                'codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '50.00', 'tarifa' => '15', 'valor' => '7.50',
            ]],
            'importeTotal' => '57.50',
            'moneda' => 'DOLAR',
            'pagos' => ['pago' => ['formaPago' => '01', 'total' => '57.50']],
        ],
        'detalles' => ['detalle' => [
            'codigoPrincipal' => 'SERV-01',
            'descripcion' => 'Servicio prestado',
            'cantidad' => '1.00',
            'precioUnitario' => '50.00',
            'descuento' => '0.00',
            'precioTotalSinImpuesto' => '50.00',
            'impuestos' => ['impuesto' => [
                'codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '50.00', 'valor' => '7.50',
            ]],
        ]],
    ];
}

dataset('tipos nuevos', [
    'notaDebito' => ['notaDebito', NotaDebitoData::class, TipoComprobante::NotaDebito, '05', '1.0.0', 'payload_nota_debito'],
    'guiaRemision' => ['guiaRemision', GuiaRemisionData::class, TipoComprobante::GuiaRemision, '06', '1.0.0', 'payload_guia_remision'],
    'liquidacionCompra' => ['liquidacionCompra', LiquidacionCompraData::class, TipoComprobante::LiquidacionCompra, '03', '1.1.0', 'payload_liquidacion'],
]);

it('deriva tipo, codDoc y versión de esquema de :dataset', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version) {
    expect($clase::tipo())->toBe($tipo)
        ->and($tipo->value)->toBe($codDoc)
        ->and($tipo->rootElement())->toBe($root)
        ->and($tipo->versionEsquema())->toBe($version);
})->with('tipos nuevos');

it('construye un XML bien formado con la raíz y versión correctas para :dataset', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version, string $payloadFn) {
    $comprobante = $clase::from($payloadFn());
    $comprobante->infoTributaria->claveAcceso = ClaveAcceso::generar(
        fechaEmision: $comprobante->fechaEmision(),
        tipoComprobante: $tipo,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '001',
        puntoEmision: '001',
        secuencial: Secuencial::fromString($comprobante->infoTributaria->secuencial),
        codigoNumerico: CodigoNumerico::fromString('22568496'),
    );

    $xml = ConstruirXml::render($comprobante);
    $dom = simplexml_load_string($xml);

    expect($dom)->not->toBeFalse()
        ->and($dom->getName())->toBe($root)
        ->and((string) $dom['version'])->toBe($version)
        // el codDoc lo deriva el dominio, no el payload
        ->and((string) $dom->infoTributaria->codDoc)->toBe($codDoc);
})->with('tipos nuevos');

it('hace roundtrip XML → DTO → XML para :dataset', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version, string $payloadFn) {
    $comprobante = $clase::from($payloadFn());
    $comprobante->infoTributaria->claveAcceso = ClaveAcceso::generar(
        fechaEmision: $comprobante->fechaEmision(),
        tipoComprobante: $tipo,
        ruc: Ruc::fromString('0922596788001'),
        ambiente: Ambiente::Pruebas,
        establecimiento: '001',
        puntoEmision: '001',
        secuencial: Secuencial::fromString($comprobante->infoTributaria->secuencial),
        codigoNumerico: CodigoNumerico::fromString('22568496'),
    );
    $xml = ConstruirXml::render($comprobante);

    $reparseado = new ComprobanteXmlParser()->parse($xml);

    expect($reparseado)->toBeInstanceOf($clase)
        ->and(ConstruirXml::render($reparseado))->toBe($xml);
})->with('tipos nuevos');

describe('emisión end-to-end de los tipos nuevos', function () {
    beforeEach(function () {
        $this->gateway = new FakeSriGateway;
        $this->app->instance(SriGateway::class, $this->gateway);
        $this->app->instance(XmlSigner::class, new FakeXmlSigner);
        config()->set('sri.firmador.driver', 'nativo');
        config()->set('sri.autorizacion.espera_ms', 0);
        Storage::fake();
        actuar_como_contribuyente();
    });

    it('emite y autoriza :dataset vía la API', function (string $root, string $clase, TipoComprobante $tipo, string $codDoc, string $version, string $payloadFn) {
        $this->postJson(route('api.v1.comprobantes.emitir'), [$root => $payloadFn()])
            ->assertSuccessful()
            ->assertJsonPath('emitido', true)
            ->assertJsonPath('tipo', $root);
    })->with('tipos nuevos');
});
