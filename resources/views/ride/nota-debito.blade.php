@extends('ride.base')

@section('emisor-extra')
    @if ($comprobante->infoNotaDebito->dirEstablecimiento)
        <div><span class="etiqueta">Dirección sucursal:</span> {{ $comprobante->infoNotaDebito->dirEstablecimiento }}</div>
    @endif
    <div class="mt">
        <span class="etiqueta">Obligado a llevar contabilidad:</span>
        {{ $comprobante->infoNotaDebito->obligadoContabilidad ?? 'NO' }}
    </div>
@endsection

@section('cuerpo')
    <div class="marco">
        <table>
            <tr>
                <td><span class="etiqueta">Razón social:</span> {{ $comprobante->infoNotaDebito->razonSocialComprador }}</td>
                <td><span class="etiqueta">Identificación:</span> {{ $comprobante->infoNotaDebito->identificacionComprador }}</td>
                <td><span class="etiqueta">Fecha de emisión:</span> {{ $comprobante->infoNotaDebito->fechaEmision->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td>
                    <span class="etiqueta">Comprobante que modifica:</span>
                    {{ $comprobante->infoNotaDebito->codDocModificado->etiqueta() }}
                    {{ $comprobante->infoNotaDebito->numDocModificado }}
                </td>
                <td colspan="2">
                    <span class="etiqueta">Fecha del documento:</span>
                    {{ $comprobante->infoNotaDebito->fechaEmisionDocSustento->format('d/m/Y') }}
                </td>
            </tr>
        </table>
    </div>

    <table class="tabla">
        <thead>
            <tr>
                <th>Motivo</th>
                <th class="num">Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comprobante->motivos as $motivo)
                <tr>
                    <td>{{ $motivo->razon }}</td>
                    <td class="num">{{ $motivo->valor }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totales mt">
        <tr>
            <td class="etiqueta">Subtotal sin impuestos</td>
            <td class="num">{{ $comprobante->infoNotaDebito->totalSinImpuestos }}</td>
        </tr>
        @foreach ($comprobante->infoNotaDebito->impuestos as $impuesto)
            <tr>
                <td class="etiqueta">Impuesto {{ $impuesto->codigo }} ({{ $impuesto->codigoPorcentaje }}) — base {{ $impuesto->baseImponible }}</td>
                <td class="num">{{ $impuesto->valor }}</td>
            </tr>
        @endforeach
        <tr class="total-final">
            <td>VALOR TOTAL</td>
            <td class="num">{{ $comprobante->infoNotaDebito->valorTotal }}</td>
        </tr>
    </table>
@endsection
