<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use SplQueue;
use React\EventLoop\LoopInterface;

class Queue
{
    Private $main;
    Private $loop;
    Private $timer;

    Private $listById = [];
    Private $timeToAnswer = 0;

    Private $running = false;
    Private $enabled = false;
    Private $waitToRun = false;

    Private $lastErrorString;

    protected $outSystem;

    function __construct(...$configs)
    {
        $this->main = new SplQueue();
        $config = [];
        $info = [];

        foreach ($configs as $param) {
            if (is_array($param))
                $config += $param;
            else if ($param instanceof LoopInterface)
                $this->loop = $param;
            else if (\is_int($param))
                $this->timeToAnswer = $param;
        }

        if ($this->timeToAnswer === 0 && isset($config['answer_timeout'])) {
            if (!\is_int($config['answer_timeout']))

            $this->timeToAnswer = $config['answer_timeout'];
        }

        $config = OutSystem::helpHandleAppName( 
            $config,
            [
                "outSystemName" => 'QUEUE'
            ]
        );
        $this->outSystem = new OutSystem($config);
    }

    /*
    * $retryOnError could be integer or bool, if is integer value will be decreased each time function is called
    */
    public function listAdd(
        &$id = null, 
        callable $onAdd = null, 
        callable $onData = null, 
        callable $onError = null, 
        int $retryOnError = null,
        int $timeout = 0
    ) {
        if ($id === null ) 
            $id = $this->genId();

        if (isset($this->listById[$id]))
            $this->listRemove($id);
        
        $this->listById[$id] = [
            'id' => $id,
            'on_add' => $onAdd,
            'on_data' => $onData,
            'on_error' => $onError,
            'retry_on_error' => $retryOnError,
            'timer' => null
        ];

        $timeout = ($timeout > 0 ? $timeout : $this->timeToAnswer);

        if ($timeout > 0)
            $this->listById[$id]['timer'] = $this->loop->addTimer(
                $timeout, 
                function () use ($id, $timeout) {
                    $this->listById[$id]['timer'] = null;

                    if ($this->listById[$id]['on_error'])
                        ($this->listById[$id]['on_error'])($this->listById[$id]);
                        $this->listRemove($id);

                    /**
                     * Check on_error functions that removes / unset id from the main list
                    */
                    if (isset($this->listById[$id]) && $this->listById[$id]['retry_on_error']) {
                        $this->listById[$id]['retry_on_error'] --;

                        $this->listAdd(
                            $this->listById[$id]['id'],
                            $this->listById[$id]['on_add'],
                            $this->listById[$id]['on_data'],
                            $this->listById[$id]['on_error'],
                            $this->listById[$id]['retry_on_error'],
                            $timeout
                        );
                    } else {
                        $this->listRemove($id);
                    }
                }
            );

        $this->outSystem->stdout("ADD: $id", OutSystem::LEVEL_ALL);

        if ($onAdd)
            return ($onAdd)($this->listById[$id]);

        return true;
    }

    public function listProcess( $id, $data = null)
    {
        if (!isset($this->listById[$id])) 
            return false;

        $toReturn = true;

        $this->outSystem->stdout("PROCESS: $id", OutSystem::LEVEL_ALL);

        if ($this->listById[$id]['timer'] !== null) 
            $this->loop->cancelTimer($this->listById[$id]['timer']);

        if ($this->listById[$id]['on_data'])
            $toReturn = ($this->listById[$id]['on_data'])($data, $this->listById[$id]);

        $this->listRemove($id);

        return $toReturn;
    }

    public function listRemove($id): bool
    {
        if (isset($this->listById[$id])) {
            $this->outSystem->stdout("REMOVE: $id", OutSystem::LEVEL_ALL);

            if ($this->listById[$id]['timer'] !== null)
                $this->loop->cancelTimer($this->listById[$id]['timer']);

			unset($this->listById[$id]);
			return true;
        }
        
		return false;
    }

    public function getTryByListId(int $id)
    {
        if (!isset($this->listById[$id]))
            return false;

        return $this->listById[$id]['retry_on_error'];
    }

    Public function push($value, int $id = null)
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
        if ($this->main->isEmpty()) 
            return null;
            
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
            if ($continue) 
                $this->next();
        }
    }

    public function empty(int $max = 100): bool
    {
        if ($this->main->isEmpty()) 
            return true;

        $empty = false;
        while ($max--) {
            try {
                $this->main->dequeue();
            } catch (\Exception $e) {
                $empty = true; // Throw when is empty
                $max = 0;
            }
        }

        return $empty;
    }

    Public function isIdInTheList($id)
    {
        return isset($this->listById[$id]);
    }

    private function genId(): int
    {
        while (isset($this->listById[$temp = \random_int()])) {}
        return $temp;
    }
}
