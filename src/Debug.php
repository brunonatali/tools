<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

//use React\EventLoop\LoopInterface; // LoopInterface will be used to save log file using debug

class Debug implements DebugInterface
{
    Private $debugEnabled = self::DEFAULT_DEBUG_ENABLED;
    Private $debugLevel = self::LEVEL_ALL; // Construct debug with all levels enabled
    Private $debugEol = self::DEFAULT_DEBUG_EOL; 
    Private $debugEolMsg = null; // Eol to append - Auto configure at construct or direct call 
    Private $debugName = self::DEFAULT_DEBUG_NAME; 
    Private $defaultLevel = self::DEFAULT_DEBUG_LEVEL; 
    Private $lastMsgHasEol = true;

    //function __construct(array $config = [], LoopInterface &$loop = null)
    function __construct(array $config = [])
    {
        $this->debugEolMsg = (BrunoNatali\Tools\SystemInteraction::isCli() ? PHP_EOL : "<br>");   
        $this->appConfigure($config);
    }

    Private function appConfigure(array $config): void
    {
        foreach($config as $name => $value){
            $name = strtoupper(trim($name));
            $value = trim($value);
			switch($name){
                case "DEBUGENABLED":
                    if (!is_bool($value)) throw new Exception( "Configuration '$name' must be boolean.");
                    $this->debugEnabled = $value;
                    break;
                case "DEBUGLEVEL":
                    if (!is_int($value)) throw new Exception( "Configuration '$name' must be integer.");
                    $this->debugLevel = $value;
                    break;
                case "DEBUGEOL":
                    if (!is_bool($value)) throw new Exception( "Configuration '$name' must be boolean.");
                    $this->debugEol = $value;
                    break;
                case "DEBUGEOLMSG":
                    if (!is_string($value)) throw new Exception( "Configuration '$name' must be string.");
                    $this->debugEolMsg = $value;
                    break;
                case "DEBUGNAME":
                    if (!is_array($value) && !is_string($value)) throw new Exception( "Configuration '$name' must be string or array of strings.");
                    if (is_array($value)) {
                        $this->debugName = '[' . implode('][', $value) . ']'; // This will help trace calls
                    } else {
                        $this->debugName = "[$value]";
                    }
                    break;
            }
        }
    }

    Public function enable()
    {
        $this->debugEnabled = true;
    }

    Public function disable()
    {
        $this->debugEnabled = false;
    }

    Public function enableEol()
    {
        $this->debugEol = true;
    }

    Public function disableEol()
    {
        $this->debugEol = false;
    }

    Public function setPreferedEol($eolMsg = null)
    {
        $this->debugEolMsg = $eolMsg;
    }

    Public function stdout(...$data): bool
    {
        if (!$this->debugEnabled) return;

        $msg = null;
        $eol = $this->debugEol;
        $level = $this->defaultLevel;
        foreach ($data as $param) {
            if (is_string($param)) { // Message 
                $msg = $param;
            } else if (is_bool($param)) { // line end enable / disable
                $eol = $param;
            } else if (is_int($param)) { // change debug level for current message
                $level = $param;
            }
        }

        if ($msg === null) return false;
        if ($level < $this->debugLevel) return false;

        if ($this->lastMsgHasEol) $msg = "[" . DATE("y-m-d H:i:s") . "]$debugName " . $msg; // input Time Stamp at beginning if last msg had LF
        if ($eol) $msg .= $this->debugEolMsg;
        echo $msg;

        $this->lastMsgHasEol = $eol;
        return true;
    }

    Public static function dstdout(...$data): bool
    {
        $msg = null;
        $eol = (BrunoNatali\Tools\SystemInteraction::isCli() ? PHP_EOL : "<br>");
        $timeStamp = 1;
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
            }
        }

        if ($msg === null) return false;

        if ($timeStamp !== 0) $msg = "[" . DATE("y-m-d H:i:s") . "]$debugName " . $msg;
        echo $msg . ($eol ? $eol : '');
        return true;
    }

    Public static function helpHandleAppName(array $receivedConfig, array $myConfig): array
    {
        if (!isset($myConfig["debugName"]) || !isset($receivedConfig["debugName"])) {
            return array_merge($receivedConfig, $myConfig);
        }
        
        if (is_array($receivedConfig["debugName"])) {
            $receivedConfig["debugName"][] = $myConfig["debugName"];
        } else {
            $receivedConfig["debugName"] = [
                $receivedConfig["debugName"],
                $myConfig["debugName"]
            ]
        }

        unset($myConfig["debugName"]); // Just for ensurance
        return array_merge($receivedConfig, $myConfig);
    }
}
?>
