<?php

namespace PHPhuck;

class Compiler
{
    const ELIMINATE_EMPTY_LOOPS     = 0b0001;
    const SHORTCUT_SINGLE_CMD_LOOPS = 0b0010;
    const COMPRESS_REPEATED_CMDS    = 0b0100;
    const COMPILER_DEFAULT =
        self::ELIMINATE_EMPTY_LOOPS | self::SHORTCUT_SINGLE_CMD_LOOPS | self::COMPRESS_REPEATED_CMDS;

    /**
     * @var int[]
     */
    private static $version = [1, 0, 0, ReleaseStages::RC + 1];

    /**
     * @var string
     */
    private static $cbfMagicNumberPrefix = 'CBFv';

    /**
     * @var string[]
     */
    private static $nonLoopCmdOpMap = [
        Cmds::PTR_INC  => Ops::PTINC,
        Cmds::PTR_DEC  => Ops::PTDEC,
        Cmds::DATA_INC => Ops::DTINC,
        Cmds::DATA_DEC => Ops::DTDEC,
        Cmds::OUTPUT   => Ops::OUTPT,
        Cmds::INPUT    => Ops::INPUT,
    ];

    /**
     * @var string[]
     */
    private static $compressibleOps = [
        Ops::DTINC => Ops::DTMLI,
        Ops::DTDEC => Ops::DTMLD,
        Ops::PTINC => Ops::PTMLI,
        Ops::PTDEC => Ops::PTMLD,
    ];

    /**
     * @return int[]
     */
    public static function getVersion()
    {
        return self::$version;
    }

    /**
     * @return string
     */
    public static function getCBFMagicNumberPrefix()
    {
        return self::$cbfMagicNumberPrefix;
    }

    /**
     * @param string $op
     * @param int $loopStartChar
     * @param int $loopStartLine
     * @return string
     */
    private function getSingleInstructionLoopOptimisedOp($op, $loopStartChar, $loopStartLine)
    {
        static $optimisedOpMap = [
            Ops::PTINC => Ops::FNDZR,
            Ops::PTDEC => Ops::FNDZL,
            Ops::DTINC => Ops::ASSNZ,
            Ops::DTDEC => Ops::ASSNZ,
        ];

        if (isset($optimisedOpMap[$op])) {
            return $optimisedOpMap[$op];
        }

        if (in_array($op, [Ops::INPUT, Ops::OUTPT])) {
            throw new \RuntimeException(sprintf(
                "Infinite I/O loop at char %d on line %d",
                $loopStartChar, $loopStartLine
            ));
        }

        throw new \RuntimeException(sprintf(
            "Infinite loop containing unknown instruction 0x%02X at char %d on line %d",
            ord($op), $loopStartChar, $loopStartLine
        ));
    }

    /**
     * @param resource $src
     * @param int $flags
     * @return SourceStream
     */
    public function compile($src, $flags = self::COMPILER_DEFAULT)
    {
        $dst = new FileSourceStream(fopen('php://temp', 'w+'), self::$version);

        $dstPtr = $currentCompressibleOpCount = 0;
        $line = $char = 1;
        $loops = [];
        $currentCompressibleOp = null;

        while (!feof($src) && false !== $cmd = fgetc($src)) {
            if ($flags & self::COMPRESS_REPEATED_CMDS) {
                if (isset(self::$nonLoopCmdOpMap[$cmd]) && $currentCompressibleOp === self::$nonLoopCmdOpMap[$cmd]) {
                    $currentCompressibleOpCount++;
                } else {
                    if ($currentCompressibleOpCount > 2 && isset(self::$compressibleOps[$currentCompressibleOp])) {
                        $dstPtr -= $currentCompressibleOpCount;
                        fseek($dst->stream, $dstPtr);
                        ftruncate($dst->stream, $dstPtr);
                        $dstPtr += fwrite($dst->stream, self::$compressibleOps[$currentCompressibleOp] . pack('N', $currentCompressibleOpCount));
                    }

                    if (isset(self::$nonLoopCmdOpMap[$cmd])) {
                        $currentCompressibleOp = self::$nonLoopCmdOpMap[$cmd];
                        $currentCompressibleOpCount = 1;
                    } else {
                        $currentCompressibleOp = null;
                        $currentCompressibleOpCount = 0;
                    }
                }
            }

            if ($cmd === Cmds::LOOP_BEGIN) {
                goto process_loop_begin_char;
            } else if ($cmd === Cmds::LOOP_END) {
                goto process_loop_end_char;
            } else if (isset(self::$nonLoopCmdOpMap[$cmd])) {
                goto process_non_loop_char;
            } else if ($cmd === "\n") {
                goto process_eol_char;
            }

            goto char_process_end;

            process_loop_begin_char: {
                $dstPtr += fwrite($dst->stream, Ops::JUMPZ . "\x00\x00\x00\x00");
                $loops[] = [$dstPtr, $char, $line];
                goto char_process_end;
            }

            process_loop_end_char: {
                if (empty($loops)) {
                    throw new \RuntimeException('Unexpected loop end at char ' . $char . ' on line ' . $line);
                }

                list($loopStartPtr, $loopChar, $loopLine) = array_pop($loops);

                if ($loopStartPtr === $dstPtr && $flags & self::ELIMINATE_EMPTY_LOOPS) {
                    // loop contains no instructions, eliminate it
                    $dstPtr -= 5;
                    fseek($dst->stream, $dstPtr);
                    ftruncate($dst->stream, $dstPtr);
                } else if ($loopStartPtr === $dstPtr - 1 && $flags & self::SHORTCUT_SINGLE_CMD_LOOPS) {
                    // loop contains a single instruction, optimise the loop to a single op
                    fseek($dst->stream, $loopStartPtr);
                    $loopOp = fgetc($dst->stream);

                    $dstPtr -= 6;
                    fseek($dst->stream, $dstPtr);
                    ftruncate($dst->stream, $dstPtr);

                    $dstPtr += fwrite($dst->stream, $this->getSingleInstructionLoopOptimisedOp($loopOp, $loopChar, $loopLine));
                } else {
                    // Loop contains multiple instructions, write the end pointer into the start pointer
                    $dstPtr += fwrite($dst->stream, Ops::JMPNZ . pack('N', $loopStartPtr));
                    fseek($dst->stream, $loopStartPtr - 4);
                    fwrite($dst->stream, pack('N', $dstPtr));
                    fseek($dst->stream, $dstPtr);
                }

                goto char_process_end;
            }

            process_non_loop_char: {
                $dstPtr += fwrite($dst->stream, self::$nonLoopCmdOpMap[$cmd]);
                goto char_process_end;
            }

            process_eol_char: {
                $line++;
                $char = 0;
                goto char_process_end;
            }

            char_process_end: {
                $char++;
            }
        }

        if (!empty($loops)) {
            list(, $char, $line) = array_pop($loops);
            throw new \RuntimeException('Unclosed loop started at char ' . $char . ' on line ' . $line);
        }

        rewind($dst->stream);
        return $dst;
    }
}
