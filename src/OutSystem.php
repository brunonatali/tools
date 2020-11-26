<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class OutSystem implements OutSystemInterface
{
    private $outSystemEnabled = self::DEFAULT_OUT_SYSTEM_ENABLED;

    private $outSystemLevel = self::DEFAULT_OUT_SYSTEM_LEVEL; 

    private $outSystemEol = self::DEFAULT_OUT_SYSTEM_EOL; 

    private $outSystemEolMsg = null; 

    private $outSystemDebug = false;

    private $outSystemName = self::DEFAULT_OUT_SYSTEM_NAME; 

    private $lastMsgHasEol = true;

    //function __construct(array $config = [], LoopInterface &$loop = null)
    function __construct(array &$config = [])
    {
        $this->outSystemEolMsg = (SystemInteraction::isCli() ? PHP_EOL : "<br>");   
        $this->appConfigure($config);

        // Debug class initialization
        if ($this->outSystemDebug)
            $this->dstdout(
                Encapsulation::formatClassConfigs4InitializationPrint($config),
                ["outSystemName" => "OutSystem"]
            );
    }
    
    public function __toString()
    {
        return 'OutSystem';
    }

    private function appConfigure(array &$config): void
    {
        foreach($config as $name => $value) {
            if (\is_string($name))
                $name = \strtoupper(\trim($name));
            if (\is_string($value)) 
                $value = trim($value);
            
            if ($name === "OUTSYSTEMENABLED") {
                if (!\is_bool($value)) 
                    throw new \Exception("Configuration '$name' must be boolean.");

                $this->outSystemEnabled = $value;
                unset($config[$name]);
            } else if ($name === "OUTSYSTEMDEBUG") {
                if (!\is_bool($value)) 
                    throw new \Exception("Configuration '$name' must be boolean.");

                $this->outSystemDebug = $value;
                unset($config[$name]);
            } else if ($name === "OUTSYSTEMLEVEL") {
                if (!\is_int($value) || $value < self::LEVEL_ALL || $value > self::LEVEL_IMPORTANT) 
                    throw new \Exception(
                        "Configuration '$name' must be integer and between " . self::LEVEL_ALL . " and " . self::LEVEL_IMPORTANT
                    );

                $this->outSystemLevel = $value;
                unset($config[$name]);
            } else if ($name === "OUTSYSTEMEOL") {
                if (!\is_bool($value)) 
                    throw new \Exception( "Configuration '$name' must be boolean.");

                $this->outSystemEol = $value;
                unset($config[$name]);
            } else if ($name === "OUTSYSTEMEOLMSG") {
                if (!\is_string($value)) 
                    throw new \Exception( "Configuration '$name' must be string.");

                $this->outSystemEolMsg = $value;
                unset($config[$name]);
            } else if ($name === "OUTSYSTEMNAME") {
                if (!\is_array($value) && !\is_string($value)) 
                    throw new \Exception( "Configuration '$name' must be string or array of strings.");

                if (\is_array($value))
                    $this->outSystemName = '[' . \implode('][', $value) . ']'; // This will help trace calls
                else
                    $this->outSystemName = "[$value]";
                    
                unset($config[$name]);
            }
        }
    }

    public function enable()
    {
        $this->outSystemEnabled = true;
    }

    public function disable()
    {
        $this->outSystemEnabled = false;
    }

    public function enableEol()
    {
        $this->outSystemEol = true;
    }

    Public function disableEol()
    {
        $this->outSystemEol = false;
    }

    public function setPreferedEol($eolMsg = null)
    {
        $this->outSystemEolMsg = $eolMsg;
    }

    public function getLastMsgHasEol(): bool
    {
        return $this->lastMsgHasEol;
    }

    public function stdout(...$data): bool
    {
        if (!$this->outSystemEnabled) 
            return false;

        $msg = null;
        $eol = $this->outSystemEol;
        $level = self::DEFAULT_OUT_SYSTEM_LEVEL;

        foreach ($data as $param)
            if (\is_string($param)) // Message 
                $msg = $param;
            else if (\is_bool($param)) // line end enable / disable
                $eol = $param;
            else if (\is_int($param)) // change debug level for current message
                $level = $param;
            else if (\is_null($param)) // replicates last eol state
                $eol = $this->lastMsgHasEol;

        if ($msg === null) 
            return false;

        if ($level < $this->outSystemLevel) 
            return false;

        // input Time Stamp at beginning if last msg had LF
        if ($this->lastMsgHasEol) 
            $msg = "[" . DATE("y-m-d H:i:s") . "]$this->outSystemName " . 
                (static::isPrintable($msg) ? $msg : '(hex)' . \bin2hex($msg)); 
        
        if ($eol) 
            $msg .= $this->outSystemEolMsg;
        
        echo $msg;

        $this->lastMsgHasEol = $eol;
        return true;
    }

    public static function dstdout(...$data): bool
    {
        $msg = null;
        $eol = (SystemInteraction::isCli() ? PHP_EOL : "<br>");
        $timeStamp = 1;
        $outSystemName = "[" . self::DEFAULT_OUT_SYSTEM_NAME . "]";
        foreach ($data as $param) {
            if (is_string($param)) { // Message 
                $msg = $param;
            } else if (is_bool($param) && $param === false) { // line end disable
                $eol = false;
            } else if (is_int($param)) { // change debug level for current message
                $timeStamp = $param;
            } else if (is_array($param)) { // Custom settings
                if (isset($param['eol'])) 
                    $eol = $param['eol']; // Could set a different EOL text / char
                if (isset($param['outSystemName'])) {
                    if (is_array($param['outSystemName'])) {
                        $outSystemName = '[' . implode('][', $param['outSystemName']) . ']'; // This will help trace calls
                    } else {
                        $outSystemName = "[" . $param['outSystemName'] . "]";
                    }
                }
            }
        }

        if ($msg === null) return false;

        if ($timeStamp !== 0) 
            $msg = "[" . DATE("y-m-d H:i:s") . "]$outSystemName " . 
                (static::isPrintable($msg) ? $msg : '(hex)' . \bin2hex($msg));

        echo $msg . ($eol ? $eol : '');

        return true;
    }

    public static function helpHandleAppName(array $receivedConfig, array $myConfig): array
    {
        if (!isset($myConfig["outSystemName"]) || !isset($receivedConfig["outSystemName"])) {
            return array_merge($receivedConfig, $myConfig);
        }
        
        if (is_array($receivedConfig["outSystemName"])) {
            $receivedConfig["outSystemName"][] = $myConfig["outSystemName"];
        } else {
            $receivedConfig["outSystemName"] = [
                $receivedConfig["outSystemName"],
                $myConfig["outSystemName"]
            ];
        }

        unset($myConfig["outSystemName"]); // Just for ensurance
        return array_merge($receivedConfig, $myConfig);
    }

    public static function isPrintable(string $msg)
    {
		return true; 
		// Not working for some strings (como ç, ã ...), need to check
        return \preg_match('/^([\x09\x0A\x0D\x20-\x7E])*$/', $msg) === 1;
    }
}
?>
