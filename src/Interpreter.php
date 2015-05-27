<?php

namespace PHPhuck;

class Interpreter
{
    /**
     * @var int[]
     */
    private $data;

    /**
     * @var int[]
     */
    private static $version = [1, 0, 0, ReleaseStages::DEV];

    /**
     * @var int
     */
    private $ptr = 0;

    /**
     * @var resource
     */
    private $stdIn;

    /**
     * @var resource
     */
    private $stdOut;

    /**
     * @param resource $stdIn
     * @param resource $stdOut
     * @param int $dataArraySize
     * @throws \LogicException
     */
    public function __construct($stdIn = STDIN, $stdOut = STDOUT, $dataArraySize = 30000)
    {
        $this->stdIn = $stdIn;
        if (!is_resource($stdIn)) {
            throw new \LogicException('STDIN pointer must be a readable stream resource');
        }

        $this->stdOut = $stdOut;
        if (!is_resource($stdOut)) {
            throw new \LogicException('STDOUT pointer must be a writeable stream resource');
        }

        $this->data = new \SplFixedArray((int)$dataArraySize);
    }

    /**
     * @return int[]
     */
    public static function getVersion()
    {
        return self::$version;
    }

    /**
     * @param SourceStream $src
     * @return int
     * @throws \RuntimeException
     */
    public function run(SourceStream $src)
    {
        $opCount = 0;

        while (list($op, $arg) = $src->next()) {
            switch ($op) {
                case Ops::JUMPZ: if (empty($this->data[$this->ptr])) { $src->seek($arg); }                        break;
                case Ops::JMPNZ: if (!empty($this->data[$this->ptr])) { $src->seek($arg); }                       break;
                case Ops::PTINC: ++$this->ptr;                                                                    break;
                case Ops::PTDEC: --$this->ptr;                                                                    break;
                case Ops::DTINC: $this->data[$this->ptr] = ($this->data[$this->ptr] + 1) % 256;                   break;
                case Ops::DTDEC: $this->data[$this->ptr] = ($this->data[$this->ptr] + 255) % 256;                 break;
                case Ops::OUTPT: fwrite($this->stdOut, chr($this->data[$this->ptr]));                             break;
                case Ops::INPUT: $this->data[$this->ptr] = ord(fgetc($this->stdIn));                              break;
                case Ops::ASSNZ: $this->data[$this->ptr] = 0;                                                     break;
                case Ops::FNDZL: while ($this->data[--$this->ptr]);                                               break;
                case Ops::FNDZR: while ($this->data[++$this->ptr]);                                               break;
                case Ops::DTMLI: $this->data[$this->ptr] = ($this->data[$this->ptr] + $arg) % 256;                break;
                case Ops::DTMLD: $this->data[$this->ptr] = ($this->data[$this->ptr] + ($arg * 255)) % 256;        break;
                case Ops::PTMLI: $this->ptr += $arg;                                                              break;
                case Ops::PTMLD: $this->ptr -= $arg;                                                              break;
                case false: break 2;
                default: throw new \RuntimeException(sprintf(
                    'Unknown op in source stream; op=0x%02X; pos=0x%X; instruction=%d',
                    ord($op), $src->tell(), $opCount + 1
                ));
            }
            $opCount++;
        }

        return $opCount;
    }
}
