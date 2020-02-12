<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

class SystemInteraction implements SystemInteractionInterface
{
    Private $system = 0x00;
    Private $sysRunFolder;
    Private $sysRegisterShdn = false;
    Protected $systemIsSHDN = false;
    Private $functionsOnAborted = [];
	Private $myAppName;
    Private $myPid;
    Private $loop = null;
    Protected $outSystem;

    // array $runFolder = [], LoopInterface &$loop = null
    function __construct(...$configs)
    {
        $userConfig = [];
        foreach ($configs as $param) {
            if (is_array($param)) {
                $userConfig += $param;
            } else if (is_object($param)) {
                if ($param instanceof LoopInterface) {
                    if ($this->loop === null) {
                        $this->loop = &$param;
                    } else {
                        throw new Exception( "Accept only one LoopInterface.");
                    }
                }
            } else {
                throw new Exception( "Wrong parameter. Accept only Array, LoopInterface.");
            }
        }
        if ($this->loop === null) throw new Exception( "Need to provide LoopInterface.");


        $this->system = SysInfo::getSystemType();

        $userConfig = OutSystem::helpHandleAppName( 
            $userConfig,
            ["outSystemName" => "SystemInteraction"]
        );
        $this->outSystem = new OutSystem($userConfig);

        if (empty($userConfig) || !isset($userConfig['systemRunFolder'])) {
            if ($this->system === self::SYSTEM_TYPE_WIN) {
                $this->sysRunFolder = self::SYSTEM_RUN_FOLDER_WIN;
            } else {
                $this->sysRunFolder = self::SYSTEM_RUN_FOLDER_UNIX;
            }
        } else if (isset($userConfig['systemRunFolder'])) {
            if (!is_array($userConfig['systemRunFolder']))
                throw new \Exception("'systemRunFolder' must be array of (string) folders.");
            $this->sysRunFolder = $userConfig['systemRunFolder'];
        }

        // Debug class initialization
        $this->outSystem->stdout(
            Encapsulation::formatClassConfigs4InitializationPrint($configs)
        );
    }
    
    public function __toString()
    {
        return 'SystemInteraction';
    }

    Public function setAppInitiated(string $appName, bool $regShdn = true): bool
    {
		$this->myAppName = $appName;
        while ($this->getAppRequestRestart()) { // Pause application til reboot
            sleep(1);
        }
        if ($this->getAppInitiated())
            throw new \Exception('App "' . $appName . '" already set as initiated.');

        $this->myPid = getmypid();

        foreach ($this->sysRunFolder as $sysRunFolder) {
            $pidHandle = fopen($sysRunFolder . $this->myAppName . '.pid', 'w+');
            if (fwrite($pidHandle, (string)$this->myPid) === FALSE){
        		throw new \Exception('ERROR creating pid file.');
        	}
            @fclose($pidHandle);
        }

        if (!$regShdn) {
            $this->outSystem->stdout("App initiated with no SHDN handler.", OutSystem::LEVEL_NOTICE);
            return true;
        }

        $this->sysRegisterShdn = $regShdn;
        $this->setFunctionOnAppAborted(function () {
            $this->outSystem->stdout("Called app abort / close. Starting shutdown.", OutSystem::LEVEL_NOTICE);
            if ($this->removePidFile()) $this->outSystem->stdout("Cleanup OK.", OutSystem::LEVEL_NOTICE);
			else $this->outSystem->stdout("Error cleaning PID.", OutSystem::LEVEL_NOTICE);
        });

        register_shutdown_function([$this, 'setAborted']);

		if ($this->system !== self::SYSTEM_TYPE_UNIX) {
            $this->outSystem->stdout("App initiated.", OutSystem::LEVEL_NOTICE);
            return true;
        }

    	pcntl_signal(SIGTERM, [$this, 'setAborted']);
    	pcntl_signal(SIGHUP,  [$this, 'setAborted']);
    	pcntl_signal(SIGINT,  [$this, 'setAborted']);

        $this->outSystem->stdout("App initiated.", OutSystem::LEVEL_NOTICE);
        return true;
    }

