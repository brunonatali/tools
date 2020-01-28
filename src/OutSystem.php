<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

//use React\EventLoop\LoopInterface; // LoopInterface will be used to save log file using debug

class OutSystem implements OutSystemInterface
{
    Private $outSystemEnabled = self::DEFAULT_OUT_SYSTEM_ENABLED;
    Private $outSystemLevel = self::LEVEL_ALL; // Construct debug with all levels enabled
    Private $outSystemEol = self::DEFAULT_OUT_SYSTEM_EOL; 
    Private $outSystemEolMsg = null; // Eol to append - Auto configure at construct or direct call 
    Private $outSystemName = self::DEFAULT_OUT_SYSTEM_NAME; 
    Private $defaultLevel = self::DEFAULT_OUT_SYSTEM_LEVEL; 
    Private $lastMsgHasEol = true;

    //function __construct(array $config = [], LoopInterface &$loop = null)
    function __construct(array &$config = [])
    {
        $this->outSystemEolMsg = (SystemInteraction::isCli() ? PHP_EOL : "<br>");   
        $this->appConfigure($config);
    }

    Private function appConfigure(array &$config): void
    {
        foreach($config as $name => $value){
            $name = strtoupper(trim($name));
            if (is_string($value)) $value = trim($value);
			switch($name){
                case "OUTSYSTEMENABLED":
                    if (!is_bool($value)) throw new Exception( "Configuration '$name' must be boolean.");
                    $this->outSystemEnabled = $value;
                    unset($config[$name]);
                    break;
                case "OUTSYSTEMLEVEL":
                    if (!is_int($value)) throw new Exception( "Configuration '$name' must be integer.");
                    $this->outSystemLevel = $value;
                    unset($config[$name]);
                    break;
                case "OUTSYSTEMEOL":
                    if (!is_bool($value)) throw new Exception( "Configuration '$name' must be boolean.");
                    $this->outSystemEol = $value;
                    unset($config[$name]);
                    break;
                case "OUTSYSTEMEOLMSG":
                    if (!is_string($value)) throw new Exception( "Configuration '$name' must be string.");
                    $this->outSystemEolMsg = $value;
                    unset($config[$name]);
                    break;
                case "OUTSYSTEMNAME":
                    if (!is_array($value) && !is_string($value)) throw new Exception( "Configuration '$name' must be string or array of strings.");
                    if (is_array($value)) {
                        $this->outSystemName = '[' . implode('][', $value) . ']'; // This will help trace calls
                    } else {
                        $this->outSystemName = "[$value]";
                    }
                    unset($config[$name]);
                    break;
            }
        }
    }

    Public function enable()
    {
        $this->outSystemEnabled = true;
    }

    Public function disable()
    {
        $this->outSystemEnabled = false;
    }

    Public function enableEol()
    {
        $this->outSystemEol = true;
    }

    Public function disableEol()
    {
        $this->outSystemEol = false;
    }

    Public function setPreferedEol($eolMsg = null)
    {
        $this->outSystemEolMsg = $eolMsg;
    }

    Public function stdout(...$data): bool
    {
        if (!$this->outSystemEnabled) return false;

        $msg = null;
        $eol = $this->outSystemEol;
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
        if ($level < $this->outSystemLevel) return false;

        if ($this->lastMsgHasEol) $msg = "[" . DATE("y-m-d H:i:s") . "]$this->outSystemName " . $msg; // input Time Stamp at beginning if last msg had LF
        if ($eol) $msg .= $this->outSystemEolMsg;
        echo $msg;

        $this->lastMsgHasEol = $eol;
        return true;
    }

    Public static function dstdout(...$data): bool
    {
        $msg = null;
        $eol = (SystemInteraction::isCli() ? PHP_EOL : "<br>");
        $timeStamp = 1;
        $outSystemName = self::DEFAULT_OUT_SYSTEM_NAME;
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

        if ($timeStamp !== 0) $msg = "[" . DATE("y-m-d H:i:s") . "]$outSystemName " . $msg;
        echo $msg . ($eol ? $eol : '');
        return true;
    }

    Public static function helpHandleAppName(array $receivedConfig, array $myConfig): array
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
}
?>
