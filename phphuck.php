<?php

namespace PHPhuck;

$begin = microtime(true);

require __DIR__ . '/src/bootstrap.php';

function error($msg, $exitCode = 1)
{
    fwrite(STDERR, "ERROR: {$msg}\n");
    exit((int)$exitCode);
}

function get_options($argv)
{
    $options = [
        'show_stats' => false,
        'run_code' => true,
        'cbf_path' => null,
        'compiler_flags' => 0,
        'compiler_no_flags' => false,
        'src_code' => null,
        'src_file' => null,
    ];

    reset($argv);
    while (false !== $arg = next($argv)) {
        list($arg, $value) = explode('=', $arg . '=');

        switch ($arg) {
            case '--version': case '--help': case '?':
                echo "\n"
                   . "PHPhuck - Brainfuck interpreter written in PHP\n"
                   . " Compiler Version:   " . create_version_string(Compiler::getVersion()) . "\n"
                   . " Interpreter Version: " . create_version_string(Interpreter::getVersion()) . "\n"
                   . "\n";

                if ($arg !== '--version') {
                    echo <<<HELP
Syntax: {$argv[0]} [options] <source file>
        {$argv[0]} [options] -r <code>

Options:
    --help              Display this help
    --version           Display version information
    --cv                Display compiler version information
    --iv                Display interpreter version information
    --compile[=<dest>]  Compile code to <dest> file and exit
    --stats             Show compilation/execution stats when complete
    -r <code>           Use <code> as source
    -f <flag>           Use compiler flag <flag>

Compiler Flags:

 The -f option may be used more than once to specify multiple flags. By default
 COMPILER_DEFAULT is passed,  using -f applies only those explicitly specified.
 A special flag  of NONE,  available only through  this CLI,  indicates that no
 flags will  be passed to the compiler  -  use of this flag in conjunction with
 any other flag will result in an error.

 Currently available compiler flags are:

   COMPILER_DEFAULT           The compiler default flag configuration. This is
                              currently defined as all available flags.
   COMPRESS_REPEATED_CMDS     Multiple   sequential  identical   commands  are
                              converted to a single instruction.
   ELIMINATE_EMPTY_LOOPS      Loops  containing no  commands do  not emit  any
                              instructions.
   SHORTCUT_SINGLE_CMD_LOOPS  Loops that repeat a single command are optimised
                              to a  single instruction.  + and -  commands are
                              converted  to  an  assignment  of  zero  to  the
                              current pointer position,  < and > commands move
                              the  pointer  until a zero  is encountered.  I/O
                              commands result in a compile-time error.
\n
HELP;
                }

                exit(0);

            case '--cv':
                exit(create_version_string(Compiler::getVersion()) . "\n");

            case '--iv':
                exit(create_version_string(Interpreter::getVersion()) . "\n");

            case '--stats':
                $options['show_stats'] = true;
                break;

            case '--compile':
                $options['run_code'] = false;
                if ($value !== '') {
                    $options['cbf_path'] = $value;
                }
                break;

            case '-r':
                if (false === $value = next($argv)) {
                    error("-r requires a code string");
                }

                $options['src_code'] = $value;
                break;

            case '-f':
                if (false === $value = next($argv)) {
                    error("-f requires a compiler flag");
                }

                $value = strtoupper($value);
                if ($value === 'NONE') {
                    if ($options['compiler_flags'] !== 0) {
                        error("-f NONE cannot be used in conjunction with any other compiler flag");
                    }

                    $options['compiler_no_flags'] = true;
                    break;
                }

                $constName = Compiler::class . '::' . $value;
                if (!defined($constName)) {
                    error("Unknown compiler flag: {$value}");
                }

                $options['compiler_flags'] |= constant($constName);
                break;

            default:
                if ($options['src_code'] !== null) {
                    error("-r cannot be used in conjunction with a source file");
                }
                if (false !== next($argv)) {
                    error("Source file must be the last argument");
                }

                $options['src_file'] = $arg;
        }
    }

    if ($options['src_file'] === null && $options['src_code'] === null) {
        error("No source file or code string specified, use --help to display help");
    }

    if ($options['compiler_flags'] === 0 && !$options['compiler_no_flags']) {
        $options['compiler_flags'] = Compiler::COMPILER_DEFAULT;
    }

    if (!$options['run_code'] && $options['cbf_path'] === null) {
        if ($options['src_file'] === null) {
            error("No compilation destination specified");
        }

        $options['cbf_path'] = in_array(strtolower(pathinfo($options['src_file'], PATHINFO_EXTENSION)), ['b', 'bf'])
            ? implode('.', array_slice(explode('.', $options['src_file']), 0, -1)) . '.cbf'
            : $options['src_file'] . '.cbf';
    }

    return $options;
}

$stats = [
    'src' => 'N/A',
    'compiled_src' => 'N/A',
    'pre_compiled' => 'No',
    'compilation_time' => 'N/A',
    'execution_time' => 'N/A',
    'ops' => 'N/A',
    'ops_sec' => 'N/A',
];

$options = get_options($_SERVER['argv']);
$cbfHandler = new CBFHandler;
$rawSrc = $compiledSrc = null;

if ($options['src_file'] !== null) {
    try {
        $compiledSrc = $cbfHandler->open($options['src_file']);

        if (!$options['run_code']) {
            error('INIT: Specified source file is already compiled');
        }

        $stats['compiled_src'] = $options['src_file'];
        $stats['pre_compiled'] = 'Yes';
    } catch (InvalidCBFFileException $e) {
        $rawSrc = fopen($options['src_file'], 'r');
        $stats['src'] = $options['src_file'];
    } catch (\Exception $e) {
        error('INIT: ' . $e->getMessage());
    }
} else {
    $rawSrc = fopen('php://temp', 'w+');
    fwrite($rawSrc, $options['src_code']);
    rewind($rawSrc);
    $stats['src'] = 'Command line code';
}

if (!isset($compiledSrc)) {
    if (!isset($rawSrc)) {
        error('INIT: Raw source stream not defined');
    }

    try {
        $compileStart = microtime(true);

        $compiledSrc = (new Compiler)->compile($rawSrc, $options['compiler_flags']);
        fclose($rawSrc);

        $stats['compilation_time'] = (microtime(true) - $compileStart) . ' sec';
    } catch (\Exception $e) {
        error('COMPILER: ' . $e->getMessage());
    }
}

if (!$options['run_code']) {
    $cbfHandler->writeSourceStream($compiledSrc, $options['cbf_path']);
    $stats['compiled_src'] = $options['cbf_path'];
    goto show_stats;
}

try {
    $executeStart = microtime(true);
    $stats['ops'] = (new Interpreter(STDIN, STDOUT, 30000))->run($compiledSrc);
    $executeEnd = microtime(true);

    $stats['execution_time'] = ($executeEnd - $executeStart) . ' sec';
    $stats['ops_sec'] = $executeEnd - $executeStart == 0 ? INF : $stats['ops'] / $stats['execution_time'];
} catch (\Exception $e) {
    error('RUNTIME: ' . $e->getMessage());
}

show_stats: {
    if ($options['show_stats']) {
        $stats['total_time'] = (microtime(true) - $begin) . ' sec';

        echo <<<STATS


Source:           {$stats['src']}
Compiled Source:  {$stats['compiled_src']}
Pre-Compiled?:    {$stats['pre_compiled']}
Compilation Time: {$stats['compilation_time']}
Execution Time:   {$stats['execution_time']}
Total Time:       {$stats['total_time']}
Ops Total:        {$stats['ops']}
Ops/Sec:          {$stats['ops_sec']}


STATS;
    }
}