    Public function setAborted()
    {
        if ($this->systemIsSHDN) return;
        $this->systemIsSHDN = true;
        $this->outSystem->stdout("App actively aborted.", OutSystem::LEVEL_NOTICE);

        foreach ($this->functionsOnAborted as $index => $func) {
            $this->outSystem->stdout("Executing SHDN function: $index.", OutSystem::LEVEL_NOTICE);
            $func();
        }
        exit; // Exit must be called to close script, otherwise script will block system shutdown.
    }

    Public function getAppInitiated(string $appName = null): bool
    {
        if ($appName === null) $appName = $this->myAppName;

        foreach ($this->sysRunFolder as $sysRunFolder)
            if(file_exists($sysRunFolder . $appName . '.pid')) {
				if (!$this->removePidFile()) return true; // Try to remove pid file based on running process
				else $this->outSystem->stdout("Cleaning last PID.", OutSystem::LEVEL_NOTICE);
			}

        return false;
    }

    Public function getAppRequestRestart(string $appName = null): bool
    {
        if ($appName === null) $appName = $this->myAppName;

        foreach ($this->sysRunFolder as $sysRunFolder)
            if(file_exists($sysRunFolder . $appName . '.reboot')) return true;

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

	Public function removePidFile(bool $rebootPid = false): bool
	{
		$toReturn = false;
		foreach ($this->sysRunFolder as $sysRunFolder)
            if(file_exists($sysRunFolder . $this->myAppName . ($rebootPid ? '.reboot' : '.pid'))) {
				if ($this->system === self::SYSTEM_TYPE_UNIX) {
					if (!file_exists( "/proc/" . file_get_contents($sysRunFolder . $this->myAppName . ($rebootPid ? '.reboot' : '.pid')))) {
						@unlink($sysRunFolder . $this->myAppName . '.pid');
						$toReturn = true;
					}
				} else {
					$pid = file_get_contents($sysRunFolder . $this->myAppName . ($rebootPid ? '.reboot' : '.pid'));
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
						@unlink($sysRunFolder . $this->myAppName . ($rebootPid ? '.reboot' : '.pid'));
						$toReturn = true;
					}
				}
			}

		return $toReturn;
	}

    Public function restartApp(): int
    {
        $this->outSystem->stdout("Restarting App.", OutSystem::LEVEL_NOTICE);
        // Just work in UNIX now
        if ($this->system !== self::SYSTEM_TYPE_UNIX) return self::ERROR_SYSTEM_NOT_SUPPORTED;

        // check if is needed
        if ($this->loop === null) 
            throw new \Exception('To use this function provide a LoopInterface in construction.');

        if ($this->getAppRequestRestart()) return self::ERROR_BUSY_WITH_SAME_REQUEST;

        foreach ($this->sysRunFolder as $sysRunFolder) {
            $pidHandle = fopen($sysRunFolder . $this->myAppName . '.reboot', 'w+');
            if (fwrite($pidHandle, (string)$this->myPid) === FALSE){
        		throw new \Exception('ERROR creating reboot pid file.');
        	}
            @fclose($pidHandle);
        }

        $return = self::ERROR_OK;
        $cmdArgs = (isset($argv) ? implode(' ', $argv) : '');
    
        if ($this->system === self::SYSTEM_TYPE_UNIX) {
            $procRunningNow = SysInfo::getCommandByProcName([$this->myAppName]);
        } else {
            // To use when implemented for windows too
        }
        
        if (is_array($procRunningNow)) {
            // Need to look this case, for now will use generic call
            exec("php $cmdArgs > /dev/null 2>&1 &");
            $return = self::ERROR_COULD_NOT_COMPLETE_PARSE;
        } else {
            exec("$procRunningNow $cmdArgs > /dev/null 2>&1 &");
        }
        
        $this->setFunctionOnAppAborted($this->removePidFile(true));
        $this->setAborted();
        return $return;
    }

    Public static function isCli(): bool
    {
        return (php_sapi_name() === 'cli');
    }
}
