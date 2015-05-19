<?php

namespace Brainfuck;

/**
 * @property-read resource $srcStream
 */
class FileSourceStream implements SourceStream
{
    use SealedObject;

    /**
     * @var resource
     */
    private $srcStream;

    /**
     * @param resource $srcStream
     */
    public function __construct($srcStream)
    {
        $this->srcStream = $srcStream;
    }

    /**
     * @return array|null
     */
    public function next()
    {
        static $argOps = [
            Ops::JUMPZ => true, Ops::JMPNZ => true,
            Ops::DTMLI => true, Ops::DTMLD => true,
            Ops::PTMLI => true, Ops::PTMLD => true,
        ];

        return [$op = fgetc($this->srcStream), isset($argOps[$op]) ? unpack('N', fread($this->srcStream, 4))[1] : -1];
    }

    /**
     * @param int $position
     */
    public function seek($position)
    {
        fseek($this->srcStream, $position, SEEK_SET);
    }

    /**
     * @return int
     */
    public function tell()
    {
        return ftell($this->srcStream);
    }
}
