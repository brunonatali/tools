<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

class EasyDateTimeEvent
{
    Private $loop;

    Private $dayFuncArr = [];
    Private $dayFuncArrIndex = 0; // Will increment in function and never will be zeroe
    Private $dayFuncVars = [
        'timer' => null,
        'today' => null
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

    Public function removeDayTrigger(int $index): bool
    {
        if (!isset($this->dayFuncArr[$index])) return false;
        unset($this->dayFuncArr[$index]);
        return true;
    }

    Public function getSecondsTillDayEnd(): int
    {
        $now = time();
        $time = $t['day'] - (date("H") * $t['hour']) - (date("i") * $t['minute']) - date("s");
        $time += $now - time(); // calculate drift time
        return $time;
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
