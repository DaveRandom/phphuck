<?php

namespace Brainfuck;

class Compiler
{
    const ELIMINATE_EMPTY_LOOPS     = 0b0001;
    const SHORTCUT_SINGLE_CMD_LOOPS = 0b0010;
    const COMPRESS_REPEATED_CMDS    = 0b0100;
    const OPTIMISE_ALL = self::ELIMINATE_EMPTY_LOOPS | self::SHORTCUT_SINGLE_CMD_LOOPS | self::COMPRESS_REPEATED_CMDS;

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
     * @param string $op
     * @param int $loopStartChar
     * @param int $loopStartLine
     * @return string
     */
    private function getSingleInstructionLoopOptimisedOp($op, $loopStartChar, $loopStartLine)
    {
        switch ($op) {
            case Ops::PTINC:
                return Ops::FNDZR;
            case Ops::PTDEC:
                return Ops::FNDZL;
            case Ops::DTINC: case Ops::DTDEC:
                return Ops::ASSNZ;
            case Ops::INPUT: case Ops::OUTPT:
                throw new \RuntimeException(sprintf(
                    "Infinite I/O loop at char %d on line %d",
                    $loopStartChar, $loopStartLine
                ));
            default:
                throw new \RuntimeException(sprintf(
                    "Infinite loop containing unknown instruction 0x%02X at char %d on line %d",
                    ord($op), $loopStartChar, $loopStartLine
                ));
        }
    }

    /**
     * @param resource $src
     * @param resource $dst
     * @param int $flags
     */
    public function compile($src, $dst, $flags = self::OPTIMISE_ALL)
    {
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
                        fseek($dst, $dstPtr);
                        ftruncate($dst, $dstPtr);
                        $dstPtr += fwrite($dst, self::$compressibleOps[$currentCompressibleOp] . pack('N', $currentCompressibleOpCount));
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
                $dstPtr += fwrite($dst, Ops::JUMPZ . "\x00\x00\x00\x00");
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
                    fseek($dst, $dstPtr);
                    ftruncate($dst, $dstPtr);
                } else if ($loopStartPtr === $dstPtr - 1 && $flags & self::SHORTCUT_SINGLE_CMD_LOOPS) {
                    // loop contains a single instruction, optimise the loop to a single op
                    fseek($dst, $loopStartPtr);
                    $loopOp = fgetc($dst);

                    $dstPtr -= 6;
                    fseek($dst, $dstPtr);
                    ftruncate($dst, $dstPtr);

                    $dstPtr += fwrite($dst, $this->getSingleInstructionLoopOptimisedOp($loopOp, $loopChar, $loopLine));
                } else {
                    // Loop contains multiple instructions, write the end pointer into the start pointer
                    $dstPtr += fwrite($dst, Ops::JMPNZ . pack('N', $loopStartPtr));
                    fseek($dst, $loopStartPtr - 4);
                    fwrite($dst, pack('N', $dstPtr));
                    fseek($dst, $dstPtr);
                }

                goto char_process_end;
            }

            process_non_loop_char: {
                $dstPtr += fwrite($dst, self::$nonLoopCmdOpMap[$cmd]);
                goto char_process_end;
            }

            process_eol_char: {
                $line++;
                $char = 0;
                goto char_process_end;
            }

            char_process_end:
            $char++;
        }

        if (!empty($loops)) {
            list(, $char, $line) = array_pop($loops);
            throw new \RuntimeException('Unclosed loop started at char ' . $char . ' on line ' . $line);
        }
    }
}
