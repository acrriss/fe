<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

// Rector se usa principalmente para portar la lógica del legado (PHP 7.1)
// a PHP 8.4 idiomático durante las fases 2 y 3.
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    );
