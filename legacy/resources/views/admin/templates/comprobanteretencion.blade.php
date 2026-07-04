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
    $comprobanteRetencion = $comprobanteRetencion;
    $nfact = $comprobanteRetencion['infoTributaria']['estab'].'-'.$comprobanteRetencion['infoTributaria']['ptoEmi'].'-'.$comprobanteRetencion['infoTributaria']['secuencial'];
@endphp
<div class="p-0">
    <div class="row m-0 " style="height: 420px;">
        <div class="col-6 " style="float: left;">
            <div class="logo" style="height: 50%">
                @if($info['logo'] == null)
                    <h4>{{$comprobanteRetencion['infoTributaria']['nombreComercial']}}</h4>

                @else
                    <div style="height: 175px; max-width: 470px">
                        <img style="height: 175px; max-width: 470px" src="{{$info['logo']}}" alt="{{$factura['infoTributaria']['nombreComercial']}}">
                    </div>
                @endif
            </div>
            <div style="border: black 2px solid;border-radius: 5px;height: 180px;">
                <div class="p-2 mt-2">
                    @if(strlen($comprobanteRetencion['infoTributaria']['razonSocial']) <= 25)
                    <h5>{{$comprobanteRetencion['infoTributaria']['razonSocial']}}</h5>
                    @else
                    <h6 style="font-size=14px">{{$comprobanteRetencion['infoTributaria']['razonSocial']}}</h6>
                    @endif
                    @if(strlen($comprobanteRetencion['infoTributaria']['nombreComercial']) <= 25)
                    <h5>{{$comprobanteRetencion['infoTributaria']['nombreComercial']}}</h5>
                    @else
                    <h6 style="font-size=14px">{{$comprobanteRetencion['nombreComercial']['nombreComercial']}}</h6>
                    @endif
                    <div class="row">
                        <div class="col-3 text-bold float-left" >
                            Dirección Matriz:
                        </div>
                        <div class="col-9 text-bold float-right">
                            {{strtoupper($comprobanteRetencion['infoTributaria']['dirMatriz'])}}
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
            <div class="p-2" style="border: black 2px solid;border-radius: 5px;height: 420px;">
                <div class="mr-2">
                    <h5>R.U.C.: {{$comprobanteRetencion['infoTributaria']['ruc']}}</h5>
                    <h3 class="text-bold">COMPROBANTE RETENCION: <br>No: {{$nfact}}</h3>
                    <h5>NUMERO DE AUTORIZACIÓN: </h5><br>{{$auth['nauth']}}<br>
                    <h5>FECHA Y HORA DE AUTORIZACIÓN: <br>{{$auth['dauth']}}</h5>
                    <h5>AMBIENTE: {{$comprobanteRetencion['infoTributaria']['ambiente'] == '1' ? 'PRUEBAS' : 'PRODUCCION'}}</h5>
                    <h5>TIPO EMISIÓN: {{$comprobanteRetencion['infoTributaria']['tipoEmision'] == '1' ? 'EMISION NORMAL' : 'EMISION POR INDISPONIBILIDAD DEL SISTEMA'}}</h5>
                    <span>CLAVE DE ACCESO <br><img src="data:image/png;base64, {{\Milon\Barcode\DNS1D::getBarcodePNG($comprobanteRetencion['infoTributaria']['claveAcceso'], "C39")}}" width="100%" height="50px" alt="barcode"   /><br>{{$comprobanteRetencion['infoTributaria']['claveAcceso']}}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="row pl-3 pr-3 mt-3" >
        <div class="col-12" >
            <div style="border: black 2px solid;border-radius: 5px;height: 120px">
                <div class="row p-1">
                    <div class="col-6 float-left">
                        <span><b>Razón Social / Nombres y Apellidos:</b> <br>{{$comprobanteRetencion['infoCompRetencion']['razonSocialSujetoRetenido']}}</span><br>
                        <span><b>Fecha Emisión:</b> <br>{{$comprobanteRetencion['infoCompRetencion']['fechaEmision']}}</span>
                    </div>
                    <div class="col-6 float-right">
                        <span><b>Identificación:</b> {{$comprobanteRetencion['infoCompRetencion']['identificacionSujetoRetenido']}}</span><br>


                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="row pl-3 pr-3 mt-3" >
        <div class="col-12">
            <table class="bordeado prod">
                <thead>
                <tr>
                    <th style="width: 10%">Comprobante</th>
                    <th style="width: 10%">Número</th>
                    <th style="width: 5%">Fecha Emisión</th>
                    <th style="width: 40%">Ejercicio Fiscal</th>
                    <th style="width: 10%">B.I. para Retención</th>
                    <th style="width: 5%">Impuesto</th>
                    <th style="width: 5%">% Retención</th>
                    <th style="width: 10%">Valor Retenido</th>
                </tr>
                </thead>
                <tbody>
                @forelse($comprobanteRetencion['impuestos']['impuesto'] as $producto)
                    <tr>
                        <td>{{$producto['codDocSustento']}}</td>
                        <td>{{$producto['numDocSustento']}}</td>
                        <td>{{$producto['fechaEmisionDocSustento']}}</td>
                        <td>{{$comprobanteRetencion['infoCompRetencion']['periodoFiscal']}}</td>
                        <td>{{$producto['baseImponible']}}</td>
                        <td>{{$producto['codigo']}}</td>
                        <td>{{$producto['porcentajeRetener']}}</td>
                        <td>{{$producto['valorRetenido']}}</td>
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


    </div>
</div>

