<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface SystemInteractionInterface extends ConstantsInterface
{
    Const SYSTEM_RUN_FOLDER_UNIX = ['/run/systema/', '/var/run/systema/'];
    Const SYSTEM_RUN_FOLDER_WIN = ['%TEMP%/systema/'];

    Const ERROR_OK = 0x00;  // No error
    Const ERROR_COULD_NOT_COMPLETE_PARSE = 0x01; // Some parse coud not be completed or code decide to abort parse
    Const ERROR_SYSTEM_NOT_SUPPORTED = 0x02; // Host system not support this function
    Const ERROR_BUSY_WITH_SAME_REQUEST = 0x03; // Resquested function already called and could not be called again

    Public function __toString();

    Public function setAppInitiated(string $appName, bool $regShdn = true);

    Public function setAborted();

    Public function getAppInitiated(string $appName = null);

    Public function getAppRequestRestart(string $appName = null);

    Public function setFunctionOnAppAborted(callable $func);

    Public function getSystem();

	Public function removePidFile();

    Public function restartApp();

    Public static function isCli(): bool;
}
?>
