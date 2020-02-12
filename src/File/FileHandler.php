<?php declare(strict_types=1);

namespace BrunoNatali\Tools\File;

class FileHandler implements FileHandlerInterface
{
    // Vars to handle external libs
    Private $loop = null;

    // Vars that could be accessed outside
    Protected $path;
    Protected $opened = false;
    Protected $hasData = false;
    Protected $readedBytes = 0; // Mean all readed bytes in life

    // Internal vars
    Private $fileDescriptor = false; // Set to false as returned default fopen error 
    Private $streamGetBytes = null;
    Private $lastGetBytes = 0;
    
    function __construct(string $path, \React\EventLoop\LoopInterface &$loop)
    {
        if (\BrunoNatali\Tools\SysInfo::getSystemType() === \BrunoNatali\Tools\SysInfo::SYSTEM_TYPE_WIN)
            throw new \Exception("This interation could not be used under Windows");
            
        $this->loop = &$loop;
        $this->path = $path;
    }

    Public function getBytes(int $bytes)
    {
        $me = &$this;
        $deferred = new \React\Promise\Deferred();

        if ($this->lastGetBytes === $bytes && 
            is_object($this->streamGetBytes) && 
            $this->streamGetBytes instanceof \React\Stream\ReadableStreamInterface
        ) {
            $this->streamGetBytes->resume();
        } else {
            if (($fileDescriptor = $this->getFileDescriptor()) !== false) {
                try {
                    $this->streamGetBytes = new \React\Stream\ReadableResourceStream($fileDescriptor, $this->loop, $bytes);
                } catch (RuntimeException $e) {
                    $deferred->reject($e);
                }
                $this->lastGetBytes = $bytes;
                $this->streamGetBytes->on('data', function ($chunk) use ($me, $deferred) {
                    $me->streamGetBytes->pause();
                    $me->readedBytes += strlen($chunk);
                    $deferred->resolve($chunk);
                });

                $this->hasData = true;
                $this->streamGetBytes->on('end', function () use ($me) {
                    $me->hasData = false;
                });

                $this->streamGetBytes->on('close', function () use ($me) {
                    $me->close();
                });
            } else {
                $deferred->reject(new \Exception('File could not be opened'));
            }
        }

        return $deferred->promise();    
    }

    Private function &getFileDescriptor(bool $force = false)
    {
        if ($force) $this->close();
        if ($this->fileDescriptor === false)
            $this->fileDescriptor = @fopen($this->path, "r"); // First of all just open as "read" mode

        if (is_resource($this->fileDescriptor) && get_resource_type($this->fileDescriptor) === 'stream') {
            fseek($this->fileDescriptor, $this->readedBytes);
            $this->opened = true;
        } else {
            $this->opened = false;
            $this->fileDescriptor = false; // Forcefully set to false
        }
        return $this->fileDescriptor;
    }

    Public function close(): void
    {
        if (!$this->opened) return;

        if (is_object($this->streamGetBytes) && method_exists($this->streamGetBytes,'close')) 
            $this->streamGetBytes->close();
        if (is_resoruce($this->fileDescriptor))
            @fclose($this->fileDescriptor);

        $this->opened = false;
        $this->hasData = false;
        $this->fileDescriptor = false;
        $this->streamGetBytes = null;
        $this->readedBytes = 0;
        $this->lastGetBytes = 0;
    }
}