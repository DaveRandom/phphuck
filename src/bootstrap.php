<?php

require __DIR__ . '/functions.php';

spl_autoload_register(function($className) {
    static $classMap = [
        'phphuck\\cbfhandler'              => __DIR__ . DIRECTORY_SEPARATOR . '/CBFHandler.php',
        'phphuck\\cmds'                    => __DIR__ . DIRECTORY_SEPARATOR . '/Cmds.php',
        'phphuck\\compiler'                => __DIR__ . DIRECTORY_SEPARATOR . '/Compiler.php',
        'phphuck\\filesourcestream'        => __DIR__ . DIRECTORY_SEPARATOR . '/FileSourceStream.php',
        'phphuck\\interpreter'             => __DIR__ . DIRECTORY_SEPARATOR . '/Interpreter.php',
        'phphuck\\invalidcbffileexception' => __DIR__ . DIRECTORY_SEPARATOR . '/InvalidCBFFileException.php',
        'phphuck\\ops'                     => __DIR__ . DIRECTORY_SEPARATOR . '/Ops.php',
        'phphuck\\releasestages'           => __DIR__ . DIRECTORY_SEPARATOR .  'ReleaseStages.php',
        'phphuck\\sourcestream'            => __DIR__ . DIRECTORY_SEPARATOR . '/SourceStream.php',
    ];

    $className = strtolower(ltrim($className, '\\'));
    if (isset($classMap[$className])) {
        /** @noinspection PhpIncludeInspection */
        require $classMap[$className];
    }
});
