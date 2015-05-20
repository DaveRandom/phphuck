<?php

namespace Brainfuck;

$begin = microtime(true);

require __DIR__ . '/SealedObject.php';
require __DIR__ . '/Cmds.php';
require __DIR__ . '/Ops.php';
require __DIR__ . '/SourceStream.php';
require __DIR__ . '/FileSourceStream.php';
require __DIR__ . '/Compiler.php';
require __DIR__ . '/Interpreter.php';

if (!isset($argv[1]) || $argv[1] === '--help') {
    echo <<<HELP

PHPhuck - Brainfuck interpreter written in PHP

Syntax: {$_SERVER['argv'][0]} <source file>
        {$_SERVER['argv'][0]} -c <code>\n

HELP;
    exit(0);
}

$stats = [
    'src' => '',
    'compiled_src' => '',
    'pre_compiled' => 'No',
    'compilation_time' => 0,
    'execution_time' => 0,
    'ops' => 0,
    'ops_sec' => 0,
];

$compiledSrc = new FileSourceStream(fopen('php://temp', 'w+'));

try {
    if ($argv[1] === '-c') {
        if (!isset($argv[2]) || $argv[2] === '') {
            throw new \RuntimeException('No code supplied to execute');
        }

        $compileStart = microtime(true);

        $src = fopen('php://temp', 'w+');
        fwrite($src, $argv[2]);
        rewind($src);

        (new Compiler)->compile($src, $compiledSrc->srcStream);
        rewind($compiledSrc->srcStream);

        fclose($src);
        $src = null;

        $stats['compilation_time'] = microtime(true) - $compileStart;
        $stats['src']              = '[String]';
        $stats['compiled_src']     = '';
        $stats['pre_compiled']     = 'No';
    } else {
        if (!is_file($argv[1])) {
            throw new \RuntimeException('Invalid path to source file: ' . $argv[1]);
        }

        if (strtolower(pathinfo($argv[1], PATHINFO_EXTENSION)) === 'bf') {
            $compiledPath = pathinfo($argv[1], PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . pathinfo($argv[1], PATHINFO_FILENAME) . '.cbf';

            if (is_file($compiledPath) && filemtime($compiledPath) >= filemtime($argv[1]) && $src = @fopen($compiledPath, 'r')) {
                $compileStart = microtime(true);

                stream_copy_to_stream($src, $compiledSrc->srcStream);
                rewind($compiledSrc->srcStream);

                fclose($src);
                $src = null;

                $stats['compilation_time'] = microtime(true) - $compileStart;
                $stats['src']              = $argv[1];
                $stats['compiled_src']     = $compiledPath;
                $stats['pre_compiled']     = 'Yes';
            } else {
                $src = @fopen($argv[1], 'r');
                if (!$src) {
                    throw new \RuntimeException('Failed to open source file: ' . $argv[1]);
                }

                $compileStart = microtime(true);

                (new Compiler)->compile($src, $compiledSrc->srcStream);
                rewind($compiledSrc->srcStream);

                fclose($src);
                $src = null;

                if ($dest = @fopen($compiledPath, 'w')) {
                    stream_copy_to_stream($compiledSrc->srcStream, $dest);
                    rewind($compiledSrc->srcStream);

                    fclose($dest);
                    $dest = null;
                }

                $stats['compilation_time'] = microtime(true) - $compileStart;
                $stats['src']              = $argv[1];
                $stats['compiled_src']     = $compiledPath;
                $stats['pre_compiled']     = 'No';
            }
        } else {
            $src = @fopen($argv[1], 'r');
            if (!$src) {
                throw new \RuntimeException('Failed to open source file: ' . $argv[1]);
            }

            $compileStart = microtime(true);

            (new Compiler)->compile($src, $compiledSrc->srcStream);
            rewind($compiledSrc->srcStream);

            fclose($src);
            $src = null;

            $stats['compilation_time'] = microtime(true) - $compileStart;
            $stats['src']              = $argv[1];
            $stats['compiled_src']     = '';
            $stats['pre_compiled']     = 'No';
        }
    }
} catch(\Exception $e) {
    fwrite(STDERR, "COMPILE ERROR: {$e->getMessage()}\n");
    exit(1);
}

try {
    $executeStart = microtime(true);
    $stats['ops'] = (new Interpreter(STDIN, STDOUT, 30000))->run($compiledSrc);
    $end = microtime(true);

    $stats['execution_time'] = $end - $executeStart;
    $stats['total_time'] = $end - $begin;
    $stats['ops_sec'] = $stats['execution_time'] == 0 ? INF : $stats['ops'] / $stats['execution_time'];
} catch (\Exception $e) {
    fwrite(STDERR, "RUNTIME ERROR: {$e->getMessage()}\n");
    exit(2);
}

echo <<<STATS


Source:           {$stats['src']}
Compiled Source:  {$stats['compiled_src']}
Pre-Compiled?:    {$stats['pre_compiled']}
Compilation Time: {$stats['compilation_time']}s
Execution Time:   {$stats['execution_time']}s
Total Time:       {$stats['total_time']}s
Ops Total:        {$stats['ops']}
Ops/Sec:          {$stats['ops_sec']}


STATS;
