<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface SystemInteractionInterface
{
    Const SYSTEM_RUN_FOLDER_UNIX = ['/run/systema/', '/var/run/systema/'];
    Const SYSTEM_RUN_FOLDER_WIN = ['%TEMP%/systema/'];

    Const SYSTEM = [
        'UNIX' = 0x01,
        'WIN' = 0x02
    ]

    Public function setAppInitiated(string $appName, bool $regShdn = true);

    Public function setAborted();

    Public function getAppInitiated(string $appName);

    Public function setFunctionOnAppAborted(callable $func);

    Public function getSystem();
}
?>
