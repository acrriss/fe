<?php

namespace App\Http\Controllers;

use App\ApiSRI\ApiOfflineSRI;
use App\User;
use App\Comprobante;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ApiOfflineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $factura = (array)$request->post();
        $sri = new ApiOfflineSRI($factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['ambiente']);


        $claveacceso = $sri->claveDeAcceso(str_replace('/','',$factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['fechaEmision']). $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['codDoc']. $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['ruc']. $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['ambiente'].
            $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['estab'].$factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['ptoEmi']. $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['secuencial']. "22568496".
            $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['tipoEmision']);
        $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['claveAcceso'] = $claveacceso;

        $filexml =$sri->createXML(array_keys($factura)[0],$factura[array_keys($factura)[0]]);
        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );

        $xml = public_path().'/xml/'.$filexml;
        $url = $factura['info']['p12'];
        $logo = $factura['info']['logo'];

        //$file = file_get_contents($url,true,stream_context_create($arrContextOptions));

        file_put_contents(public_path().'/p12/active.p12','');
        file_put_contents(public_path().'/p12/active.p12',base64_decode($url) );
        file_put_contents(public_path().'/img/logoride.png','' );
        file_put_contents(public_path().'/img/logoride.png',base64_decode($logo) );
        // $factura['info']['logo'] = asset('img/logoride.png');
        $firma = $sri->firmarComprobante(public_path().'/p12/active.p12',$factura['info']['clavep12'],$xml,$filexml);

        //base64 logo fix pdf file
        $path = public_path('/img/logoride.png');
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        $factura['info']['logo'] = 'data:image/' . $type . ';base64,' . base64_encode($data);


        $comprobante = file_get_contents($xml);
        sleep('3');
        $recibir = $sri->recibirWs($comprobante);
        $rec = json_decode(json_encode($recibir),true);


        sleep('5');
        $auth = $sri->autorizarWs($claveacceso);

        if ($auth['isAutorizado'] == true){
            $factura['auth']['nauth'] = $auth['autorizaciones'][0]->numeroAutorizacion;
            $factura['auth']['dauth'] = $auth['autorizaciones'][0]->fechaAutorizacion;
            @$rideurl = $sri->createRide($claveacceso,$factura,$factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['ruc'],array_keys($factura)[0]);
            if($factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['ambiente']=='2'){

                if (array_keys($factura)[0] == 'comprobanteRetencion') {
                    $iduser = (new User())->createUser($factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['razonSocialSujetoRetenido'],$factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['identificacionSujetoRetenido'],$factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['razonSocialSujetoRetenido']);

                }else{
                    $iduser = (new User())->createUser($factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['razonSocialComprador'],$factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['identificacionComprador'],$factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['razonSocialComprador']);

                }
                $compp =new Comprobante();
                $ccomp = $compp->where('ride',$rideurl)->count();
                if ($ccomp > 0){

                }else{
                    $compro = new Comprobante();
                    $miTipo = '';
                    switch (array_keys($factura)[0]) {
                        case 'factura':
                            $miTipo = 'Factura';
                            break;
                        case 'notaCredito':
                            $miTipo = 'Nota de Crédito';
                            break;
                        case 'comprobanteRetencion':
                            $miTipo = 'Retención';
                            break;
                        default:
                            $miTipo =array_keys($factura)[0];
                            break;
                    }
                    $compro->tipo = $miTipo;
                    $compro->ruc = $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['ruc'];
                    $compro->razonSocial = $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[0]]['razonSocial'];
                    $compro->dcompra = Carbon::createFromFormat('d/m/Y',trim($factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['fechaEmision']))->toDateTimeString();
                    if (array_keys($factura)[0] !== 'factura') {
                    }else{

                        $compro->importeTotal = $factura[array_keys($factura)[0]][array_keys($factura[array_keys($factura)[0]])[1]]['importeTotal'];

                    }                $compro->xml = asset('xml/'.$filexml);
                    $compro->ride = $rideurl;
                    $compro->iduser_fk = $iduser;
                    $compro->save();
                }

            }
            $ridde = file_get_contents($rideurl,true,stream_context_create($arrContextOptions));
            $auth['ride']= base64_encode($ridde);
            $factura['respuestas']=array(
                'firma'=>$firma,
                'receptor'=>$recibir,
                'autenticador'=>$auth,


            );

            //TODO: remove xml and pdf files
            unlink($xml);
            unlink($rideurl);
        }else{
            $factura['respuestas']=array(
                'firma'=>$firma,
                'receptor'=>$recibir,
                'autenticador'=>$auth,

            );
        }


        return \Response::json($factura);


    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
