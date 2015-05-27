<?php

spl_autoload_register(function($className) {
    static $classMap = [
        'brainfuck\\cmds'             => __DIR__ . '/Cmds.php',
        'brainfuck\\compiler'         => __DIR__ . '/Compiler.php',
        'brainfuck\\filesourcestream' => __DIR__ . '/FileSourceStream.php',
        'brainfuck\\interpreter'      => __DIR__ . '/Interpreter.php',
        'brainfuck\\ops'              => __DIR__ . '/Ops.php',
        'brainfuck\\releasestages'    => __DIR__ . '/ReleaseStages.php',
        'brainfuck\\sourcestream'     => __DIR__ . '/SourceStream.php',
    ];

    $className = ltrim($className, '\\');
    if (isset($classMap[$className])) {
        /** @noinspection PhpIncludeInspection */
        require $classMap[$className];
    }
});
