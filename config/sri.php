<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web services del SRI (comprobantes electrónicos, esquema offline)
    |--------------------------------------------------------------------------
    */

    'wsdl' => [
        'pruebas' => [
            'recepcion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'autorizacion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
        'produccion' => [
            'recepcion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
            'autorizacion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Firmador XAdES-BES (jar heredado, invocado vía java)
    |--------------------------------------------------------------------------
    */

    'firmador' => [
        'jar' => env('SRI_FIRMADOR_JAR', storage_path('app/firmador/sri.jar')),
        'java' => env('SRI_JAVA_BIN', 'java'),
        'timeout' => env('SRI_FIRMADOR_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autorización: reintentos de consulta tras la recepción
    |--------------------------------------------------------------------------
    |
    | El SRI no autoriza instantáneamente; el legado dormía 5 s fijos. Aquí
    | se consulta hasta `intentos` veces esperando `espera_ms` entre cada una.
    |
    */

    'autorizacion' => [
        'intentos' => env('SRI_AUTORIZACION_INTENTOS', 5),
        'espera_ms' => env('SRI_AUTORIZACION_ESPERA_MS', 2000),
    ],
];
