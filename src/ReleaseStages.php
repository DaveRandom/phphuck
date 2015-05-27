<?php

namespace PHPhuck;

class ReleaseStages
{
    const DEV    = 0;
    const ALPHA  = 1;
    const BETA   = 2;
    const RC     = 3;
    const STABLE = 255;

    public static function getString($stage)
    {
        static $staticStages = [
            self::DEV    => 'dev',
            self::ALPHA  => 'alpha',
            self::BETA   => 'beta',
            self::STABLE => 'stable',
        ];

        if (isset($staticStages[$stage])) {
            return $staticStages[$stage];
        }

        return 'rc' . ($stage - self::RC);
    }
}
