<?php
function claveDeAcceso($cadena)
{
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
    if ($verificador==10){
        $verificador=1;
    }
    return $caa.$verificador;
}
