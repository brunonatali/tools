<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use React\Socket\ConnectionInterface;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

class SimpleBaseServer implements SimpleBaseServerInterface
{
    protected $loop;
    protected $server;
    protected $userConfig;

    protected $clientConn = []; // Handle all clients info
    protected $callback = [];

    Protected $outSystem;

    /**
     * LoopInterface
     * Socket name 'mySock.sock'
    */
    function __construct(...$configs)
    {
        if (!isset($this->callback['connect']))
            $this->callback['connect'] = function ($id) { return true; };

        if (!isset($this->callback['data']))
            $this->callback['data'] = function ($data, $id) {};

        if (!isset($this->callback['close']))
            $this->callback['close'] = function ($id) {};

        $serverName = 'SBS'; // SimpleBaseServer
        $this->userConfig = [];
        foreach ($configs as $param) {
            if (is_string($param))
                $serverName = $param;
            else if (is_array($param))
                $this->userConfig += $param;
        }
        $config = OutSystem::helpHandleAppName( 
            $this->userConfig,
            [
                "outSystemName" => $serverName
            ]
        );
        $this->outSystem = new OutSystem($config);

        $this->userConfig += [ 
            'serializeData' => false // Serialization is disabled by default
        ];

        $this->server->on('connection', function (ConnectionInterface $connection) {

            $myId = (int) $connection->stream; // Use stream ID as own ID

            if (!$this->onConnect($myId, $connection)) {
                $this->outSystem->stdout("Client connection rejected: $myId", OutSystem::LEVEL_NOTICE);
                $connection->close();
                return;
            }

                
            $connection->on('data', function ($data) use ($myId) {

                $this->outSystem->stdout("Data -raw- ($myId): $data", OutSystem::LEVEL_NOTICE);

                if ($this->userConfig['serializeData']) {
                    // Push raw data and get deserialized one
                    $data = $this->clientConn[$myId]['dataHandler']->simpleSerialDecode($data); 
    
                    while ($data !== null) {
                        $this->outSystem->stdout("Data -parsed- ($myId): $data", OutSystem::LEVEL_NOTICE);
                        
                        $this->onData($data, $myId);

                        // Just get buffered data
                        $data = $this->clientConn[$myId]['dataHandler']->simpleSerialDecode();
                    }
                } else {
                    $this->onData($data, $myId);
                }
            });
        
            $connection->on('end', function () use ($myId) {
                if (!$this->onClose($myId))
                    return;

                $this->outSystem->stdout("Client ($myId) connection ended", OutSystem::LEVEL_NOTICE);
                $this->removeClient($myId);
            });
        
            $connection->on('error', function ($e) use ($myId) {
                $this->outSystem->stdout("Client ($myId) connection Error: " . $e->getMessage(), OutSystem::LEVEL_NOTICE);
                $this->removeClient($myId);
            });
        
            $connection->on('close', function () use ($myId) {
                if (!$this->onClose($myId))
                    return;

                $this->outSystem->stdout("Client ($myId) connection closed", OutSystem::LEVEL_NOTICE);
                $this->removeClient($myId);
            });
        });
        
        $this->server->on('error', function (Exception $e) {
            $this->outSystem->stdout('Main server connection Error: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
        });
    }

    public function onConnect($funcOrId = null, &$connection = null): bool
    {
        if (\is_callable($funcOrId) && !\is_integer($funcOrId)) {
            $this->callback['connect'] = $funcOrId;
            return true;
        }
        
        if ($connection === null)
            return false;

        if (($this->callback['connect'])($funcOrId) === true) {
            $this->clientConn[$funcOrId] = [
                'active' => true,
                'conn' => &$connection,
                'dataHandler' => (
                    $this->userConfig['serializeData'] ? 
                    new DataManipulation() : 
                    null
                ) 
            ];

            return true;
        }

        return false;
    }

    public function onClose($funcOrId = null): bool
    {
        if (\is_callable($funcOrId)) {
            $this->callback['close'] = $funcOrId;
            return true;
        }
        
        if (!isset($this->clientConn[$funcOrId]))
            return false;

        if ($this->clientConn[$funcOrId]['active']) {
            $this->clientConn[$funcOrId]['active'] = false;
            ($this->callback['close'])($funcOrId);
        }
        return true;
    }

    public function onData($funcOrData, $id = null)
    {
        if (\is_callable($funcOrData) && !is_string($funcOrData)) { // char "_" is identified as callable
            $this->callback['data'] = $funcOrData;
            return true;
        }

        if (!isset($this->clientConn[$id]))
            return false;
            
        return ($this->callback['data'])($funcOrData, $id);
    }

    public function write($data, $id = null): bool
    {
        if ($id !== null) {
            if (\is_array($id)) {
                $this->outSystem->stdout("Write (array): $data", OutSystem::LEVEL_NOTICE);

                $ret = true;
                foreach ($id as $cId) {
                    if (isset($this->clientConn[$cId]))
                        $ret = $ret && $this->write($data, $cId);
                    else 
                        $ret = false;
                    
                    $this->outSystem->stdout("Write to ($cId)", OutSystem::LEVEL_NOTICE);
                }

                return $ret;
            } else {
                if (isset($this->clientConn[$id])) {
                    $this->outSystem->stdout("Write ($id): $data", OutSystem::LEVEL_NOTICE);

                    $this->clientConn[$id]['conn']->write((
                        $this->userConfig['serializeData'] ?
                        DataManipulation::simpleSerialEncode($data) :
                        $data
                    ));
                    return true;
                }
            }
        } else {
            $this->outSystem->stdout("Write (all): $data", OutSystem::LEVEL_NOTICE);

            foreach ($this->clientConn as $id => $client) {
                $this->outSystem->stdout("Write to ($id)", OutSystem::LEVEL_NOTICE);

                $client['conn']->write((
                    $this->userConfig['serializeData'] ?
                    DataManipulation::simpleSerialEncode($data) :
                    $data
                ));
            }

            return count($this->clientConn) !== 0;
        }

        $this->outSystem->stdout("Write (ERROR): $data", OutSystem::LEVEL_NOTICE);
        return false;
    }

    public function disconnectClient($id = null): bool
    {
        if ($id !== null) {
            if (\is_array($id)) {
                $this->outSystem->stdout("Disconnect (array)", OutSystem::LEVEL_NOTICE);

                $ret = true;
                foreach ($id as $cId) {
                    if (isset($this->clientConn[$cId]))
                        $ret = $ret && $this->clientConn[$cId]['conn']->close();
                    else 
                        $ret = false;
                    
                    $this->outSystem->stdout("Close sent to ($cId)", OutSystem::LEVEL_NOTICE);
                }

                return $ret;
            } else {
                if (isset($this->clientConn[$id])) {
                    $this->outSystem->stdout("Close sent to ($id)", OutSystem::LEVEL_NOTICE);

                    $this->clientConn[$id]['conn']->close();
                    return true;
                }
            }
        } else {
            $this->outSystem->stdout("Close (all): $data", OutSystem::LEVEL_NOTICE);

            foreach ($this->clientConn as $id => $client) {
                $this->outSystem->stdout("Close sent to ($id)", OutSystem::LEVEL_NOTICE);

                $client['conn']->close();
            }

            return true;
        }

        $this->outSystem->stdout("Close (ERROR)", OutSystem::LEVEL_NOTICE);
        return false;
    }

    public function getClientsId(): array
    {
        $ret = [];
        foreach ($this->clientConn as $id => $client)
            $ret[] = $id;

        return $ret;
    }

    public function &getClientConnection($id)
    {
        if (isset($this->clientConn[$id])) 
            return $this->clientConn[$id]['conn'];

        return null;
    }

    public function close()
    {
        $this->server->close();
    }

    private function removeClient($id)
    {
        if (isset($this->clientConn[$id])) {
            if ($this->clientConn[$id]['active'])
                $this->clientConn[$id]['conn']->close();

            unset($this->clientConn[$id]);
        }
    }
}