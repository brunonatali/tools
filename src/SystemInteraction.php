<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class SystemInteraction implements SystemInteractionInterface
{
    Private $system = 0x00;
    Private $sysRunFolder;
    Private $sysRegisterShdn = false;
    Protected $systemIsSHDN = false;
    Private $functionsOnAborted = [];
	Private $myAppName;

    function __construct(array $runFolder = [])
    {
		
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$this->system = self::SYSTEM['WIN'];
		} else {
			$this->system = self::SYSTEM['UNIX'];
		}
		
        if (empty($runFolder)) {
            if ($this->system === self::SYSTEM['WIN']) {
                $this->sysRunFolder = self::SYSTEM_RUN_FOLDER_WIN;
            } else {
                $this->sysRunFolder = self::SYSTEM_RUN_FOLDER_UNIX;
            }
        }
        else $this->sysRunFolder = $runFolder;
    }

    Public function setAppInitiated(string $appName, bool $regShdn = true): bool
    {
		$this->myAppName = $appName;
        if ($this->getAppInitiated($appName)) 
            throw new \Exception('App "' . $appName . '" already set as initiated.');

        $thisAppPid = getmypid();

        foreach ($this->sysRunFolder as $sysRunFolder) {
            $pidHandle = fopen($sysRunFolder . $this->myAppName . '.pid', 'w+');
            if (fwrite($pidHandle, (string)$thisAppPid) === FALSE){
        		throw new \Exception('ERROR creating pid file.');
        	}
            @fclose($pidHandle);
        }

        if (!$regShdn) return true;
		
        $this->sysRegisterShdn = $regShdn;
        $this->setFunctionOnAppAborted(function () {
            $this->removePidFile();
        });
		
        register_shutdown_function([$this, 'setAborted']);
		
		if ($this->system !== self::SYSTEM['UNIX']) return true;
		
    	pcntl_signal(SIGTERM, [$this, 'setAborted']);
    	pcntl_signal(SIGHUP,  [$this, 'setAborted']);
    	pcntl_signal(SIGINT,  [$this, 'setAborted']);

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
            throw new \Exception('System not handling shutdown requests.');
            return false;
        }
        $this->functionsOnAborted[] = $func;
        return true;
    }

    Public function getSystem(): int
    {
        return $this->system;
    }

	Public function removePidFile(): bool
	{
		$toReturn = false;
		foreach ($this->sysRunFolder as $sysRunFolder)
            if(file_exists($sysRunFolder . $this->myAppName . '.pid')) {
				if ($this->system === self::SYSTEM['UNIX']) {
					if (!file_exists( "/proc/" . file_get_contents($sysRunFolder . $this->myAppName . '.pid'))) {
						@unlink($sysRunFolder . $this->myAppName . '.pid');
						$toReturn = true;
					}
				} else {
					$pid = file_get_contents($sysRunFolder . $this->myAppName . '.pid');
					$pidFound = false;
					$processes = explode( "\n", shell_exec( "tasklist.exe" ));
					foreach ( $processes as $process ) {
						if( trim($process) == "" ||
							strpos( "Image Name", $process ) === 0 || 
							strpos( "===", $process ) === 0 ) continue;
						
						$matches = false;
						preg_match( "/(.*?)\s+(\d+).*$/", $process, $matches );
						if (isset($matches[2]) && $pid == $matches[2]) {
							$pidFound = true;
							break;
						}
						
					}
					
					if (!$pidFound) {
						@unlink($sysRunFolder . $this->myAppName . '.pid');
						$toReturn = true;
					}
				}
			}
		
		return $toReturn;
	}
}
