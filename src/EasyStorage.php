<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use BrunoNatali\Tools\SystemInteraction;
use React\EventLoop\LoopInterface;

class EasyStorage implements EasyStorageInterface
{
    Private $fileFormart = self::AS_JSON;

    Private $saveList = [];
    Private $saveListIndex = 0;

    Private $theSystem;

    function __construct(LoopInterface &$loop = null, SystemInteractionInterface &$sysInteraction = null)
    {
        if ($sysInteraction !== null) $this->theSystem = &$sysInteraction;
        else $this->theSystem = new SystemInteraction();
    }

    Public function addArray(array &$array, string $name)
    {
        $this->saveList[++$this->saveListIndex] =
    }

    Public function save()
    {

    }
}
