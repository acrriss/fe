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
    $notaCredito = $notaCredito;
    $nfact = $notaCredito['infoTributaria']['estab'].'-'.$notaCredito['infoTributaria']['ptoEmi'].'-'.$notaCredito['infoTributaria']['secuencial'];
@endphp
<div class="p-0">
    <div class="row m-0" style="height: 420px;">
        <div class="col-6" style="float: left;">
            <div class="logo" style="height: 50%">
                @if($info['logo'] == null)
                    <h4>{{$notaCredito['infoTributaria']['nombreComercial']}}</h4>

                @else
                    <div style="height: 175px; max-width: 470px">
                        <img style="height: 175px; max-width: 470px" src="{{$info['logo']}}" alt="{{$factura['infoTributaria']['nombreComercial']}}">
                    </div>
                @endif
            </div>
            <div style="border: black 2px solid;border-radius: 5px;height: 180px;">
                <div class="p-2">
                     @if(strlen($notaCredito['infoTributaria']['razonSocial']) <= 25)
                    <h5>{{$notaCredito['infoTributaria']['razonSocial']}}</h5>
                    @else
                    <h6 style="font-size=14px">{{$notaCredito['infoTributaria']['razonSocial']}}</h6>
                    @endif
                    @if(strlen($notaCredito['infoTributaria']['nombreComercial']) <= 25)
                    <h5>{{$notaCredito['infoTributaria']['nombreComercial']}}</h5>
                    @else
                    <h6 style="font-size=14px">{{$notaCredito['nombreComercial']['nombreComercial']}}</h6>
                    @endif
                    <div class="row">
                        <div class="col-3 text-bold float-left" >
                            Dirección Matriz:
                        </div>
                        <div class="col-9 text-bold float-right">
                            {{strtoupper($notaCredito['infoTributaria']['dirMatriz'])}}
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
                    <h5>R.U.C.: {{$notaCredito['infoTributaria']['ruc']}}</h5>
                    <h3 class="text-bold">NOTA DE CRÉDITO: <br>No: {{$nfact}}</h3>
                    <h5>NUMERO DE AUTORIZACIÓN: </h5><br>{{$auth['nauth']}}<br>
                    <h5>FECHA Y HORA DE AUTORIZACIÓN: <br>{{$auth['dauth']}}</h5>
                    <h5>AMBIENTE: {{$notaCredito['infoTributaria']['ambiente'] == '1' ? 'PRUEBAS' : 'PRODUCCION'}}</h5>
                    <h5>TIPO EMISIÓN: {{$notaCredito['infoTributaria']['tipoEmision'] == '1' ? 'EMISION NORMAL' : 'EMISION POR INDISPONIBILIDAD DEL SISTEMA'}}</h5>
                    <span>CLAVE DE ACCESO <br><img src="data:image/png;base64, {{\Milon\Barcode\DNS1D::getBarcodePNG($notaCredito['infoTributaria']['claveAcceso'], "C39")}}" width="100%" height="50px" alt="barcode"   /><br>{{$notaCredito['infoTributaria']['claveAcceso']}}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="row pl-3 pr-3 mt-3" >
        <div class="col-12" >
            <div style="border: black 2px solid;border-radius: 5px;height: 180px">
                <div class="row p-1">
                    <div class="col-6 float-left">
                        <span><b>Razón Social / Nombres y Apellidos:</b> <br>{{$notaCredito['infoNotaCredito']['razonSocialComprador']}}</span><br>
                        <span><b>Fecha Emisión:</b> <br>{{$notaCredito['infoNotaCredito']['fechaEmision']}}</span><br>
                         <span><b>Comprobante que se Modifica:</b> </span><br>
                        <span><b>Fecha Emisión (comprobante a Modificar):</b> </span><br>
                        <span><b>Razon de Modificación:</b> </span>
                    </div>
                    <div class="col-6 float-right">
                        <span><b>Identificación:</b> {{$notaCredito['infoNotaCredito']['identificacionComprador']}}</span><br>
                        <span></span><br>
                         <span></span><br>
                         <span></span><br>
<span>{{$notaCredito['infoNotaCredito']['codDocModificado']}} {{$notaCredito['infoNotaCredito']['numDocModificado']}}</span><br>
                        <span>{{$notaCredito['infoNotaCredito']['fechaEmisionDocSustento']}}</span><br>
                        <span>{{$notaCredito['infoNotaCredito']['motivo']}}</span>

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
                    <th style="width: 10%">Código</th>
                    <th style="width: 10%">Codigo Auxiliar</th>
                    <th style="width: 5%">Cant.</th>
                    <th style="width: 40%">Descripción</th>
                    <th style="width: 5%">Detalle Adicional</th>
                    <th style="width: 5%">Detalle Adicional</th>
                    <th style="width: 5%">Detalle Adicional</th>
                    <th style="width: 10%">Precio Unitario</th>
                    <th style="width: 10%">Desc.</th>
                    <th style="width: 10%">Subtotal</th>
                </tr>
                </thead>
                <tbody>
                @forelse($notaCredito['detalles']['detalle'] as $producto)
                    <tr >
                        <td>{{$producto['codigoInterno']}}</td>
                        <td></td>
                        <td>{{$producto['cantidad']}}</td>
                        <td style="text-align: left">{{$producto['descripcion']}}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>{{$producto['precioUnitario']}}</td>
                        <td>{{$producto['descuento']}}</td>
                        <td>{{$producto['precioTotalSinImpuesto']}}</td>
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
                    <td class="td-de">@foreach($notaCredito['infoNotaCredito']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='2') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL 0%</td>
                    <td class="td-de">@foreach($notaCredito['infoNotaCredito']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='0') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL NO OBJETO IVA</td>
                    <td class="td-de">@foreach($notaCredito['infoNotaCredito']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='6') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL EXENTO IVA</td>
                    <td class="td-de">@foreach($notaCredito['infoNotaCredito']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='7') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL SIN IMPUESTOS</td>
                    <td class="td-de">{{$notaCredito['infoNotaCredito']['totalSinImpuestos']}}</td>
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
                    <td class="td-de">@foreach($notaCredito['infoNotaCredito']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='2') {{$imp['valor']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">IRBPNR</td>
                    <td class="td-de">0.00</td>
                </tr>
                <tr>
                    <td class="td-iz">VALOR TOTAL</td>
                    <td class="td-de">{{$notaCredito['infoNotaCredito']['valorModificacion']}}</td>
                </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

