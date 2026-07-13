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
        // 'nativo' (XAdES-BES en PHP puro, default) o 'jar' (firmador Java
        // heredado, fallback de emergencia). El nativo está validado contra
        // el SRI real (AUTORIZADO en ambiente de pruebas, 2026-07-10).
        'driver' => env('SRI_FIRMADOR_DRIVER', 'nativo'),
        'jar' => env('SRI_FIRMADOR_JAR', resource_path('firmador/sri.jar')),
        'java' => env('SRI_JAVA_BIN', 'java'),
        'timeout' => env('SRI_FIRMADOR_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificados: binario openssl para el fallback de .p12 legacy
    |--------------------------------------------------------------------------
    */

    'certificados' => [
        'openssl' => env('SRI_OPENSSL_BIN', 'openssl'),
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

    /*
    |--------------------------------------------------------------------------
    | Webhooks: notificaciones firmadas a integradores (§11)
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        // timeout (s) de cada intento de entrega
        'timeout' => env('SRI_WEBHOOKS_TIMEOUT', 10),
        // días antes del vencimiento del certificado en los que se publica
        // certificado.por_vencer (una vez por umbral, con el comando diario)
        'certificado_umbrales_dias' => [30, 7, 1],
    ],
];
