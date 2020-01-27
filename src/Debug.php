<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

class Debug implements DebugInterface
{
    Private $enabled = true;
    Private $level = self::LEVEL_ALL; // Current debug level
    Private $defaultLevel = self::LEVEL_ALL; // Default level to be used on msg with not explicit level
    Private $eol = true; // Put line ending 
    Private $eolMsg = null; // Eol to append
    Private $lastMsgHasEol = true;

    //function __construct(array $config = [], LoopInterface &$loop = null)
    function __construct(array $config = [])
    {
        foreach ($config as $key => $value) 
            if (isset($this->$key)) $this->$key = $this->$value
    }

    Public function enable()
    {
        $this->enabled = true;
    }

    Public function disable()
    {
        $this->enabled = false;
    }

    Public function enableEol()
    {
        $this->eol = true;
    }

    Public function disableEol()
    {
        $this->eol = false;
    }

    Public function setPreferedEol($eolMsg = null)
    {
        $this->eolMsg = $eolMsg;
    }

    Public function stdout(...$data): bool
    {
        if (!$this->enabled) return;

        $msg = null;
        $eol = $this->eol;
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
        if ($level < $this->level) return false;

        if ($this->lastMsgHasEol) $msg = "[" . DATE("y-m-d H:i:s") . "] " . $msg; // input Time Stamp at beginning if last msg had LF
        if ($eol) $msg .= ($this->eolMsg ? $this->eolMsg : PHP_EOL);
        echo $msg;

        $this->lastMsgHasEol = $eol;
        return true;
    }

    Public static function dstdout(...$data): bool
    {
        $msg = null;
        $eol = PHP_EOL;
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

        if ($timeStamp !== 0) $msg = "[" . DATE("y-m-d H:i:s") . "] " . $msg;
        echo $msg . ($eol ? $eol : '');
        return true;
    }
}
?>
