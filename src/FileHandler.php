<?php declare(strict_types=1);

/**
*   Just work on unix
*/

namespace BrunoNatali\Tools;

use React\Stream\ReadableStreamInterface;
use React\EventLoop\LoopInterface;
use React\Filesystem\Filesystem;
use React\Promise\Deferred;

class FileHandler implements FileHandlerInterface
{
    Private $loop = null;
    Private $filesystem = null;
    Private $file = null;
    Private $adapter = null;

    Private $path = null;
    Private $times = null;
    Protected $size = null;

    Private $getBytesStart = 0;
    Private $getBytesCurrent = 0;

    function __construct(LoopInterface &$loop, string $path)
    {
        $this->loop = &$loop;
        $this->path = $path;

        try {
            $this->filesystem = Filesystem::create($this->loop);
        } catch (RuntimeException $e) {
            echo "Could not use React\Filesystem due an error: " . $e->getMessage() . PHP_EOL; // Need to be better implemented
            exit;
        }
        
        $this->adapter = $this->filesystem->getAdapter();
        $this->file = $this->filesystem->file($this->path);
    }

    Public static function getContent(LoopInterface &$loop = null, string $path = null)
    {
        $deferred = new Deferred();
        if ($loop === null && $this->loop === null) {
            $deferred->reject(new Exception("LoopInterface could not be empty", 1));
            return $deferred->promise();
        }
        if ($path === null && $this->path === null) {
            $deferred->reject(new Exception("File path could not be empty", 1));
            return $deferred->promise();
        }

        if ($this->filesystem === null) {
            $this->filesystem = Filesystem::create(($loop === null ? $this->loop : $loop));
            $this->file = $this->filesystem->file(($path === null ? $this->path : $path));
        }

        return $this->file->getContents();
    }

    Public function getBytes(int $bytes)
    {
        $me = &$this;
        $continue = true;
        $deferred = new Deferred();

        if ($this->size === null)
            $this->file->size()->then(function ($size) use ($me, $deferred, $continue) {
                $me->size = $size;
            }, function ($e) {
                $continue = false;
                $deferred->reject($e);
            });
        
        if ($this->getBytesStart + $bytes > $this->size) $bytes = $this->size - $this->getBytesStart;

        $this->file->open('r')->then(function (ReadableStreamInterface $stream) use ($me, $deferred, $bytes) {
            $fileDescriptor = $stream->getFiledescriptor();

            $me->adapter->read($fileDescriptor, $bytes, $me->getBytesStart)->then(function ($content) use ($me, $deferred, $bytes) {
                $me->getBytesStart = $me->getBytesStart + $bytes;
                $deferred->resolve($content);
            });
        });

        return $deferred->promise();
    }

    Public function getAccessTime()
    {
        $deferred = new Deferred();
        if ($this->times === null) {
            $this->file->time()->then(function ($times) use ($deferred) {
                $this->times = $times;
                $deferred->resolve($this->times['atime']->format('r'));
            }, function ($e) {
                $deferred->reject($e);
            });
        } else {
            $deferred->resolve($this->times['atime']->format('r'));
        }

        return $deferred->promise();
    }

    Public function getCreationTime()
    {
        $deferred = new Deferred();
        if ($this->times === null) {
            $this->file->time()->then(function ($times) use ($deferred) {
                $this->times = $times;
                $deferred->resolve($this->times['ctime']->format('r'));
            }, function ($e) {
                $deferred->reject($e);
            });
        } else {
            $deferred->resolve($this->times['ctime']->format('r'));
        }

        return $deferred->promise();
    }

    Public function getModifiedTime()
    {
        $deferred = new Deferred();
        if ($this->times === null) {
            $this->file->time()->then(function ($times) use ($deferred) {
                $this->times = $times;
                $deferred->resolve($this->times['mtime']->format('r'));
            }, function ($e) {
                $deferred->reject($e);
            });
        } else {
            $deferred->resolve($this->times['mtime']->format('r'));
        }

        return $deferred->promise();
    }
}