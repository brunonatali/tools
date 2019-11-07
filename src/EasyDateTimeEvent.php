<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

class EasyDateTimeEvent
{
    Private $loop;

    Private $dayFuncArr = [];
    Private $dayFuncArrIndex = 0; // Will increment in function and never will be zeroe
    Private $dayFuncTimer = null;



    function __construct(LoopInterface &$loop, $timeToAnswer = 0)
    {
        $this->loop = &$loop;
    }

    Public function addDayTrigger(callable $func): int
    {
        $today = date("d");

        $this->dayFuncArr[ ++$this->dayFuncArrIndex ] = $func;
        return $this->dayFuncArrIndex;
    }

    Public function removeDayTrigger(int $index): bool
    {
        if (!isset($this->dayFuncArr[$index])) return false;
        unset($this->dayFuncArr[$index]);
        return true;
    }

    Private function addTimer(&$var, float $time, bool $periodic = true)
    {
        $time = round($time, 2);
        if ($periodic) {
            $this->loop-addPeriodicTimer($time, function () {
                
            });
        }
    }
}
?>
