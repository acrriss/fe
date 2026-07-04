<!DOCTYPE html >
<style>
    .prod tr td{
        font-size:80% !important;
    }
    .page-break {
        page-break-after: always;
    }
    .text-bold{
        font-weight: bold
    }
    .bordeado table, th, td{
        border: black 2px solid;
        border-radius: 5px;
    }
    .bordeado td{
        text-align: center;
    }
    .td-iz{
        text-align: left !important;
    }
    .td-de{
        text-align: right !important;
    }
</style>
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
@php
    $info =$info;
    $guiaRemision = $guiaRemision;
    $nfact = $guiaRemision['infoTributaria']['estab'].'-'.$guiaRemision['infoTributaria']['ptoEmi'].'-'.$guiaRemision['infoTributaria']['secuencial'];
@endphp
<div class="p-0">
    <div class="row m-0" style="height: 420px;">
        <div class="col-6" style="float: left;">
            <div class="logo" style="height: 50%">
                @if($info['logo'] == null)
                    <h4>{{$guiaRemision['infoTributaria']['nombreComercial']}}</h4>

                @else
                    <div style="height: 175px; max-width: 470px">
                        <img style="height: 175px; max-width: 470px" src="{{$info['logo']}}" alt="{{$factura['infoTributaria']['nombreComercial']}}">
                    </div>
                @endif
            </div>
            <div style="border: black 2px solid;border-radius: 5px;height: 180px;">
                <div class="p-2">
                    @if(strlen($guiaRemision['infoTributaria']['razonSocial']) <= 25)
                        <h5>{{$guiaRemision['infoTributaria']['razonSocial']}}</h5>
                    @else
                        <h6 style="font-size=14px">{{$guiaRemision['infoTributaria']['razonSocial']}}</h6>
                    @endif
                    @if(strlen($guiaRemision['infoTributaria']['nombreComercial']) <= 25)
                        <h5>{{$guiaRemision['infoTributaria']['nombreComercial']}}</h5>
                    @else
                        <h6 style="font-size=14px">{{$guiaRemision['nombreComercial']['nombreComercial']}}</h6>
                    @endif
                    <div class="row">
                        <div class="col-3 text-bold float-left" >
                            Dirección Matriz:
                        </div>
                        <div class="col-9 text-bold float-right">
                            {{strtoupper($guiaRemision['infoTributaria']['dirMatriz'])}}
                        </div>
                    </div>
                    <div class="row" style="clear: both">
                        <div class="col-8 text-bold float-left" >
                            Contribuyente Especial No:
                        </div>
                        <div class="col-4 text-bold float-right">
                            {{strtoupper($info['especialn'])}}
                        </div>
                    </div>
                    <div class="row" style="clear: both">
                        <div class="col-8 text-bold float-left" >
                            Obligado a llevar Contabilidad:
                        </div>
                        <div class="col-4 text-bold float-right">
                            {{strtoupper($info['obligado'])}}
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-6" style="float: right">
            <div class="p-2" style="border: black 2px solid;border-radius: 5px;height: 100%;">
                <div class="mr-2">
                    <h5>R.U.C.: {{$guiaRemision['infoTributaria']['ruc']}}</h5>
                    <h3 class="text-bold">GUIA DE REMISION <br>No: {{$nfact}}</h3>
                    <h5>NUMERO DE AUTORIZACIÓN: </h5><br>{{$auth['nauth']}}<br>
                    <h5>FECHA Y HORA DE AUTORIZACIÓN: <br>{{$auth['dauth']}}</h5>
                    <h5>AMBIENTE: {{$guiaRemision['infoTributaria']['ambiente'] == '1' ? 'PRUEBAS' : 'PRODUCCION'}}</h5>
                    <h5>TIPO EMISIÓN: {{$guiaRemision['infoTributaria']['tipoEmision'] == '1' ? 'EMISION NORMAL' : 'EMISION POR INDISPONIBILIDAD DEL SISTEMA'}}</h5>
                    <span>CLAVE DE ACCESO <br><img src="data:image/png;base64, {{\Milon\Barcode\DNS1D::getBarcodePNG($guiaRemision['infoTributaria']['claveAcceso'], "C39")}}" width="100%" height="50px" alt="barcode"   /><br>{{$guiaRemision['infoTributaria']['claveAcceso']}}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="row pl-3 pr-3 mt-3" >
        <div class="col-12" >
            <div style="border: black 2px solid;border-radius: 5px;height: 180px">
                <div class="row p-1">
                    <div class="col-6">
                        <span><b>Identificacion (Transportista):</b> <br>{{$guiaRemision['infoGuiaRemision']['rucTransportista']}}</span><br>
                        <span><b>Razon Social/Nombres y Apellidos:</b> <br>{{$guiaRemision['infoGuiaRemision']['razonSocialTransportista']}}</span><br>
                        <span><b>Placa:</b> {{$guiaRemision['infoGuiaRemision']['placa']}}</span><br>
                        <span><b>Punto de Partida:</b> {{$guiaRemision['infoGuiaRemision']['dirPartida']}}</span><br>
                        <span><b>Fecha inicio Transporte:</b> {{$guiaRemision['infoGuiaRemision']['fechaIniTransporte']}} Fecha fin Transporte: {{$guiaRemision['infoGuiaRemision']['fechaFinTransporte']}}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @forelse($guiaRemision['destinatarios']['destinatario'] as $producto)
        <div class="row pl-3 pr-3 mt-3" >
            <div class="col-12" >
                <div style="border: black 2px solid;border-radius: 5px;height: 180px">
                    <div class="row p-1">
                        <div class="col-6">
                            <span>Comprobante de Venta {{$producto['codDocSustento']}} {{$producto['numDocSustento']}}</span><br>
                            <span>Numero de Autorizacion {{$producto['numAutDocSustento']}}</span><br>
                            <span>Motivo de Traslado: {{$producto['numAutDocSustento']}}</span><br>
                            <span>Identificacion (Destinatario) {{$producto['identificacionDestinatario']}}</span><br>
                            <span>Razon Social/Nombres Apellidos {{$producto['razonSocialDestinatario']}}</span>
                            <span>Documento Aduanero {{$producto['docAduaneroUnico']}}</span>
                            <span>Codigo Establecimiento Destino {{$producto['codEstabDestino']}}</span>
                            <span>Ruta {{$producto['ruta']}}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
    @endforelse
    <div class="row pl-3 pr-3 mt-3" >
        <div class="col-12">
            <table class="bordeado prod">
                <thead>
                <tr>
                    <th style="width: 20%">Cantidad</th>
                    <th style="width: 40%">Descripción</th>
                    <th style="width: 20%">Codigo Principal</th>
                    <th style="width: 20%">Codigo Auxiliar</th>
                </tr>
                </thead>
                <tbody>
                @forelse($guiaRemision['detalles']['detalle'] as $producto)
                    <tr >
                        <td>{{$producto['cantidad']}}</td>
                        <td style="text-align: left">{{$producto['descripcion']}}</td>
                        <td>{{$producto['codigoInterno']}}</td>
                        <td>{{$producto['codigoAdicional']}}</td>
                    </tr>
                @empty
                @endforelse
                </tbody>
            </table>
        </div>

    </div>
    <div class="row pl-3 pr-3 mt-3" >
        <div class="col-7 float-left">
            <div style="border: black 2px solid;border-radius: 5px">
                <h5>Información Adicional</h5>
                <h6>EMAIL: {{$info['email']}}</h6>
                <h6>CELULAR: {{$info['celular']}}</h6>
            </div>
        </div>
        <div class="col-5 float-right">
        </div>

    </div>
</div>

