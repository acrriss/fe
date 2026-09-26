@extends('ride.base')

@section('emisor-extra')
    @if ($comprobante->infoFactura->dirEstablecimiento)
        <div>
            <span class="etiqueta">Dirección sucursal:</span>
            {{ $comprobante->infoFactura->dirEstablecimiento }}
        </div>
    @endif
    <div class="mt">
        <span class="etiqueta">Obligado a llevar contabilidad:</span>
        {{ $comprobante->infoFactura->obligadoContabilidad }}
    </div>
@endsection

@section('cuerpo')
    <div class="marco">
        <table>
            <tr>
                <td>
                    <span class="etiqueta">Razón social / Nombres:</span>
                    {{ $comprobante->infoFactura->razonSocialComprador }}
                </td>
                <td>
                    <span class="etiqueta">Identificación:</span>
                    {{ $comprobante->infoFactura->identificacionComprador }}
                </td>
                <td>
                    <span class="etiqueta">Fecha de emisión:</span>
                    {{ $comprobante->infoFactura->fechaEmision->format('d/m/Y') }}
                </td>
                {{-- Anexo 25 §2: la ficha exige la placa en el XML y no dice
                     nada del RIDE, pero el §9.19 permite imprimir datos
                     adicionales «conforme lo requiera el contribuyente» y al
                     cliente del transporte le identifica el servicio. --}}
                @if ($comprobante->infoFactura->placa)
                    <td>
                        <span class="etiqueta">Placa:</span>
                        {{ $comprobante->infoFactura->placa }}
                    </td>
                @endif
            </tr>
            {{-- Anexo 2: la dirección ocupa su propia línea bajo los datos
                 del comprador; el correo y el teléfono no son campos del XML
                 y salen en «Información adicional» (campoAdicional). --}}
            @if ($comprobante->infoFactura->direccionComprador)
                <tr>
                    <td colspan="4">
                        <span class="etiqueta">Dirección:</span>
                        {{ $comprobante->infoFactura->direccionComprador }}
                    </td>
                </tr>
            @endif
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
        'totalConImpuestos' => $comprobante->infoFactura->totalConImpuestos,
        'totalSinImpuestos' => $comprobante->infoFactura->totalSinImpuestos,
        'totalDescuento' => $comprobante->infoFactura->totalDescuento,
        'propina' => $comprobante->infoFactura->propina,
        'etiquetaTotal' => "VALOR TOTAL ({$comprobante->infoFactura->moneda})",
        'total' => $comprobante->infoFactura->importeTotal,
    ])
@endsection
