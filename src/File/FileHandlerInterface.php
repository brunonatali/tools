<?php declare(strict_types=1);

namespace BrunoNatali\Tools\File;

interface FileHandlerInterface
{
    Public function getBytes(int $bytes);
    Public function close(): void;
}