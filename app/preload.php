<?php
/**
 * OPcache preload — compila archivos PHP en memoria al arrancar FPM.
 * validate_timestamps=0 garantiza que no se vuelven a leer del disco.
 */

$vendorDirs = [
    // PSR / interfaces
    'psr/log/src',
    'psr/container/src',
    'psr/simple-cache/src',
    'psr/http-message/src',
    'psr/http-factory/src',
    'psr/event-dispatcher/src',
    // Symfony polyfills
    'symfony/polyfill-php83',
    'symfony/polyfill-php82',
    'symfony/polyfill-mbstring',
    'symfony/deprecation-contracts',
    'symfony/translation-contracts/src',
    'symfony/service-contracts/src',
    'symfony/event-dispatcher-contracts/src',
    // Symfony components (base antes que UID/etc)
    'symfony/finder/src' ,
    'symfony/filesystem/src',
    'symfony/console/src',
    'symfony/http-foundation/src',
    'symfony/http-kernel/src',
    'symfony/routing/src',
    'symfony/var-dumper/src',
    // Laravel framework
    'laravel/framework/src/Illuminate/Contracts',
    'laravel/framework/src/Illuminate/Macroable',
    'laravel/framework/src/Illuminate/Collections',
    'laravel/framework/src/Illuminate/Support',
    'laravel/framework/src/Illuminate/Container',
    'laravel/framework/src/Illuminate/Pipeline',
    'laravel/framework/src/Illuminate/Events',
    'laravel/framework/src/Illuminate/Bus',
    'laravel/framework/src/Illuminate/Http',
    'laravel/framework/src/Illuminate/Routing',
    'laravel/framework/src/Illuminate/Database',
    'laravel/framework/src/Illuminate/Auth',
    'laravel/framework/src/Illuminate/Session',
    'laravel/framework/src/Illuminate/View',
    'laravel/framework/src/Illuminate/Validation',
    'laravel/framework/src/Illuminate/Filesystem',
    'laravel/framework/src/Illuminate/Log',
    'laravel/framework/src/Illuminate/Cache',
    'laravel/framework/src/Illuminate/Encryption',
    'laravel/framework/src/Illuminate/Hashing',
    'laravel/framework/src/Illuminate/Cookie',
    'laravel/framework/src/Illuminate/Foundation',
    // Livewire (ANTES de los componentes de la app)
    'livewire/livewire/src',
    // Otros paquetes usados
    'illuminate/contracts/src',
    'nesbot/carbon/src',
    'brick/math/src',
    'ramsey/uuid/src',
];

$base    = __DIR__ . '/vendor';
$compiled = 0;

foreach ($vendorDirs as $dir) {
    $full = "{$base}/{$dir}";
    if (!is_dir($full)) continue;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            @opcache_compile_file($file->getRealPath());
            $compiled++;
        }
    }
}

// Clases de la aplicación
$appDirs = [__DIR__ . '/app'];
foreach ($appDirs as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            @opcache_compile_file($file->getRealPath());
            $compiled++;
        }
    }
}

error_log("[preload] Compilados: {$compiled} archivos");
