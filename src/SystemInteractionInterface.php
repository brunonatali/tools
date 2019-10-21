<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface SystemInteractionInterface
{
    // This will only work under unix.
    // Need to be implemented something like: if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
    Const SYSTEM_RUN_FOLDER = ['/run/systema/', '/var/run/systema/'];
}
?>
