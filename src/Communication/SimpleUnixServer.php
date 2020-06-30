<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use React\Socket\UnixServer as Server;
use React\Socket\ConnectionInterface;

use BrunoNatali\Tools\Queue;
use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

class SimpleUnixServer implements SimpleUnixServerInterface
{
    private $loop;
    private $sock;
    private $server;
    private $socketPath;
    Protected $outSystem;

    private $clientConn = []; // Handle all clients info
    private $callback = [];

    /**
     * LoopInterface
     * Socket name 'mySock.sock'
    */
    function __construct(&$loop, $sock, ...$configs)
    {
        $this->loop = &$loop;
        $this->sock = $sock;

        $this->callback = [
            'connect' => function ($id) {},
            'data' => function ($data, $id) {},
            'close' => function ($id) {}
        ];

        $serverName = 'SUS';
        $userConfig = [];
        foreach ($configs as $param) {
            if (is_string($param))
                $serverName = $param;
            else if (is_array($param))
                $userConfig += $param;
        }
        $config = OutSystem::helpHandleAppName( 
            $userConfig,
            [
                "outSystemName" => $serverName,
                "outSystemEnabled" => true
            ]
        );
        $this->outSystem = new OutSystem($config);
    }

    public function start()
    {
        self::checkSocketFolder();
        $this->socketPath = self::checkSocket($this->sock);

        $this->server = new Server($this->socketPath, $this->loop);
        \chmod($this->socketPath, 0777);

        $this->server->on('connection', function (ConnectionInterface $connection) {

            $myId = (int) $connection->stream; // Use stream ID as own ID

            $this->clientConn[$myId] = [
                'active' => true,
                'conn' => &$connection,
                'queue' => new Queue($this->loop),
                'dataHandler' => new DataManipulation()
            ];
            $this->outSystem->stdout("New client connection: $myId", OutSystem::LEVEL_NOTICE);
                
            $connection->on('data', function ($data) use ($myId) { 

                $this->outSystem->stdout("Raw data ($myId): '$data'", OutSystem::LEVEL_NOTICE);

                while (($data = $this->clientConn[$myId]['dataHandler']->simpleSerialDecode($data)) !== null) {

                    $this->outSystem->stdout("Parsed data ($myId): " . $this->onData($data, $myId), OutSystem::LEVEL_NOTICE);

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
            $this->outSystem->stdout('Main connection Error: ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
        });
        
        $this->outSystem->stdout('Listening on: ' . $this->server->getAddress(), OutSystem::LEVEL_NOTICE);
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
            $this->callback['close']($funcOrId);
        }
        return true;
    }

    public function onData($funcOrData, $id = null)
    {
        if (\is_callable($funcOrData)) {
            $this->callback['data'] = $funcOrData;
            return true;
        }
        
        if (!isset($this->clientConn[$id]))
            return false;
        
        return $this->callback['data']($funcOrData, $id);
    }

    public function write($data, $id = null): bool
    {
        if ($id !== null) {
            if (isset($this->clientConn[$id])) {
                $this->clientConn[$id]['conn']->write(DataManipulation::simpleSerialEncode($data));
                return true;
            }
        } else {
            foreach ($this->clientConn as $id => $client) {
                $client['conn']->write(DataManipulation::simpleSerialEncode($data));
            }

            return count($this->clientConn) !== 0;
        }

        return false;
    }

    private function removeClient($id)
    {
        if (isset($this->clientConn[$id])) {
            if ($this->clientConn[$id]['active'])
                $this->clientConn[$id]->close();

            unset($this->clientConn[$id]);
        }
    }

    public static function checkSocketFolder(): bool
    {
        if (!\file_exists(self::SOCK_FOLDER)) {
            \mkdir(self::SOCK_FOLDER, 0755, true );
            \chmod(self::SOCK_FOLDER, 0755);
            return false;
        }
        return true;
    } 

    public static function checkSocket($sockName)
    {
        $path = self::SOCK_FOLDER . $sockName;
        if (\file_exists($path)) \unlink($path);

        return $path;
    }
}