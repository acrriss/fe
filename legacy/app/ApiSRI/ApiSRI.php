<?php
namespace App\ApiSRI;


use Barryvdh\Snappy\Facades\SnappyPdf;
use Knp\Snappy\Pdf;
use Spatie\ArrayToXml\ArrayToXml;


class ApiSRI {

    public $urlrec = '';
    public $urlauth = '';
    public $jarpath = 'sri.jar';
    public $xmlpath = 'xml';
    public function enviarmail(){

    }
    public function createRide($clavedeacceso,$data,$ruc,$tipo){
        switch ($tipo){
            case 'factura':
                $pdf = SnappyPdf::loadView('admin.templates.ride', $data)->setOption('encoding', 'UTF-8')->setOption('margin-left', 0)->setOption('margin-right', 0);
                $pdf->save(public_path().'/pdf/'.$clavedeacceso.'_'.$ruc.'.pdf',true);
                break;
            case 'notaCredito':
                $pdf = SnappyPdf::loadView('admin.templates.notacredito', $data)->setOption('encoding', 'UTF-8')->setOption('margin-left', 0)->setOption('margin-right', 0);
                $pdf->save(public_path().'/pdf/'.$clavedeacceso.'_'.$ruc.'.pdf',true);

                break;
            case 'comprobanteRetencion':
                $pdf = SnappyPdf::loadView('admin.templates.comprobanteretencion', $data)->setOption('margin-left', 0)->setOption('encoding', 'UTF-8')->setOption('margin-right', 0);
                $pdf->save(public_path().'/pdf/'.$clavedeacceso.'_'.$ruc.'.pdf',true);

                break;
            case 'guiaRemision':
                $pdf = SnappyPdf::loadView('admin.templates.guiaRemision', $data)->setOption('margin-left', 0)->setOption('encoding', 'UTF-8')->setOption('margin-right', 0);
                $pdf->save(public_path().'/pdf/'.$clavedeacceso.'_'.$ruc.'.pdf',true);

                break;
            case 'notaDebito':
                $pdf = SnappyPdf::loadView('admin.templates.notadebito', $data)->setOption('margin-left', 0)->setOption('encoding', 'UTF-8')->setOption('margin-right', 0);
                $pdf->save(public_path().'/pdf/'.$clavedeacceso.'_'.$ruc.'.pdf',true);

                break;
        }
        return public_path('pdf/'.$clavedeacceso.'_'.$ruc.'.pdf');
    }
    public function __construct($ambiente = '1')
    {
        // api not working
        switch ($ambiente){
            case 1:
                $this->urlrec = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantes?wsdl';
                $this->urlauth = 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantes?wsdl';
                break;
            case 2:
                $this->urlrec = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantes?wsdl';
                $this->urlauth = 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantes?wsdl';
                break;
        }
    }
    public function createXML($tipo,$data) {

        $file_name = str_replace(' ', '_',$data['infoTributaria']['claveAcceso'].'.xml');
        //$xmlDoc->save(public_path()."/xml/" . $file_name);
        //return xml file name
        if ($tipo == 'comprobanteRetencion'){
            $version = '1.0.0';
        }else{
            $version = '1.1.0';
        }

        $artxml = new ArrayToXml($data, [
            'rootElementName' => $tipo,
            '_attributes' => [
                'id' => 'comprobante',
                'version' => $version,
            ]
        ],true,'UTF-8');
        //$result = $artxml->convert();
        $dom = $artxml->toDom();
        $dom->formatOutput = true;
        $dom->save(public_path()."/xml/" . $file_name);

        return $file_name;
    }
    public function claveDeAcceso($cadena)
    {
        //cadena debe tener
        //$claveacceso = claveDeAcceso($infoDoc['fechaEmision']. $infoTrib['codDoc']. $infoTrib['ruc']. $infoTrib['ambiente'].
        //    $infoTrib['estab'].$infoTrib['ptoEmi']. $infoTrib['secuencial']. "22568496".
        //    $infoTrib['tipoEmision']);
        $cadena = trim($cadena);
        $caa= $cadena;
        $baseMultiplicador=7;
        $aux=new \SplFixedArray(strlen($cadena));
        $aux=$aux->toArray();
        $multiplicador = 2;
        $total=0;
        $verificador=0;

        for($i = count($aux)-1;$i >=0; --$i){
            $aux[$i]=substr($cadena,$i,1);
            //echo $aux[$i];
            @$aux[$i]*=$multiplicador;
            ++$multiplicador;
            if ($multiplicador>$baseMultiplicador){
                $multiplicador =2;
            }
            $total+=$aux[$i];
        }

        if (($total==0)||($total==1))$verificador=0;else{
            $verificador=(11-($total%11)==11)?0:11-($total%11);
        }
        //echo $total%11;
        if ($verificador==10){
            $verificador=1;
        }
        return $caa.$verificador;
    }
    public function autorizarWs($claveAcceso){
        $params = array("claveAccesoComprobante"=>$claveAcceso);
        $client = new \SoapClient($this->urlauth);
        $result=$client->autorizacionComprobante($params);
        if ($result){
            if ($result->RespuestaAutorizacionComprobante){
                $result->isAutorizado = false;
                if ($result->RespuestaAutorizacionComprobante->autorizaciones){
                    if (isset($result->RespuestaAutorizacionComprobante->autorizaciones->autorizacion)){
                        $autorizaciones = $result->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;
                        $result->RespuestaAutorizacionComprobante->autorizaciones->autorizacion = array();
                        if (is_array($autorizaciones)){
                            $result->RespuestaAutorizacionComprobante->autorizaciones=$autorizaciones;
                        }else{
                            $result->RespuestaAutorizacionComprobante->autorizaciones = array($autorizaciones);
                        }

                        $result->RespuestaAutorizacionComprobante->mensajesWs = array();
                        $result->RespuestaAutorizacionComprobante->mensajesDb = array();

                        $numeroComprobantes = $result->RespuestaAutorizacionComprobante->numeroComprobantes;
                        array_push($result->RespuestaAutorizacionComprobante->mensajesDb,"Numero de comprobantes enviados: {$numeroComprobantes}");
                        array_push($result->RespuestaAutorizacionComprobante->mensajesWs,"Numero de comprobantes enviados: {$numeroComprobantes}");

                        $result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviado = null;
                        $result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviadoFecha = null;

                        for($idxAutorizacion=0;$idxAutorizacion<count($result->RespuestaAutorizacionComprobante->autorizaciones);$idxAutorizacion++){
                            $autorizacion=$result->RespuestaAutorizacionComprobante->autorizaciones[$idxAutorizacion];
                            $autorizacion->fechaAutorizacion =date("Y-m-d H:i:s",strtotime($autorizacion->fechaAutorizacion));

                            //EE: Convertir en array los mensajes
                            if ($autorizacion->mensajes){
                                if (isset($autorizacion->mensajes->mensaje)){
                                    $mensajes=$autorizacion->mensajes->mensaje;
                                    $autorizacion->mensajes =array();
                                    if (is_array($mensajes)){
                                        $autorizacion->mensajes =$mensajes;
                                    }else{
                                        $autorizacion->mensajes =array($mensajes);
                                    }
                                }

                                if (!is_array($autorizacion->mensajes))$autorizacion->mensajes=(array)$autorizacion->mensajes;

                                $autorizacion->mensajeDb = array();
                                $autorizacion->mensajeWs = array();

                                for($idxMensaje=0;$idxMensaje<count($autorizacion->mensajes);$idxMensaje++){
                                    $item=$autorizacion->mensajes[$idxMensaje];
                                    $noEnvio = $idxAutorizacion+1;
                                    $informacionAdicional =isset($item->informacionAdicional)?"\n".$item->informacionAdicional:"";
                                    $mensaje=$item->mensaje;
                                    $identificador=$item->identificador;
                                    $tipo=$item->tipo;
                                    $mensajesDb=trim("[{$autorizacion->fechaAutorizacion}]: ({$tipo} - {$identificador}) {$mensaje}{$informacionAdicional}");
                                    $mensajesWs=trim("[{$autorizacion->fechaAutorizacion}]: ({$tipo} - {$identificador}) {$mensaje}{$informacionAdicional}");
                                    @array_push($autorizacion->mensajesDb,$mensajesDb,trim("Envio {$noEnvio}$mensajesDb"));
                                    @array_push($autorizacion->mensajesWs,$mensajesWs,trim("Envio {$noEnvio}$mensajesWs"));
                                    $autorizacion->mensajes[$idxMensaje]=(array)$autorizacion->mensajes[$idxMensaje];
                                }
                            }
                            //EE: Ultimo envio
                            if (is_null($result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviado)){
                                $result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviado = (array)$autorizacion;
                                $result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviadoFecha =$autorizacion->fechaAutorizacion;

                            }else{
                                if ($autorizacion->fechaAutorizacion > $result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviadoFecha){
                                    $result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviado=(array)$autorizacion;
                                    $result->RespuestaAutorizacionComprobante->ultimoComprobanteEnviadoFecha=$autorizacion->fechaAutorizacion;
                                }
                            }
                            $isAutorizado=$autorizacion->estado == "AUTORIZADO"&&!$result->isAutorizado?true:false;

                            if ($isAutorizado){
                                $result->isAutorizado = true;

                                //$result->RespuestaAutorizacionComprobante->comprobanteAutorizado=$this->obtenerComprobanteAutorizado($autorizacion);
                                //return $result;
                                $result->RespuestaAutorizacionComprobante->numeroAutorizacion =$autorizacion->numeroAutorizacion;
                            }

                            $result->RespuestaAutorizacionComprobante->autorizaciones[$idxAutorizacion]=$result->RespuestaAutorizacionComprobante->autorizaciones[$idxAutorizacion];
                        }

                    }
                }
                $isAutorizado=$result->isAutorizado;
                $result=(array)$result->RespuestaAutorizacionComprobante;
                $result["isAutorizado"]=$isAutorizado;
            }
        }
        return $result;
    }
    public function firmarComprobante($cert,$passCert,$xml,$outxml){

        $xmlpath = realpath(public_path().'/'.$this->xmlpath);
        exec("java -jar ".public_path().'/'.$this->jarpath." $cert $passCert $xml $xmlpath $outxml",$ou,$ret);

        return array(
            'ruta'=>$xmlpath.'/'.$outxml,
            'out'=>$ou,
            'ret'=>$ret
        );

    }
    public function recibirWs($comprobante)
    {
        $params = array("xml"=>$comprobante);
        $client = new \SoapClient($this->urlrec);
        $result = $client->validarComprobante($params);

        if($result){
            if ($result->RespuestaRecepcionComprobante){
                $result->isRecibida = $result->RespuestaRecepcionComprobante->estado==="RECIBIDA"?true:false;
                if ($result->RespuestaRecepcionComprobante->comprobantes){
                    if (isset($result->RespuestaRecepcionComprobante->comprobantes->comprobante)){
                        $comprobantes = $result->RespuestaRecepcionComprobante->comprobantes->comprobante;
                        $result->RespuestaRecepcionComprobante->comprobantes = array();
                        if (is_array($comprobantes)){
                            $result->RespuestaRecepcionComprobante->comprobantes = $comprobantes;
                        }else{
                            $result->RespuestaRecepcionComprobante->comprobantes[0]=$comprobantes;
                        }

                        $result->RespuestaRecepcionComprobante->mensajesWs = array();
                        $result->RespuestaRecepcionComprobante->mensajesDb = array();

                        for($idxComprobante=0;$idxComprobante<count($result->RespuestaRecepcionComprobante->comprobantes);$idxComprobante++){
                            $comprobante = $result->RespuestaRecepcionComprobante->comprobantes[$idxComprobante];
                            if ($comprobante->mensajes){
                                if (isset($comprobante->mensajes->mensaje)){
                                    $mensajes=$comprobantes->mensajes->mensaje;
                                    $comprobante->mensajes = array();
                                    if (is_array($mensajes)){
                                        $comprobante->mensajes = $mensajes;
                                    }else{
                                        $comprobante->mensaje[0]=$mensajes;
                                    }
                                }
                                for($idxMensaje = 0;$idxMensaje < count($comprobante->mensajes); $idxMensaje++){
                                    $item = $comprobante->mensajes[$idxMensaje];
                                    $informacionAdicional = isset($item->informacionAdicional)?"\n".$item->informacionAdicional:"";
                                    $mensaje = $item->mensaje;
                                    $identificador = $item->identificador;
                                    $tipo = $item->tipo;
                                    $mensajeDB=trim("({$tipo} - {$identificador}){$mensaje}{$informacionAdicional}");
                                    $mensajeWs = trim("({$tipo} - {$identificador}){$mensaje}{$informacionAdicional}");
                                    @array_push($result->RespuestaRecepcionComprobante->mensajesDB,$mensajeDB);
                                    @array_push($result->RespuestaRecepcionComprobante->mensajesWs,$mensajeWs);
                                    $comprobante->mensajes[$idxMensaje] = (array)$comprobante->mensajes[$idxMensaje];
                                }
                            }
                            $result->RespuestaRecepcionComprobante->comprobantes[$idxComprobante]=(array)$result->RespuestaRecepcionComprobante->comprobantes[$idxComprobante];
                        }
                    }

                    $isRecibida = $result->isRecibida;
                    $result = (array)$result->RespuestaRecepcionComprobante;
                    $result["isRecibida"]=$isRecibida;
                }
            }
        }
        return $result;
    }

}
