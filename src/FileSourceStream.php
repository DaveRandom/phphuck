<?php

namespace PHPhuck;

/**
 * @property-read resource $srcStream
 */
class FileSourceStream implements SourceStream
{
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
     * @param string $name
     * @return string|int
     * @throws \LogicException
     */
    public function __get($name)
    {
        if (!isset($this->$name)) {
            throw new \LogicException('Invalid property: ' . $name);
        }

        return $this->$name;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws \LogicException
     */
    public function __set($name, $value)
    {
        throw new \LogicException(get_class($this) . ' objects are sealed');
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
