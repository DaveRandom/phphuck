<?php

namespace PHPhuck;

class CBFHandler
{
    const MAGIC_NUMBER_PREFIX = 'CBFv';

    /**
     * @param string $srcFilePath
     * @return FileSourceStream
     */
    public function open($srcFilePath)
    {
        if (!is_file($srcFilePath) || !is_readable($srcFilePath)) {
            throw new \RuntimeException('File ' . $srcFilePath . ' does not exist or is not readable');
        }

        $rawStream = fopen($srcFilePath, 'r');

        if (fread($rawStream, 4) !== self::MAGIC_NUMBER_PREFIX) {
            throw new InvalidCBFFileException($srcFilePath . ' is not a valid CBF file (invalid magic number)');
        } else if (strlen($rawVersion = fread($rawStream, 4)) !== 4) {
            throw new InvalidCBFFileException($srcFilePath . ' is not a valid CBF file (incomplete compiler compilerVersion identifier)');
        }

        $compilerVersion = array_values(unpack('C*', $rawVersion));

        $stream = fopen('php://temp', 'w+');
        stream_copy_to_stream($rawStream, $stream);
        rewind($stream);
        fclose($rawStream);

        return new FileSourceStream($stream, $compilerVersion);
    }

    /**
     * @param SourceStream $src
     * @param string $dstPath
     */
    public function writeSourceStream(SourceStream $src, $dstPath)
    {
        if (!$dst = fopen($dstPath, 'w')) {
            throw new \RuntimeException('File ' . $dstPath . ' is not writable');
        }

        fwrite($dst, self::MAGIC_NUMBER_PREFIX . pack('C*', ...$src->compilerVersion));
        stream_copy_to_stream($src->stream, $dst);
        fclose($dst);
    }
}
