<?php
// Implementación INDEPENDIENTE del módulo 11 según ficha técnica del SRI
// (pesos 2..7 de derecha a izquierda; 11-resto; 11→0, 10→1)
function mod11(string $c): int {
    $peso = 2; $total = 0;
    for ($i = strlen($c) - 1; $i >= 0; $i--) {
        $total += (int)$c[$i] * $peso;
        $peso = $peso === 7 ? 2 : $peso + 1;
    }
    $v = 11 - ($total % 11);
    return $v === 11 ? 0 : ($v === 10 ? 1 : $v);
}

require __DIR__.'/generate-lib.php'; // claveDeAcceso verbatim

$cadena = '071220220109225967880011001001000004303225684961';
printf("cadena:      %s (%d dígitos)\n", $cadena, strlen($cadena));
printf("legado:      %s\n", substr(claveDeAcceso($cadena), -1));
printf("independiente: %d\n", mod11($cadena));

// Buscar vectores que cubran los casos borde del verificador (10→1 y 11→0)
$base = '0712202201092259678800110010010000043032256849'; // sin secuencial-final ni tipoEmision
$vectors = []; $found = ['normal'=>false,'diez'=>false,'once'=>false];
for ($n = 0; $n < 2000; $n++) {
    $sec = str_pad((string)$n, 9, '0', STR_PAD_LEFT);
    $c = '0712202201'.'0922596788001'.'1'.'001'.'001'.$sec.'22568496'.'1';
    $peso=2;$total=0;
    for($i=strlen($c)-1;$i>=0;$i--){$total+=(int)$c[$i]*$peso;$peso=$peso===7?2:$peso+1;}
    $raw = 11 - ($total % 11);
    $tag = $raw===10?'diez':($raw===11?'once':'normal');
    if (!$found[$tag]) {
        $found[$tag] = true;
        $legacy = claveDeAcceso($c);
        $indep  = mod11($c);
        $vectors[] = ['cadena'=>$c,'verificadorLegado'=>(int)substr($legacy,-1),'verificadorIndependiente'=>$indep,'caso'=>"11-resto={$raw}",'coinciden'=>substr($legacy,-1)==(string)$indep];
    }
    if (!in_array(false,$found,true)) break;
}
file_put_contents(dirname(__DIR__,2).'/fixtures/golden/claveAcceso-vectors.json', json_encode($vectors, JSON_PRETTY_PRINT)."\n");
echo json_encode($vectors, JSON_PRETTY_PRINT)."\n";
