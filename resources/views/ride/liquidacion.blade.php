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

    @include('ride.partials.totales', [
        'totalConImpuestos' => $comprobante->infoLiquidacionCompra->totalConImpuestos,
        'totalSinImpuestos' => $comprobante->infoLiquidacionCompra->totalSinImpuestos,
        'etiquetaTotal' => "VALOR TOTAL ({$comprobante->infoLiquidacionCompra->moneda})",
        'total' => $comprobante->infoLiquidacionCompra->importeTotal,
    ])
@endsection
