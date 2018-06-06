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
    private static $version = [1, 0, 0, ReleaseStages::RC + 1];

    /**
     * @var int
     */
    private $pointer = 0;

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
     * @throws \RuntimeException
     */
    private function validateCompilerVersion(SourceStream $src)
    {
        list($maj, $min) = $src->compilerVersion;

        if ($maj > self::$version[0] || ($maj === self::$version[0] && $min > self::$version[1])) {
            throw new \RuntimeException(sprintf(
                'Interpreter %s cannot execute code compiled by compiler %s',
                create_version_string(self::$version),
                create_version_string($src->compilerVersion)
            ));
        }
    }

    /**
     * @param SourceStream $src
     * @return int
     * @throws \RuntimeException
     */
    public function run(SourceStream $src)
    {
        $this->validateCompilerVersion($src);

        $opCount = 0;

        while (list($op, $arg) = $src->next()) {
            switch ($op) {
                case Ops::JUMP_IF_ZERO: {
                    if (empty($this->data[$this->pointer])) {
                        $src->seek($arg);
                    }

                    break;
                }

                case Ops::JUMP_IF_NOT_ZERO: {
                    if (!empty($this->data[$this->pointer])) {
                        $src->seek($arg);
                    }

                    break;
                }

                case Ops::POINTER_INCREMENT: {
                    ++$this->pointer;
                    break;
                }

                case Ops::POINTER_DECREMENT: {
                    --$this->pointer;
                    break;
                }

                case Ops::DATA_INCREMENT: {
                    $this->data[$this->pointer] = ($this->data[$this->pointer] + 1) % 256;
                    break;
                }

                case Ops::DATA_DECREMENT: {
                    $this->data[$this->pointer] = ($this->data[$this->pointer] + 255) % 256;
                    break;
                }

                case Ops::DATA_OUTPUT: {
                    fwrite($this->stdOut, chr($this->data[$this->pointer]));
                    break;
                }

                case Ops::DATA_INPUT: {
                    $this->data[$this->pointer] = ord(fgetc($this->stdIn));
                    break;
                }

                case Ops::ASSIGN_ZERO: {
                    $this->data[$this->pointer] = 0;
                    break;
                }

                case Ops::FIND_NEXT_ZERO_LEFT: {
                    do {
                        --$this->pointer;
                    } while (!empty($this->data[$this->pointer]));
                    break;
                }

                case Ops::FIND_NEXT_ZERO_RIGHT: {
                    do {
                        ++$this->pointer;
                    } while (!empty($this->data[$this->pointer]));
                    break;
                }

                case Ops::DATA_MULTIPLE_INCREMENT: {
                    $this->data[$this->pointer] = ($this->data[$this->pointer] + $arg) % 256;
                    break;
                }

                case Ops::DATA_MULTIPLE_DECREMENT: {
                    $this->data[$this->pointer] = ($this->data[$this->pointer] + ($arg * 255)) % 256;
                    break;
                }

                case Ops::POINTER_MULTIPLE_INCREMENT: {
                    $this->pointer += $arg;
                    break;
                }

                case Ops::POINTER_MULTIPLE_DECREMENT: {
                    $this->pointer -= $arg;
                    break;
                }

                case false: {
                    // end of op stream
                    break 2;
                }

                default: {
                    throw new \RuntimeException(sprintf(
                        'Unknown op in source stream; op=0x%02X; pos=0x%X; instruction=%d',
                        ord($op), $src->tell(), $opCount + 1
                    ));
                }
            }

            $opCount++;
        }

        return $opCount;
    }
}
