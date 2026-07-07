@extends('ride.base')

@section('emisor-extra')
    @if ($comprobante->infoCompRetencion->dirEstablecimiento)
        <div>
            <span class="etiqueta">Dirección sucursal:</span>
            {{ $comprobante->infoCompRetencion->dirEstablecimiento }}
        </div>
    @endif
    <div class="mt">
        <span class="etiqueta">Obligado a llevar contabilidad:</span>
        {{ $comprobante->infoCompRetencion->obligadoContabilidad }}
    </div>
@endsection

@section('cuerpo')
    <div class="marco">
        <table>
            <tr>
                <td>
                    <span class="etiqueta">Razón social / Nombres (sujeto retenido):</span>
                    {{ $comprobante->infoCompRetencion->razonSocialSujetoRetenido }}
                </td>
                <td>
                    <span class="etiqueta">Identificación:</span>
                    {{ $comprobante->infoCompRetencion->identificacionSujetoRetenido }}
                </td>
            </tr>
            <tr>
                <td>
                    <span class="etiqueta">Fecha de emisión:</span>
                    {{ $comprobante->infoCompRetencion->fechaEmision->format('d/m/Y') }}
                </td>
                <td>
                    <span class="etiqueta">Período fiscal:</span>
                    {{ $comprobante->infoCompRetencion->periodoFiscal }}
                </td>
            </tr>
        </table>
    </div>

    <table class="tabla">
        <thead>
            <tr>
                <th>Comprobante de sustento</th>
                <th>Número</th>
                <th>Fecha</th>
                <th class="num">Base imponible</th>
                <th>Impuesto</th>
                <th class="num">% Retención</th>
                <th class="num">Valor retenido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($comprobante->impuestos as $impuesto)
                <tr>
                    <td>{{ $impuesto->codDocSustento->etiqueta() }}</td>
                    <td>{{ $impuesto->numDocSustento }}</td>
                    <td>{{ $impuesto->fechaEmisionDocSustento->format('d/m/Y') }}</td>
                    <td class="num">{{ $impuesto->baseImponible }}</td>
                    <td>{{ $impuesto->codigo }} ({{ $impuesto->codigoRetencion }})</td>
                    <td class="num">{{ $impuesto->porcentajeRetener }}</td>
                    <td class="num">{{ $impuesto->valorRetenido }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
