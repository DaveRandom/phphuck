<?php

namespace Brainfuck;

class Ops
{
    const PTINC = "\x00";
    const PTDEC = "\x01";
    const DTINC = "\x02";
    const DTDEC = "\x03";
    const OUTPT = "\x04";
    const INPUT = "\x05";
    const JUMPZ = "\x06";
    const JMPNZ = "\x07";
    const ASSNZ = "\x08";
    const FNDZL = "\x09";
    const FNDZR = "\x0A";
    const DTMLI = "\x0B";
    const DTMLD = "\x0C";
    const PTMLI = "\x0D";
    const PTMLD = "\x0E";
}
