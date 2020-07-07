<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use React\Socket\UnixConnector;
use React\Socket\ConnectionInterface;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

class SimpleUnixClient implements SimpleUnixInterface
{
    //public $queue;

    private $loop;
    private $serverSock;
    private $dataMan;
    private $userConfig;

    private $callback = [];

    private $connected = false;
    private $myConn = null;
    private $serverConn = null;

    Protected $outSystem;

    function __construct(&$loop, $serverSock, ...$configs)
    {
        $this->loop = &$loop;
        $this->serverSock = $serverSock;
        
        $this->callback = [
            'connect' => function () {},
            'data' => function ($data) {},
            'close' => function () {}
        ];

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
            'serializeData' => true
        ];

        if ($this->userConfig['serializeData'])
            $this->dataMan = new DataManipulation();
    }

    public function connect()
    {
        $me = &$this;
        $this->myConn = new \React\Socket\UnixConnector($this->loop);
        
        $this->myConn->connect(self::SOCK_FOLDER . $this->serverSock)->then(
            function (ConnectionInterface $serverConn) use ($me) {
                $me->serverConn = &$serverConn;
                $me->connected = true;

                ($this->callback['connect'])();

                $serverConn->on('data', function ($data) use ($me) {
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

                $serverConn->on('end', function () use ($me) {
                    ($me->callback['close'])();
    
                    $me->outSystem->stdout("Connection ended", OutSystem::LEVEL_NOTICE);
                });
            
                $serverConn->on('error', function ($e) use ($me) {
                    $me->outSystem->stdout("Error: " . $e->getMessage(), OutSystem::LEVEL_NOTICE);
                });
            
                $serverConn->on('close', function () use ($me) {
                    ($me->callback['close'])();
    
                    $me->outSystem->stdout("Closed", OutSystem::LEVEL_NOTICE);
                });
            }, 
            function ($reason) use ($me) {
                $this->outSystem->stdout('Server is not running: ' . $reason, OutSystem::LEVEL_IMPORTANT);
                $this->outSystem->stdout('Scheduling connection to 5s', OutSystem::LEVEL_NOTICE);

                $me->scheduleConnect(5.0);
            }
        );

    }

    public function scheduleConnect($time = 1.0)
    {
        $me = &$this;
        $this->loop->addTimer($time, function () use ($me) {
            $me->connect();
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