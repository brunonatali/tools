<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

class Scheduler
{
    Private $scheduled = false;
    Private $loop;
    Private $list = [];

    /** 
    * Add some inputs to make an easy call when start class
    * @input LoopInterface
    * @input Time - If 0 nothing is scheduled
    * @input Function to execute on Time
    * @input (non necessary) [callable] Function to be executed on error 
    * @input (non necessary) [bool] Infinite timer or not
    */
    function __construct(LoopInterface &$loop, float $time = 0, callable $execFunction = null, ...$args)
    {
        $this->loop = &$loop;
        $this->schedule($time, $execFunction, $args);
    }

    /** 
    * @input Time - If 0 nothing is scheduled
    * @input Function to execute on Time
    * @input (non necessary) [callable] Function to be executed on error 
    * @input (non necessary) [bool] Infinite timer or not
    * @return false if error or integer if is schedduled
    */
    Public function schedule(float $time = 0, callable $execFunction = null, ...$args)
    {
        if ((!$time || $time < 0) || !is_callable($execFunction) || count($args) > 2) return false;
        $loopType = "addTimer";
        $errorFunction = null;
        foreach ($args as $arg) 
            if (is_bool($arg) && $arg) $loopType = "addPeriodicTimer";
            else if (is_callable($arg)) $errorFunction = $arg;
        
        $listIndex = count($this->list);
        $this->list[$listIndex] = $this->loop->$loopType(
            $time, 
            ($errorFunction === null ? $execFunction : function () use ($execFunction, $errorFunction) { if ($execFunction() === false) $errorFunction();})
        );

        return $listIndex;
    }

    /** 
    * @input Index of desired timer to be canceled or NULL to cancel all timers
    * @return true if could cancel a timer or if have no timers 
    */
    Public function cancel($listIndex = null): bool
    {
        if (is_int($listIndex) && isset($this->list[$listIndex])) {
            $this->loop->cancelTimer($this->list[$listIndex]);
            return true;
        } else if ($listIndex === null) {
            foreach ($this->list as $timer) $this->loop->cancelTimer($timer);
            return true;
        }
        return false;
    }
}