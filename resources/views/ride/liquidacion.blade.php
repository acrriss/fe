@extends('ride.base')

@section('emisor-extra')
    @if ($comprobante->infoLiquidacionCompra->dirEstablecimiento)
        <div><span class="etiqueta">Dirección sucursal:</span> {{ $comprobante->infoLiquidacionCompra->dirEstablecimiento }}</div>
    @endif
    <div class="mt">
        <span class="etiqueta">Obligado a llevar contabilidad:</span>
        {{ $comprobante->infoLiquidacionCompra->obligadoContabilidad ?? 'NO' }}
    </div>
@endsection

@section('cuerpo')
    <div class="marco">
        <table>
            <tr>
                <td><span class="etiqueta">Proveedor:</span> {{ $comprobante->infoLiquidacionCompra->razonSocialProveedor }}</td>
                <td><span class="etiqueta">Identificación:</span> {{ $comprobante->infoLiquidacionCompra->identificacionProveedor }}</td>
                <td><span class="etiqueta">Fecha de emisión:</span> {{ $comprobante->infoLiquidacionCompra->fechaEmision->format('d/m/Y') }}</td>
            </tr>
        </table>
    </div>

    <table class="tabla">
        <thead>
            <tr>
                <th>Código</th>
                <th class="num">Cantidad</th>
                <th>Descripción</th>
                <th class="num">Precio unitario</th>
                <th class="num">Descuento</th>
                <th class="num">Precio total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comprobante->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->codigoPrincipal ?? $detalle->codigoInterno }}</td>
                    <td class="num">{{ $detalle->cantidad }}</td>
                    <td>{{ $detalle->descripcion }}</td>
                    <td class="num">{{ $detalle->precioUnitario }}</td>
                    <td class="num">{{ $detalle->descuento }}</td>
                    <td class="num">{{ $detalle->precioTotalSinImpuesto }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totales mt">
        <tr>
            <td class="etiqueta">Subtotal sin impuestos</td>
            <td class="num">{{ $comprobante->infoLiquidacionCompra->totalSinImpuestos }}</td>
        </tr>
        @foreach ($comprobante->infoLiquidacionCompra->totalConImpuestos as $impuesto)
            <tr>
                <td class="etiqueta">{{ $impuesto->etiqueta() }} — base {{ $impuesto->baseImponible }}</td>
                <td class="num">{{ $impuesto->valor }}</td>
            </tr>
        @endforeach
        <tr class="total-final">
            <td>VALOR TOTAL ({{ $comprobante->infoLiquidacionCompra->moneda }})</td>
            <td class="num">{{ $comprobante->infoLiquidacionCompra->importeTotal }}</td>
        </tr>
    </table>
@endsection
