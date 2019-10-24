<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class SystemInteraction implements SystemInteractionInterface
{
    Private $system = 0x00;
    Private $sysRunFolder;
    Private $sysRegisterShdn = false;
    Protected $systemIsSHDN = false;
    Private $functionsOnAborted = [];

    function __construct(array $runFolder = [])
    {
        if (is_empty($runFolder)) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $this->system = self::SYSTEM['WIN'];
                $this->sysRunFolder = self::SYSTEM_RUN_FOLDER_WIN;
            } else {
                $this->system = self::SYSTEM['UNIX'];
                $this->sysRunFolder = self::SYSTEM_RUN_FOLDER_UNIX;
            }
        }
        else $this->sysRunFolder = $runFolder;
    }

    Public function setAppInitiated(string $appName, bool $regShdn = true): bool
    {
        if ($this->getAppInitiated($appName)) {
            throw new Exception('App "' . $appName . '" already set as initiated.');
            return false;
        }

        $thisAppPid = getmypid();

        foreach ($this->sysRunFolder as $sysRunFolder) {
            $pidHandle = fopen($sysRunFolder . $appName . '.pid', 'w+');
            if (fwrite($pidHandle, (string)$thisAppPid) === FALSE){
        		throw new Exception('ERROR creating pid file.');
        	}
            @fclose($pidHandle);
        }

        $this->setFunctionOnAppAborted(function () use ($appName){
            foreach ($this->sysRunFolder as $sysRunFolder)
                if(file_exists($sysRunFolder . $appName . '.pid')) @unlink($sysRunFolder . $appName . '.pid');
        });

        if (!$regShdn) return true;
        register_shutdown_function([$this, 'setAborted']);
    	pcntl_signal(SIGTERM, [$this, 'setAborted']);
    	pcntl_signal(SIGHUP,  [$this, 'setAborted']);
    	pcntl_signal(SIGINT,  [$this, 'setAborted']);
        $this->sysRegisterShdn = true;

        return true;
    }

    Public function setAborted()
    {
        if ($this->systemIsSHDN) return;
        $this->systemIsSHDN = true;

        foreach ($this->functionsOnAborted as $func) $func();
        exit; // Exit must be called to close script, otherwise script will block system shutdown.
    }

    Public function getAppInitiated(string $appName): bool
    {
        foreach ($this->sysRunFolder as $sysRunFolder)
            if(file_exists($sysRunFolder . $appName . '.pid')) return true;

        return false;
    }

    Public function setFunctionOnAppAborted(callable $func): bool
    {
        if (!$this->sysRegisterShdn) {
            throw new Exception('System not handling shutdown requests.');
            return false;
        }
        $this->functionsOnAborted[] = $func;
        return true;
    }

    Public function getSystem(): int
    {
        return $this->system;
    }
}
