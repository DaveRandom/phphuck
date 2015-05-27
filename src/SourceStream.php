<?php

namespace PHPhuck;

/**
 * @property-read resource $stream
 * @property-read int[] $compilerVersion
 */
interface SourceStream
{
    /**
     * @return array|null
     */
    public function next();

    /**
     * @param int $position
     */
    public function seek($position);

    /**
     * @return int
     */
    public function tell();
}
