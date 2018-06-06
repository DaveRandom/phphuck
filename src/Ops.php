<?php

namespace PHPhuck;

class Ops
{
    const POINTER_INCREMENT          = "\x00";
    const POINTER_DECREMENT          = "\x01";
    const DATA_INCREMENT             = "\x02";
    const DATA_DECREMENT             = "\x03";
    const DATA_OUTPUT                = "\x04";
    const DATA_INPUT                 = "\x05";
    const JUMP_IF_ZERO               = "\x06";
    const JUMP_IF_NOT_ZERO           = "\x07";
    const ASSIGN_ZERO                = "\x08";
    const FIND_NEXT_ZERO_LEFT        = "\x09";
    const FIND_NEXT_ZERO_RIGHT       = "\x0A";
    const DATA_MULTIPLE_INCREMENT    = "\x0B";
    const DATA_MULTIPLE_DECREMENT    = "\x0C";
    const POINTER_MULTIPLE_INCREMENT = "\x0D";
    const POINTER_MULTIPLE_DECREMENT = "\x0E";
}
