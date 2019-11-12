<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use BrunoNatali\Tools\SystemInteraction;
use React\EventLoop\LoopInterface;

class EasyStorage implements EasyStorageInterface
{
    Private $fileFormart = self::AS_JSON;

    Private $saveList = [];

    Private $theSystem;

    function __construct(LoopInterface &$loop = null, SystemInteractionInterface &$sysInteraction = null)
    {
        if ($sysInteraction !== null) $this->theSystem = &$sysInteraction;
        else $this->theSystem = new SystemInteraction();

        $me = &$this;
        $this->theSystem->setFunctionOnAppAborted(function () {
            $me->save();
        });
    }

    Public function addArray(array &$array, string $name)
    {
        $hash = $this->getHash($name);
        $this->saveList[$hash] = &$array;

        return $hash;
    }


    Public function getArray(string $fileName = null, string $arrayName = null)
    {
        if ($arrayName !== null) $fileName = $this->getHash($arrayName, $fileName);

        if (!file_exists(__DIR__ . '/' . $fileName)) return false;

        $content = null;
        if (($content = file_get_contents(__DIR__ . '/' . $fileName)) === null) return false;

        if (!is_array($content = json_decode($content, true))) return false;

        return $content;
    }

    Public function save(string $hash = null): bool
    {
        if ($hash !== null) {
            return $this->saveToFile($hash);
        } else {
            $toReturn = false;
            foreach ($this->saveList as $hash => $arr) {
                $toReturn = $this->saveToFile($hash);
            }
        }
    }

    Private checkStoredFile()
    {
        //password_verify('rasmuslerdorf', $hash);
    }

    Private function getHash(string $arrayName, string $fileName = null)
    {
        if ($fileName === null) $fileName = $this->getFileName();

        eval(self::IDENTIFIER_VAR . self::EQUAL . self::DEFAULT_IDENTIFIER . self::SEMICOLON . 'return password_hash(' . self::IDENTIFIER_VAR . ', PASSWORD_DEFAULT)' . self::SEMICOLON);
    }

    Private getFileName()
    {
        return basename(__FILE__, '.php');
    }

    Private saveToFile(string $hashFileName): bool
    {
        $fp = fopen(__DIR__ . '/' . $hashFileName, 'w');
        fwrite($fp, json_encode($this->saveList[$hashFileName]));
        fclose($fp);

        if (!file_exists(__DIR__ . '/' . $hashFileName)) return false;
        return true;
    }
}
