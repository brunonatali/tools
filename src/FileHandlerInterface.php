<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

interface FileHandlerInterface
{
    Public function getContent(LoopInterface &$loop = null, string $path = null);
    Public function getBytes(int $bytes);
    Public function getFileSize(bool $forceCheck = false);
    Public function getAccessTime();
    Public function getCreationTime();
    Public function getModifiedTime();
}