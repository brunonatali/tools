<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface DebugInterface
{
    Const LEVEL_ALL = 0;    // Lowest level - Output all levels on terminal is 0
    Const LEVEL_NOTICE = 1  // Only output on terminal if message level is 3, 2 or 1
    Const LEVEL_WARNING = 2 // Only output on terminal if message level is 3 or 2
    Const LEVEL_IMPORTANT = 3 // Only output if message level is 3

    Private function appConfigure(array $config): void;
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