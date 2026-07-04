@extends('adminlte::page')

@section('title', 'Ver Comprobantes')

@section('content_header')
    <h1>
        Ver comprobantes
        <small>Inicio</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{route('home')}}"><i class="fa fa-dashboard"></i> Inicio</a></li>
        <li class="active">Ver Comprobantes</li>
    </ol>
@stop

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Tabla de Comprobantes</h3>
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <table id="vercomp" class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <th>Tipo Comprobante</th>
                            <th>Razón Social</th>
                            <th>RUC</th>
                            <th>Fecha de comprobante</th>
                            <th>Valor total</th>
                            <th>Opciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        @php

                        @endphp
                        @forelse($compros as $cp)
                        <tr>
                            <td>{{$cp->tipo}}</td>
                            <td>{{$cp->razonSocial}}</td>
                            <td>{{$cp->ruc}}</td>
                            <td>{{$cp->dcompra}}</td>
                            <td>$ {{$cp->importeTotal}}</td>
                            <td>
                                <!--button class="btn-ver btn-sm btn btn-warning"><i class="fa fa-search"></i></button-->
                                <a href="{{'xml/"'.base64_encode($cp->xml).'"'}}" class="btn-xml btn-sm btn btn-info"><i class="fa fa-download"></i>XML</a>
                                <a href="{{'ride/"'.base64_encode($cp->ride).'"'}}" class="btn-ride btn-sm btn btn-primary"><i class="fa fa-download"></i>RIDE</a>
                            </td>
                        </tr>
                            @empty
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr>
                            <th>Tipo Comprobante</th>
                            <th>Razón Social</th>
                            <th>RUC</th>
                            <th>Fecha de comprobante</th>
                            <th>Valor total</th>
                            <th>Opciones</th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <!-- /.box-body -->
            </div>
        </div>
    </div>
@stop
@section('js')
    <script>
        $(document).ready(function(){
var t = $('#vercomp').DataTable();
            @if(request()->has('search'))
                t.search('{{urldecode(request('search'))}}').draw();
            @endif        });
    </script>
@stop
