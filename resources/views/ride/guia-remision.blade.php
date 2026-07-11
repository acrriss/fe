@extends('ride.base')

@section('emisor-extra')
    @if ($comprobante->infoGuiaRemision->dirEstablecimiento)
        <div><span class="etiqueta">Dirección sucursal:</span> {{ $comprobante->infoGuiaRemision->dirEstablecimiento }}</div>
    @endif
    <div class="mt"><span class="etiqueta">Punto de partida:</span> {{ $comprobante->infoGuiaRemision->dirPartida }}</div>
@endsection

@section('cuerpo')
    <div class="marco">
        <table>
            <tr>
                <td><span class="etiqueta">Transportista:</span> {{ $comprobante->infoGuiaRemision->razonSocialTransportista }}</td>
                <td><span class="etiqueta">RUC/Id:</span> {{ $comprobante->infoGuiaRemision->rucTransportista }}</td>
                <td><span class="etiqueta">Placa:</span> {{ $comprobante->infoGuiaRemision->placa }}</td>
            </tr>
            <tr>
                <td><span class="etiqueta">Inicio transporte:</span> {{ $comprobante->infoGuiaRemision->fechaIniTransporte->format('d/m/Y') }}</td>
                <td colspan="2"><span class="etiqueta">Fin transporte:</span> {{ $comprobante->infoGuiaRemision->fechaFinTransporte->format('d/m/Y') }}</td>
            </tr>
        </table>
    </div>

    @foreach ($comprobante->destinatarios as $destinatario)
        <div class="marco">
            <table>
                <tr>
                    <td><span class="etiqueta">Destinatario:</span> {{ $destinatario->razonSocialDestinatario }}</td>
                    <td><span class="etiqueta">Identificación:</span> {{ $destinatario->identificacionDestinatario }}</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="etiqueta">Dirección:</span> {{ $destinatario->dirDestinatario }}</td>
                </tr>
                <tr>
                    <td colspan="2"><span class="etiqueta">Motivo del traslado:</span> {{ $destinatario->motivoTraslado }}</td>
                </tr>
                @if ($destinatario->ruta)
                    <tr><td colspan="2"><span class="etiqueta">Ruta:</span> {{ $destinatario->ruta }}</td></tr>
                @endif
            </table>

            <table class="tabla mt">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descripción</th>
                        <th class="num">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($destinatario->detalles as $detalle)
                        <tr>
                            <td>{{ $detalle->codigoInterno }}</td>
                            <td>{{ $detalle->descripcion }}</td>
                            <td class="num">{{ $detalle->cantidad }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endsection
