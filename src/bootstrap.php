<?php

namespace Brainfuck;

function help($errMsg = null, $errNo = null)
{
    if (isset($errMsg)) {
        fwrite(STDERR, "ERROR: {$errMsg}\n");
    }


    if (!isset($errNo)) {
        $errNo = (int)isset($errMsg);
    }

    exit($errNo);
}

function error($msg, $errNo = 1)
{
    fwrite(STDERR, "ERROR: {$msg}\n");
    exit($errNo);
}

require __DIR__ . '/SealedObject.php';
require __DIR__ . '/Cmds.php';
require __DIR__ . '/Ops.php';
require __DIR__ . '/SourceStream.php';
require __DIR__ . '/FileSourceStream.php';
require __DIR__ . '/Compiler.php';
require __DIR__ . '/Interpreter.php';
