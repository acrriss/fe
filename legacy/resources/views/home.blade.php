@extends('adminlte::page')

@section('title', 'Inicio')

@section('content_header')
    <!--h1>
        Inicio
        <small>Preview page</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Widgets</li>
    </ol-->
@stop

@section('content')
    <div class="row">
        <div class="col-lg-6 col-xs-6">
            <!-- small box -->
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3>{{(new App\Comprobante())->where(['tipo'=>'Factura','iduser_fk'=>auth()->user()->id])->count()}}</h3>

                    <p>Facturas Recibidas</p>
                </div>
                <div class="icon">
                    <i class="fa fa-shopping-cart"></i>
                </div>
                <a href="{{route('ver_facturas')}}?search=Factura" class="small-box-footer">
                    Ver Facturas <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>{{(new App\Comprobante())->where(['tipo'=>'Nota de Crédito','iduser_fk'=>auth()->user()->id])->count()}}</h3>

                    <p>Notas de Credito</p>
                </div>
                <div class="icon">
                    <i class="fa fa-files-o"></i>
                </div>
                <a href="{{route('ver_facturas')}}?search=Nota de Crédito" class="small-box-footer">
                    Ver Notas de Credito <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <div class="col-lg-6 col-xs-6">
            <!-- Widget: user widget style 1 -->
            <div class="box box-widget widget-user">
                <!-- Add the bg color to the header using any of the bg-* classes -->
                <div class="widget-user-header bg-aqua-active">
                    <h3 class="widget-user-username">{{ucwords(auth()->user()->name)}}</h3>
                    <h5 class="widget-user-desc">{{auth()->user()->email}}</h5>
                </div>
                <div class="widget-user-image">
                    <img class="img-circle" style="border: 0" src="{{asset('img/user.png')}}" alt="User Avatar">
                </div>
                <div class="box-footer">
                    <div class="row">
                        <div class="col-sm-6 border-right">
                            <div class="description-block">
                                <h5 class="description-header">{{(new App\Comprobante())->where(['tipo'=>'Factura','iduser_fk'=>auth()->user()->id])->count()}}</h5>
                                <span class="description-text">Facturas</span>
                            </div>
                            <!-- /.description-block -->
                        </div>
                        <!-- /.col -->
                        <div class="col-sm-6 border-right">
                            <div class="description-block">
                                <h5 class="description-header">{{(new App\Comprobante())->where(['tipo'=>'Nota de Crédito','iduser_fk'=>auth()->user()->id])->count()}}</h5>
                                <span class="description-text">Notas de Credito</span>
                            </div>
                            <!-- /.description-block -->
                        </div>

                    </div>
                    <!-- /.row -->
                </div>
            </div>
            <!-- /.widget-user -->
        </div>
        </div>
@stop
