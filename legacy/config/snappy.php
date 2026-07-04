<?php

return array(


    'pdf' => array(
        'enabled' => true,
        //'binary'  => '"C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltopdf.exe"',
        'binary' => 'wkhtmltopdf',
        //'binary'  => base_path('vendor/h4cc/wkhtmltopdf-i386/bin/wkhtmltopdf-i386'),
        'timeout' => false,
        'options' => array(),
        'env'     => array(),
    ),
    'image' => array(
        'enabled' => true,
        'binary' => 'wkhtmltoimage',
        //'binary'  => '"C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltoimage .exe"',
        //'binary'  => base_path('vendor\h4cc\wkhtmltoimage-i386\bin\wkhtmltoimage-i386'),
        'timeout' => false,
        'options' => array(),
        'env'     => array(),
    ),


);
