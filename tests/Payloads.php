<?php

/*
|--------------------------------------------------------------------------
| Payloads de prueba
|--------------------------------------------------------------------------
|
| Fuente única de los comprobantes que usan los tests: un builder por tipo
| con datos válidos para el dominio actual (IVA 15 %, identificaciones con
| dígito verificador correcto, sin codDoc ni claveAcceso: ambos los deriva
| el dominio). Se cargan desde Pest.php.
|
| payload_comprobante($tipo) devuelve el subárbol del comprobante y
| payload_emision($tipo) lo envuelve como {tipo: …}, listo para POSTear.
|
*/

/**
 * RUC del contribuyente de prueba: el que crean actuar_como_contribuyente()
 * y contribuyente_gestionado(), y el que llevan todos los payloads.
 */
const RUC_PRUEBA = '0922596788001';

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
        'nombreComercial' => 'PRUEBA',
        'ruc' => RUC_PRUEBA,
        'estab' => '001',
        'ptoEmi' => '001',
        'secuencial' => $secuencial,
        'dirMatriz' => 'Av. Principal 100',
    ];
}

/**
 * @return array<string, mixed>
 */
function payload_factura(): array
{
    return [
        'infoTributaria' => info_tributaria('000000001'),
        'infoFactura' => [
            'fechaEmision' => '10/07/2026',
            'dirEstablecimiento' => 'Av. Principal 100',
            'obligadoContabilidad' => 'SI',
            'tipoIdentificacionComprador' => '07',
            'razonSocialComprador' => 'CONSUMIDOR FINAL',
            'identificacionComprador' => '9999999999999',
            'totalSinImpuestos' => '100.00',
            'totalDescuento' => '0.00',
            'totalConImpuestos' => ['totalImpuesto' => [
                ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '100.00', 'tarifa' => '15.00', 'valor' => '15.00'],
            ]],
            'propina' => '0.00',
            'importeTotal' => '115.00',
            'moneda' => 'DOLAR',
        ],
        'detalles' => ['detalle' => [
            [
                'codigoPrincipal' => 'SERV-01',
                'descripcion' => 'Servicio de consultoría',
                'cantidad' => '1.00',
                'precioUnitario' => '100.00',
                'descuento' => '0.00',
                'precioTotalSinImpuesto' => '100.00',
                // un solo impuesto como objeto (no lista): el caso que más
                // integradores envían y que Payload::lista() debe normalizar
                'impuestos' => ['impuesto' => [
                    'codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '100.00', 'valor' => '15.00',
                ]],
            ],
        ]],
    ];
}

/**
 * Nota de crédito con dos detalles (uno gravado al 15 % y otro al 0 %).
 *
 * @return array<string, mixed>
 */
function payload_nota_credito(): array
{
    return [
        'infoTributaria' => info_tributaria('000000002'),
        'infoNotaCredito' => [
            'fechaEmision' => '10/07/2026',
            'dirEstablecimiento' => 'Av. Principal 100',
            'tipoIdentificacionComprador' => '04',
            'razonSocialComprador' => 'CLIENTE S.A.',
            'identificacionComprador' => '1713328506001',
            'obligadoContabilidad' => 'SI',
            'codDocModificado' => '01',
            'numDocModificado' => '001-001-000000001',
            'fechaEmisionDocSustento' => '01/07/2026',
            'totalSinImpuestos' => '80.00',
            'valorModificacion' => '87.50',
            'moneda' => 'DOLAR',
            'totalConImpuestos' => ['totalImpuesto' => [
                ['codigo' => '2', 'codigoPorcentaje' => '4', 'baseImponible' => '50.00', 'valor' => '7.50'],
                ['codigo' => '2', 'codigoPorcentaje' => '0', 'baseImponible' => '30.00', 'valor' => '0.00'],
            ]],
            'motivo' => 'Devolución de productos o servicios',
        ],
        'detalles' => ['detalle' => [
            [
                'codigoInterno' => 'PV-0001',
                'descripcion' => 'Servicio devuelto',
                'cantidad' => '1.00',
                'precioUnitario' => '50.00',
                'descuento' => '0.00',
                'precioTotalSinImpuesto' => '50.00',
                'impuestos' => ['impuesto' => [
                    'codigo' => '2', 'codigoPorcentaje' => '4', 'tarifa' => '15.00', 'baseImponible' => '50.00', 'valor' => '7.50',
                ]],
            ],
            [
                'codigoInterno' => 'PV-0002',
                'descripcion' => 'Producto exento devuelto',
                'cantidad' => '1.00',
                'precioUnitario' => '30.00',
                'descuento' => '0.00',
                'precioTotalSinImpuesto' => '30.00',
                'impuestos' => ['impuesto' => [
                    'codigo' => '2', 'codigoPorcentaje' => '0', 'tarifa' => '0.00', 'baseImponible' => '30.00', 'valor' => '0.00',
                ]],
            ],
        ]],
    ];
}

/**
 * @return array<string, mixed>
 */
function payload_retencion(): array
{
    return [
        'infoTributaria' => info_tributaria('000000003'),
        'infoCompRetencion' => [
            'fechaEmision' => '10/07/2026',
            'dirEstablecimiento' => 'Av. Principal 100',
            'obligadoContabilidad' => 'SI',
            'tipoIdentificacionSujetoRetenido' => '04',
            'razonSocialSujetoRetenido' => 'PROVEEDOR S.A.',
            'identificacionSujetoRetenido' => '1792146739001',
            'periodoFiscal' => '07/2026',
        ],
        'impuestos' => ['impuesto' => [
            [
                'codigo' => '2',
                'codigoRetencion' => '2',
                'baseImponible' => '15.00',
                'porcentajeRetener' => '70',
                'valorRetenido' => '10.50',
                'codDocSustento' => '01',
                'numDocSustento' => '001001000000001',
                'fechaEmisionDocSustento' => '01/07/2026',
            ],
        ]],
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

/**
 * Subárbol del comprobante del tipo dado (por nombre del elemento raíz).
 *
 * @return array<string, mixed>
 */
function payload_comprobante(string $tipo): array
{
    return match ($tipo) {
        'factura' => payload_factura(),
        'notaCredito' => payload_nota_credito(),
        'comprobanteRetencion' => payload_retencion(),
        'notaDebito' => payload_nota_debito(),
        'guiaRemision' => payload_guia_remision(),
        'liquidacionCompra' => payload_liquidacion(),
        default => throw new InvalidArgumentException("No hay payload de prueba para «{$tipo}»."),
    };
}

/**
 * Payload completo de emisión ({tipo: comprobante}) listo para POSTear.
 *
 * @return array<string, array<string, mixed>>
 */
function payload_emision(string $tipo): array
{
    return [$tipo => payload_comprobante($tipo)];
}
