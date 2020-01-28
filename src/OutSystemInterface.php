<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface OutSystemInterface
{
    Const LEVEL_ALL = 0;        // Lowest level - Output all levels on terminal is 0
    Const LEVEL_NOTICE = 1;     // Only output on terminal if message level is 3, 2 or 1
    Const LEVEL_WARNING = 2;    // Only output on terminal if message level is 3 or 2
    Const LEVEL_IMPORTANT = 3;  // Only output if message level is 3

    Const DEFAULT_OUT_SYSTEM_ENABLED = false;   // Enable or Disable debug at satartup
    Const DEFAULT_OUT_SYSTEM_EOL = true;        // Enable or Disable line ending 
    Const DEFAULT_OUT_SYSTEM_NAME = "NoName";   // Default name for Class / Function / App that called Debug
    Const DEFAULT_OUT_SYSTEM_LEVEL = self::LEVEL_ALL; // Default level to be used on msg with not explicit level

    Public function enable();
    Public function disable();
    Public function enableEol();
    Public function disableEol();
    Public function setPreferedEol($eolMsg = null);
    Public function stdout(...$data): bool;
    Public static function dstdout(...$data): bool;
    Public static function helpHandleAppName(array $receivedConfig, array $myConfig): array;
}
?>