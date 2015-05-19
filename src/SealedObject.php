<?php

namespace Brainfuck;

trait SealedObject
{
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
}
