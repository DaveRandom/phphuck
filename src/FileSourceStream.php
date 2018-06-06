<?php

namespace PHPhuck;

/**
 * @property-read resource $stream
 * @property-read int[] $compilerVersion
 */
class FileSourceStream implements SourceStream
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @var int[]
     */
    private $compilerVersion;

    /**
     * @param resource $stream
     * @param int[] $version
     */
    public function __construct($stream, array $version)
    {
        if (count($version) !== 4) {
            throw new \LogicException('Invalid version: must contain exactly 4 bytes as integers');
        }

        foreach ($version as $element) {
            if (!is_int($element) || $element < 0 || $element > 255) {
                throw new \LogicException('Invalid version: must contain exactly 4 bytes as integers');
            }
        }

        $this->stream = $stream;
        $this->compilerVersion = $version;
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
        // Ops that carry an argument in the following 4 bytes
        static $argOps = [
            Ops::JUMP_IF_ZERO => 1, Ops::JUMP_IF_NOT_ZERO => 1,
            Ops::DATA_MULTIPLE_INCREMENT => 1, Ops::DATA_MULTIPLE_DECREMENT => 1,
            Ops::POINTER_MULTIPLE_INCREMENT => 1, Ops::POINTER_MULTIPLE_DECREMENT => 1,
        ];

        $op = fgetc($this->stream);
        $arg = isset($argOps[$op])
            ? unpack('N', fread($this->stream, 4))[1]
            : -1;

        return [$op, $arg];
    }

    /**
     * @param int $position
     */
    public function seek($position)
    {
        fseek($this->stream, $position, SEEK_SET);
    }

    /**
     * @return int
     */
    public function tell()
    {
        return ftell($this->stream);
    }
}
