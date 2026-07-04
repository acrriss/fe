
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
   $factura = $factura;
   $nfact = $factura['infoTributaria']['estab'].'-'.$factura['infoTributaria']['ptoEmi'].'-'.$factura['infoTributaria']['secuencial'];

@endphp
<div class="p-0">
    <div class="row m-0 " style="height: 420px;">
        <div class="col-6" style="float: left;">
            <div class="logo" style="height: 50%">
                @if($info['logo'] == null)
    <h4>{{$factura['infoTributaria']['nombreComercial']}}</h4>

                @else
                    <div style="height: 190px; max-width: 470px">
                        <img style="height: 190px; max-width: 470px" src="{{$info['logo']}}" alt="{{$factura['infoTributaria']['nombreComercial']}}">
                    </div>
                @endif
            </div>
                    <div style="border: black 2px solid;border-radius: 5px;height: 50%;">
                        <div class="p-2">
                             @if(strlen($factura['infoTributaria']['razonSocial']) <= 25)
                    <h5>{{$factura['infoTributaria']['razonSocial']}}</h5>
                    @else
                    <h6 style="font-size=14px">{{$factura['infoTributaria']['razonSocial']}}</h6>
                    @endif
                    @if(strlen($factura['infoTributaria']['nombreComercial']) <= 25)
                    <h5>{{$factura['infoTributaria']['nombreComercial']}}</h5>
                    @else
                    <h6 style="font-size=14px">{{$factura['nombreComercial']['nombreComercial']}}</h6>
                    @endif
                            <div class="row">
                               <div class="col-3 text-bold float-left" >
                                   Dirección Matriz:
                               </div>
                                <div class="col-9 text-bold float-right">
                                    {{strtoupper($factura['infoTributaria']['dirMatriz'])}}
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
                    <h5>R.U.C.: {{$factura['infoTributaria']['ruc']}}</h5>
                    <h3 class="text-bold">FACTURA: <br>No: {{$nfact}}</h3>
                    <h5>NUMERO DE AUTORIZACIÓN: </h5>{{$auth['nauth']}}<br>
                    <h5>FECHA Y HORA DE AUTORIZACIÓN: <br>{{$auth['dauth']}}</h5>
                    <h5>AMBIENTE: {{$factura['infoTributaria']['ambiente'] == '1' ? 'PRUEBAS' : 'PRODUCCION'}}</h5>
                    <h5>TIPO EMISIÓN: {{$factura['infoTributaria']['tipoEmision'] == '1' ? 'EMISIÓN NORMAL' : 'EMISION POR INDISPONIBILIDAD DEL SISTEMA'}}</h5>
                    <span>CLAVE DE ACCESO <br><img src="data:image/png;base64, {{\Milon\Barcode\DNS1D::getBarcodePNG($factura['infoTributaria']['claveAcceso'], "C39")}}" width="100%" height="50px" alt="barcode"   /><br>{{$factura['infoTributaria']['claveAcceso']}}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="row pl-3 pr-3 mt-3" >
        <div class="col-12" >
            <div style="border: black 2px solid;border-radius: 5px;height: 100px">
                <div class="row p-1">
                    <div class="col-6 float-left">
                        <span><b>Razón Social/Nombres y Apellidos:</b> <br>{{$factura['infoFactura']['razonSocialComprador']}}</span><br>
                        <span><b>Fecha Emisión:</b> <br>{{$factura['infoFactura']['fechaEmision']}}</span>
                    </div>
                    <div class="col-6 float-right">
                        <span><b>Identificación:</b> {{$factura['infoFactura']['identificacionComprador']}}</span><br>
                        <span ><b>Guia de Remisión:</b> </span>

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
                    <th style="width: 10%">Código Principal</th>
                    <th style="width: 10%">Código Auxiliar</th>
                    <th style="width: 5%">Cant.</th>
                    <th style="width: 40%">Descripción</th>
                    <th style="width: 5%">Detalle Adicional</th>
                    <th style="width: 10%">Precio Unitario</th>
                    <th style="width: 10%">Desc.</th>
                    <th style="width: 10%">Subtotal</th>
                </tr>
                </thead>
                <tbody>
                @forelse($factura['detalles']['detalle'] as $producto)
                    <tr>
                        <td>{{$producto['codigoPrincipal']}}</td>
                        <td></td>
                        <td>{{$producto['cantidad']}}</td>
                        <td style="text-align: left">{{$producto['descripcion']}}</td>
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
    <div class="row pl-3 pr-3  mt-3" >
        <div class="col-7 float-left">
            <div style="border: black 2px solid;border-radius: 5px">
                <h5>Información Adicional</h5>
                <h6>Email: {{$info['email']}}</h6>
                <h6>Celular: {{$info['celular']}}</h6>
                <h6>Forma de pago: Otros con utilización del sistema financiero</h6>
            </div>
        </div>
        <div class="col-5 float-right">
            <table class="bordeado text-bold td-iz" style="width: 100%;">
                <tbody>
                <tr>
                    <td class="td-iz">SUBTOTAL 12%</td>
                    <td class="td-de">@foreach($factura['infoFactura']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='2') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL 0%</td>
                    <td class="td-de">@foreach($factura['infoFactura']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='0') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL NO OBJETO IVA</td>
                    <td class="td-de">@foreach($factura['infoFactura']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='6') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL EXENTO IVA</td>
                    <td class="td-de">@foreach($factura['infoFactura']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='7') {{$imp['baseImponible']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">SUBTOTAL SIN IMPUESTOS</td>
                    <td class="td-de">{{$factura['infoFactura']['totalSinImpuestos']}}</td>
                </tr>
                <tr>
                    <td class="td-iz">TOTAL DESCUENTO</td>
                    <td class="td-de">{{$factura['infoFactura']['totalDescuento']}}</td>
                </tr>
                <tr>
                    <td class="td-iz">ICE</td>
                    <td class="td-de">0.00</td>
                </tr>
                <tr>
                    <td class="td-iz">IVA 12%</td>
                    <td class="td-de">@foreach($factura['infoFactura']['totalConImpuestos']['totalImpuesto'] as $imp) @if($imp['codigo']=='2') {{$imp['valor']}} @else 0.00 @endif @endforeach</td>
                </tr>
                <tr>
                    <td class="td-iz">IRBPNR</td>
                    <td class="td-de">0.00</td>
                </tr>
                <tr>
                    <td class="td-iz">VALOR TOTAL</td>
                    <td class="td-de">{{$factura['infoFactura']['importeTotal']}}</td>
                </tr>
                </tbody>
            </table>
        </div>

    </div>
</div>

