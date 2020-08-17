<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use React\Socket\ConnectionInterface;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

class SimpleBaseClient implements SimpleUnixInterface
{
    //public $queue;

    protected $loop;
    protected $dataMan;
    protected $userConfig;

    protected $uri;

    protected $callback = [];

    protected $connected = false;
    protected $myConn = null;
    protected $serverConn = null;

    protected $forcedClose = false;
    protected $reconnectionScheduled = null;
    protected $cancelTimer = null;
    protected $connPromise = null;

    protected $outSystem;

    function __construct(...$configs)
    {
        if (!isset($this->callback['connect']))
            $this->callback['connect'] = function () { return true; };

        if (!isset($this->callback['data']))
            $this->callback['data'] = function ($data) {};

        if (!isset($this->callback['close']))
            $this->callback['close'] = function () {};

        if (!isset($this->callback['timeout']))
            $this->callback['timeout'] = function () {};

        $clientName = 'SUC'; // SimpleUnixClient
        $this->userConfig = [];
        foreach ($configs as $param) {
            if (is_string($param))
                $clientName = $param;
            else if (is_array($param))
                $this->userConfig += $param;
        }
        $config = OutSystem::helpHandleAppName( 
            $this->userConfig,
            [
                "outSystemName" => $clientName
            ]
        );
        $this->outSystem = new OutSystem($config);

        $this->userConfig += [ 
            'serializeData' => false
        ];

        if ($this->userConfig['serializeData'])
            $this->dataMan = new DataManipulation();

            
        $this->connectClient();
    }

    protected function connectClient()
    {
        $this->connPromise = null;

        $this->cancelTimer = $this->loop->addTimer(10.0, function () {
            $this->outSystem->stdout('Server did not respond in 10s', OutSystem::LEVEL_NOTICE);

            $this->connPromise->cancel();

            $this->scheduleConnect(5.0);
        });

        $this->connPromise = $this->myConn->connect($this->uri)->then(
            function (ConnectionInterface $serverConn) {
                $this->serverConn = &$serverConn;
                $this->connected = true;

                // Reset auxiliar vars
                $this->forcedClose = false;
                if ($this->cancelTimer !== null) {
                    $this->loop->cancelTimer($this->cancelTimer);
                    $this->cancelTimer = null;
                }

                $this->outSystem->stdout('Connected', OutSystem::LEVEL_IMPORTANT);
                ($this->callback['connect'])();

                $serverConn->on('data', function ($data) {
                    if ($this->userConfig['serializeData']) {
                        $getData = true;
                        $data = $this->dataMan->simpleSerialDecode($data);
    
                        while ($getData) {
                            if ($data !== null) {
                                ($this->callback['data'])($data);
                                $data = $this->dataMan->simpleSerialDecode();
                            } else {
                                $getData = false;
                            }
                        }
                    } else {
                        ($this->callback['data'])($data);
                    }
                });

                $serverConn->on('end', function () {
                    $this->connected = false;

                    ($this->callback['close'])();

                    if (!$this->forcedClose)
                        $this->scheduleConnect(5.0);
    
                    $this->outSystem->stdout("Connection ended", OutSystem::LEVEL_NOTICE);
                });
            
                $serverConn->on('error', function ($e) {
                    $this->outSystem->stdout("Error: " . $e->getMessage(), OutSystem::LEVEL_NOTICE);
                });
            
                $serverConn->on('close', function () {
                    $this->connected = false;

                    ($this->callback['close'])();

                    if (!$this->forcedClose)
                        $this->scheduleConnect(5.0);
    
                    $this->outSystem->stdout("Closed", OutSystem::LEVEL_NOTICE);
                });
            }
        )->otherwise(
            function ($reason) {
                $this->outSystem->stdout(
                    'Server is not running: ' . $reason->getMessage(), 
                    OutSystem::LEVEL_IMPORTANT
                );

                $this->scheduleConnect(5.0);
            }
        )->otherwise(
            function ($e) {
                //var_dump($e->getMessage());
                $this->outSystem->stdout("Some other error received on connect. Uncoment line " . ((int) __LINE__ - 1), 
                    OutSystem::LEVEL_NOTICE
                );
            }
        );
    }

    public function scheduleConnect($time = 1.0)
    {
        if ($this->reconnectionScheduled !== null)
            return;

        $this->outSystem->stdout('Scheduling connection to 5s', OutSystem::LEVEL_NOTICE);

        ($this->callback['timeout'])();

        $this->reconnectionScheduled = 
            $this->loop->addTimer($time, function () {
            $this->reconnectionScheduled = null;
            $this->connectClient();
        });
    }

    public function close()
    {
        $this->forcedClose = true;

        $this->outSystem->stdout('Good Bye!', OutSystem::LEVEL_NOTICE);

        if ($this->reconnectionScheduled !== null) {
            $this->loop->cancelTimer($this->reconnectionScheduled);
            return; // If is trying to connect dont need to disconnect
        }

        // Cancel / close connection attempt
        if ($this->cancelTimer !== null) {
            $this->loop->cancelTimer($this->cancelTimer);
            $this->cancelTimer = null;

            $this->connPromise->cancel();
        }

        if ($this->connected)
            $this->serverConn->close();
    }

    public function write($data): bool
    {
        if (!$this->connected) return false;

        $this->outSystem->stdout('Write: ' . $data, OutSystem::LEVEL_NOTICE);

        $this->serverConn->write((
            $this->userConfig['serializeData'] ?
            DataManipulation::simpleSerialEncode($data) :
            $data
        ));
        
        return true;
    }

    public function onData(callable $function)
    {
        $this->callback['data'] = $function;
    }
    
    public function onConnect(callable $function)
    {
        $this->callback['connect'] = $function;
    }
    
    public function onTimeOut(callable $function)
    {
        $this->callback['timeout'] = $function;
    }
    
    public function onClose(callable $function)
    {
        $this->callback['close'] = $function;
    }
}