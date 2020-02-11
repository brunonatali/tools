<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use SplQueue;
use React\EventLoop\LoopInterface;

class Queue
{
    Private $main;
    Private $loop;
    Private $timer;
    Private $timeToAnswer;
    Private $listById = [];
    Private $running = false;
    Private $enabled = false;
    Private $waitToRun = false;
    Private $lastErrorString;

    function __construct(LoopInterface &$loop, $timeToAnswer = 0)
    {
        $this->main = new SplQueue();
        $this->loop = &$loop;
        $this->timeToAnswer = $timeToAnswer;
    }

    /*
    * $retryOnError could be integer or bool, if is integer value will be decreased each time function is called
    */
    Public function listAdd(string &$id = null, $onAdd = null, $onData = null, $onError = false, $retryOnError = null)
    {
        if ($id === null ) $id = $this->genId();
        $this->listById[$id] = [
            'id' => $id,
            'onAdd' => $onAdd,
            'onData' => $onData,
            'onError' => $onError,
            'retryOnError' => (is_int($retryOnError) ? $retryOnError - 1 : $retryOnError),
            'timer' => ($this->timeToAnswer === 0 ? null : $this->loop->addTimer($this->timeToAnswer, function () use ($id){
                $this->loop->cancelTimer($this->listById[$id][ 'timer' ]);
                if (is_callable($this->listById[$id]['onError']))
                    $this->listById[$id]['onError']($params = $this->listById[$id]);
                if ($this->listById[$id]['retryOnError'])
                    $this->listAdd(
                        $this->listById[$id][ 'id' ],
                        $this->listById[$id][ 'onAdd' ],
                        $this->listById[$id][ 'onData' ],
                        $this->listById[$id][ 'onError' ],
                        $this->listById[$id][ 'retryOnError' ]
                    );
                else {
                    $this->listRemove($id);
                }
            }))
        ];
        if (is_callable($this->listById[$id]['onAdd'])) {
            echo "is callable";
            return $this->listById[$id]['onAdd']($params = $this->listById[$id]);
        }
        return true;
    }

    Public function listProccess(string $id, $data = null)
    {
        $toReturn = true;
        if (!isset($this->listById[$id])) return false;

        if ($this->listById[$id][ 'timer' ] !== null) $this->loop->cancelTimer($this->listById[$id][ 'timer' ]);
        if (is_callable($this->listById[$id]['onData']))
            $toReturn = $this->listById[$id]['onData']($data, $params = $this->listById[$id]);

        $this->listRemove($id);

        return $toReturn;
    }

    Public function listRemove(string $id)
    {
        if (isset($this->listById[$id])) {
			unset($this->listById[$id]);
			return true;
		}
		return false;
    }

    Public function getTryByListId($id)
    {
        return $this->listById[$id][ 'retryOnError' ];
    }

    Public function push($value, number $id = null)
    {
        if ($id !== null) {
            try {
                $this->main->add($id,$value);
            } catch (OutOfRangeException $e) {
                $this->lastErrorString = $e;
                return false;
            }
        } else {
            $this->main->enqueue($value);
        }

        return $this->next();
    }

	// Count could be true (run to infinite until have elements on the list, false, to run once or number of items that it must collect and parse)
    Public function next($count = true)
    {
        if ($this->main->isEmpty()) return null;
        if (!$this->enabled || $this->running) {
            $this->waitToRun = true;
            return false;
        }
		
        $queueExecuteNode = $this->main->dequeue();
        if (is_callable($queueExecuteNode)) {
            $this->running = true;
            $this->timer = $this->loop->futureTick(function () use ($queueExecuteNode, &$count){
                $queueExecuteNode();
                $this->running = false;
                $this->timer = null;
				$count --;
                if ($count) $this->next($count);
				else $this->pause();
            });
        }
        return true;
    }

	Public function nextTillEnd()
	{
		if ( !$this->next( $this->main->count() ) ) 
			$this->pause();
	}

    Public function pause()
    {
        $this->enabled = false;
    }

    Public function resume(bool $continue = true)
    {
        $this->enabled = true;
        if ($this->waitToRun) {
            $this->waitToRun = false;
            if ($continue) $this->next();
        }
    }

    Public function isIdInTheList($id)
    {
        return isset($this->listById[$id]);
    }

    Private function genId(): int
    {
        while(isset($this->listById[$temp = rand()])) {}
        return $temp;
    }
}
