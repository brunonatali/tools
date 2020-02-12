<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface SysInfoInterface extends ConstantsInterface
{

    /**
    * input [filters], true (show header), removable filters (string)
    */
    Public static function netstat(...$imputs): array;

    /**
    * input [filters], true (show header)
    */
    Public static function ps(...$imputs): array;

    /**
    * input [filters], true (show cmd arguments)
    * output string of command or array with all match commands
    */
    Public static function getCommandByProcName(...$imputs);

    /**
    * output self::SYSTEM_TYPE_{UNIX/WIN}
    */
    Public static function getSystemType(): int;
}