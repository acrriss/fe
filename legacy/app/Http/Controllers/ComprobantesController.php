<?php

namespace App\Http\Controllers;
use App\ApiSRI\ApiSRI;

use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Knp\Snappy\Pdf;
use App\Comprobante;

class ComprobantesController extends Controller
{
    public function ver_facturas(){
        $data = array();
        $compro = new Comprobante();
        $compros = $compro->where('iduser_fk',auth()->user()->id)->get();
        return view('admin.comprobantes',compact('data','compros'));
    }
}
