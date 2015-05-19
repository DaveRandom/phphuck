<?php

namespace Brainfuck;

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
