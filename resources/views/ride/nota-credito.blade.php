@extends('ride.base')

@section('emisor-extra')
    @if ($comprobante->infoNotaCredito->dirEstablecimiento)
        <div>
            <span class="etiqueta">Dirección sucursal:</span>
            {{ $comprobante->infoNotaCredito->dirEstablecimiento }}
        </div>
    @endif
    <div class="mt">
        <span class="etiqueta">Obligado a llevar contabilidad:</span>
        {{ $comprobante->infoNotaCredito->obligadoContabilidad }}
    </div>
@endsection

@section('cuerpo')
    <div class="marco">
        <table>
            <tr>
                <td>
                    <span class="etiqueta">Razón social / Nombres:</span>
                    {{ $comprobante->infoNotaCredito->razonSocialComprador }}
                </td>
                <td>
                    <span class="etiqueta">Identificación:</span>
                    {{ $comprobante->infoNotaCredito->identificacionComprador }}
                </td>
                <td>
                    <span class="etiqueta">Fecha de emisión:</span>
                    {{ $comprobante->infoNotaCredito->fechaEmision->format('d/m/Y') }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="etiqueta">Comprobante que modifica:</span>
                    {{ $comprobante->infoNotaCredito->codDocModificado->etiqueta() }}
                    {{ $comprobante->infoNotaCredito->numDocModificado }}
                </td>
                <td>
                    <span class="etiqueta">Fecha del documento:</span>
                    {{ $comprobante->infoNotaCredito->fechaEmisionDocSustento->format('d/m/Y') }}
                </td>
                <td>
                    <span class="etiqueta">Motivo:</span>
                    {{ $comprobante->infoNotaCredito->motivo }}
                </td>
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
            <td class="num">{{ $comprobante->infoNotaCredito->totalSinImpuestos }}</td>
        </tr>
        @foreach ($comprobante->infoNotaCredito->totalConImpuestos as $impuesto)
            <tr>
                <td class="etiqueta">
                    {{ $impuesto->etiqueta() }}
                    — base {{ $impuesto->baseImponible }}
                </td>
                <td class="num">{{ $impuesto->valor }}</td>
            </tr>
        @endforeach
        <tr class="total-final">
            <td>VALOR MODIFICACIÓN ({{ $comprobante->infoNotaCredito->moneda }})</td>
            <td class="num">{{ $comprobante->infoNotaCredito->valorModificacion }}</td>
        </tr>
    </table>
@endsection
