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
    $notaDebito = $notaDebito;
    $nfact = $notaDebito['infoTributaria']['estab'].'-'.$notaDebito['infoTributaria']['ptoEmi'].'-'.$notaDebito['infoTributaria']['secuencial'];
@endphp
<div class="p-0">
    <div class="row m-0" style="height: 420px;">
        <div class="col-6" style="float: left;">
            <div class="logo" style="height: 50%">
                @if($info['logo'] == null)
                    <h4>{{$notaDebito['infoTributaria']['nombreComercial']}}</h4>

                @else
                    <div style="height: 175px; max-width: 470px">
                        <img style="height: 175px; max-width: 470px" src="{{$info['logo']}}" alt="{{$factura['infoTributaria']['nombreComercial']}}">
                    </div>
                @endif
            </div>
            <div style="border: black 2px solid;border-radius: 5px;height: 180px;">
                <div class="p-2">
                    @if(strlen($notaDebito['infoTributaria']['razonSocial']) <= 25)
                        <h5>{{$notaDebito['infoTributaria']['razonSocial']}}</h5>
                    @else
                        <h6 style="font-size=14px">{{$notaDebito['infoTributaria']['razonSocial']}}</h6>
                    @endif
                    @if(strlen($notaDebito['infoTributaria']['nombreComercial']) <= 25)
                        <h5>{{$notaDebito['infoTributaria']['nombreComercial']}}</h5>
                    @else
                        <h6 style="font-size=14px">{{$notaDebito['nombreComercial']['nombreComercial']}}</h6>
                    @endif
                    <div class="row">
                        <div class="col-3 text-bold float-left" >
                            Dirección Matriz:
                        </div>
                        <div class="col-9 text-bold float-right">
                            {{strtoupper($notaDebito['infoTributaria']['dirMatriz'])}}
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
                    <h5>R.U.C.: {{$notaDebito['infoTributaria']['ruc']}}</h5>
                    <h3 class="text-bold">NOTA DE DEBITO: <br>No: {{$nfact}}</h3>
                    <h5>NUMERO DE AUTORIZACIÓN: </h5><br>{{$auth['nauth']}}<br>
                    <h5>FECHA Y HORA DE AUTORIZACIÓN: <br>{{$auth['dauth']}}</h5>
                    <h5>AMBIENTE: {{$notaDebito['infoTributaria']['ambiente'] == '1' ? 'PRUEBAS' : 'PRODUCCION'}}</h5>
                    <h5>TIPO EMISIÓN: {{$notaDebito['infoTributaria']['tipoEmision'] == '1' ? 'EMISION NORMAL' : 'EMISION POR INDISPONIBILIDAD DEL SISTEMA'}}</h5>
                    <span>CLAVE DE ACCESO <br><img src="data:image/png;base64, {{\Milon\Barcode\DNS1D::getBarcodePNG($notaDebito['infoTributaria']['claveAcceso'], "C39")}}" width="100%" height="50px" alt="barcode"   /><br>{{$notaDebito['infoTributaria']['claveAcceso']}}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="row pl-3 pr-3" >
        <div class="col-12" >
            <div style="border: black 2px solid;border-radius: 5px;height: 180px">
                <div class="row p-1">
                    <div class="col-6 float-left">
                        <span><b>Razón Social / Nombres y Apellidos:</b> <br>{{$notaDebito['infoNotaDebito']['razonSocialComprador']}}</span><br>
                        <span><b>Fecha Emisión:</b> <br>{{$notaDebito['infoNotaDebito']['fechaEmision']}}</span><br>
                        <span><b>Comprobante que se Modifica:</b> </span><br>
                        <span><b>Fecha Emisión (comprobante a Modificar):</b> </span><br>
                        <span><b>Razon de Modificación:</b> </span>
                    </div>
                    <div class="col-6 float-right">
                        <span><b>Identificación:</b> {{$notaDebito['infoNotaDebito']['identificacionComprador']}}</span><br>
                        <span></span><br>
                        <span></span><br>
                        <span></span><br>
                        <span>{{$notaDebito['infoNotaDebito']['codDocModificado']}} {{$notaDebito['infoNotaDebito']['numDocModificado']}}</span><br>
                        <span>{{$notaDebito['infoNotaDebito']['fechaEmisionDocSustento']}}</span><br>
                        <span>{{$notaDebito['infoNotaDebito']['motivo']}}</span>

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
                    <th style="width: 50%">Razon de la Modificacion</th>
                    <th style="width: 50%">Valor de la Modificacion</th>
                </tr>
                </thead>
                <tbody>
                @forelse($notaDebito['motivos']['motivo'] as $producto)
                    <tr >
                        <td>{{$producto['razon']}}</td>
                        <td>{{$producto['valor']}}</td>
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
            <table class="bordeado text-bold td-iz" style="width: 100%;">
                <tbody>
                <tr>
                    <td class="td-iz">SUBTOTAL 12%</td>
                    <td class="td-de">@foreach($notaDebito['infoNotaDebito']['impuestos']['impuesto'] as $imp) @if($imp['codigo']=='2') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL 0%</td>
                    <td class="td-de">@foreach($notaDebito['infoNotaDebito']['impuestos']['impuesto'] as $imp) @if($imp['codigo']=='0') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL NO OBJETO IVA</td>
                    <td class="td-de">@foreach($notaDebito['infoNotaDebito']['impuestos']['impuesto'] as $imp) @if($imp['codigo']=='6') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL EXENTO IVA</td>
                    <td class="td-de">@foreach($notaDebito['infoNotaDebito']['impuestos']['impuesto'] as $imp) @if($imp['codigo']=='7') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL SIN IMPUESTOS</td>
                    <td class="td-de">{{$notaDebito['infoNotaDebito']['totalSinImpuestos']}}</td>
                </tr>
                <tr>
                    <td class="td-iz">TOTAL DESCUENTO</td>
                    <td class="td-de">0</td>
                </tr>
                <tr>
                    <td class="td-iz">ICE</td>
                    <td class="td-de">0.00</td>
                </tr>
                <tr>
                    <td class="td-iz">IVA 12%</td>
                    <td class="td-de">@foreach($notaDebito['infoNotaDebito']['impuestos']['impuesto'] as $imp) @if($imp['codigo']=='2') {{$imp['valor']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">IRBPNR</td>
                    <td class="td-de">0.00</td>
                </tr>
                <tr>
                    <td class="td-iz">VALOR TOTAL</td>
                    <td class="td-de">{{$notaDebito['infoNotaDebito']['valorTotal']}}</td>
                </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

