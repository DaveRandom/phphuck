<?php

namespace PHPhuck;

function create_version_string($version)
{
    return sprintf('%d.%d.%d-%s', $version[0], $version[1], $version[2], ReleaseStages::getString($version[3]));
}
