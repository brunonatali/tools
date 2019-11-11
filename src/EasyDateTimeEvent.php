<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

class EasyDateTimeEvent
{
    Private $loop;

    Private $dayFuncArrIndex = 0; // Will increment in function and never will be zeroe
    Private $dayFuncArr = [];
    Private $dayFuncVars = [
        'timer' => null,
        'today' => null
    ];
    Private $hourFuncArrIndex = 0; // Will increment in function and never will be zeroe
    Private $hourFuncArr = [];
    Private $hourFuncVars = [
        'timer' => null,
        'now' => null
    ];

    Private $t[
        'minute' => 60,
        'hour' => 3600,
        'day' => 86400
    ];

    function __construct(LoopInterface &$loop)
    {
        $this->loop = &$loop;
    }

    Public function addDayTrigger(callable $func = null): int
    {
        if ($func !== null) $this->dayFuncArr[ ++$this->dayFuncArrIndex ] = $func;

        if ($this->dayFuncVars['timer'] === null) {
            $this->dayFuncVars['today'] = date("d");

            $time = $this->getSecondsTillDayEnd();
            echo "addDayTrigger ---> $time" . PHP_EOL;

            $me = &$this;
            $this->addTimer($this->dayFuncTimer, $time, function () use ($me) {
                if ($me->dayFuncVars['today'] === date("d") && (date("i") != 59 || date("s") < 59)) {
                    $me->addDayTrigger();
                    return;
                }
                foreach ($me->dayFuncArr as $dayFunc) $dayFunc();
                $me->addDayTrigger();
            },
            false);
        } else {
            $this->loop->cancelTimer($this->dayFuncVars['timer']);
            $this->addDayTrigger();
        }

        return $this->dayFuncArrIndex;
    }

    Public function addHourTrigger(callable $func = null): int
    {
        if ($func !== null) $this->hourFuncArr[ ++$this->hourFuncArrIndex ] = $func;

        if ($this->hourFuncVars['timer'] === null) {
            $this->hourFuncVars['now'] = date("d");

            $time = $this->getSecondsTillHourEnd();
            echo "addHourTrigger ---> $time" . PHP_EOL;

            $me = &$this;
            $this->addTimer($this->dayFuncTimer, $time, function () use ($me) {
                if ($me->hourFuncVars['now'] === date("H") && (date("i") != 59 || date("s") < 59)) {
                    $me->addHourTrigger();
                    return;
                }
                foreach ($me->hourFuncArr as $hourFunc) $hourFunc();
                $me->addHourTrigger();
            },
            false);
        } else {
            $this->loop->cancelTimer($this->hourFuncVars['timer']);
            $this->addHourTrigger();
        }

        return $this->hourFuncArrIndex;
    }

    Public function removeDayTrigger(int $index): bool
    {
        if (!isset($this->dayFuncArr[$index])) return false;
        unset($this->dayFuncArr[$index]);
        return true;
    }

    Public function removeHourTrigger(int $index): bool
    {
        if (!isset($this->hourFuncArr[$index])) return false;
        unset($this->hourFuncArr[$index]);
        return true;
    }

    Public function getSecondsTillDayEnd(): int
    {
        $now = time();
        $time = $t['day'] - (date("H") * $t['hour']) - (date("i") * $t['minute']) - date("s");
        $time += $now - time(); // calculate drift time
        return $time;
    }

    Public function getSecondsTillHourEnd(): int
    {
        $now = time();
        $time = $t['hour'] - (date("i") * $t['minute']) - date("s");
        $time += $now - time(); // calculate drift time
        return $time;
    }

    Public function getSecondsTillMinuteEnd(): int
    {
        return $t['minute'] - date("s");
    }

    Private function addTimer(&$timer, float $time, callable $func, bool $periodic = true)
    {
        $time = round($time, 2);
        if ($periodic) {
            $timer = $this->loop-addPeriodicTimer($time, $func);
        } else {
            $timer = $this->loop-addTimer($time, $func);
        }
    }
}
?>
