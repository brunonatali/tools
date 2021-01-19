<?php declare(strict_types=1);

namespace BrunoNatali\Tools\File;

interface OnFileChangeInterface
{
    const ERROR_FILE_NAME_ABSENT = 0x110;
    const ERROR_FILE_CALL_ABSENT = 0x111;
    const ERROR_FILE_LOOP_ABSENT = 0x112;
    const ERROR_FILE_NOT_EXIST = 0x113;

    const USE_SYSTEM_INOTIFY = 0x50;
    const USE_SYSTEM_POLLING = 0x51;

    /**
     * Just start monitoring system
     * 
     * @return bool
    */
    public function start(): bool;
    
    /**
     * Stop current file monitoring
     * 
     * @return bool
    */
    public function stop(): bool;

    /**
     * Configure / reconfigure polling time
     * 
     * @param float $time New polling time
     * @return bool
    */
    public function setPollingTime(int $time): bool;
}