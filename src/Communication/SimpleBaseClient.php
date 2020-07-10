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

    protected $outSystem;

    function __construct(...$configs)
    {
        if (!isset($this->callback['connect']))
            $this->callback['connect'] = function () { return true; };

        if (!isset($this->callback['data']))
            $this->callback['data'] = function ($data) {};

        if (!isset($this->callback['close']))
            $this->callback['close'] = function () {};

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

            
        $this->myConn->connect($this->uri)->then(
            function (ConnectionInterface $serverConn) {
                $this->serverConn = &$serverConn;
                $this->connected = true;

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
                    ($this->callback['close'])();
    
                    $this->outSystem->stdout("Connection ended", OutSystem::LEVEL_NOTICE);
                });
            
                $serverConn->on('error', function ($e) {
                    $this->outSystem->stdout("Error: " . $e->getMessage(), OutSystem::LEVEL_NOTICE);
                });
            
                $serverConn->on('close', function () {
                    ($this->callback['close'])();
    
                    $this->outSystem->stdout("Closed", OutSystem::LEVEL_NOTICE);
                });
            }, 
            function ($reason) {
                $this->outSystem->stdout('Server is not running: ' . $reason, OutSystem::LEVEL_IMPORTANT);
                $this->outSystem->stdout('Scheduling connection to 5s', OutSystem::LEVEL_NOTICE);

                $this->scheduleConnect(5.0);
            }
        );
    }

    public function scheduleConnect($time = 1.0)
    {
        $this->loop->addTimer($time, function () {
            $this->connect();
        });
    }

    public function close()
    {
        $this->outSystem->stdout('Good Bye!', OutSystem::LEVEL_NOTICE);
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

    public function onData($function)
    {
        if (!\is_callable($function)) 
            return false;

        $this->callback['data'] = $function;
        return true;
    }
    
    public function onClose($function)
    {
        if (!\is_callable($function)) 
            return false;

        $this->callback['close'] = $function;
        return true;
    }
    
    public function onConnect($function)
    {
        if (!\is_callable($function)) 
            return false;

        $this->callback['connect'] = $function;
        return true;
    }
}