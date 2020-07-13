<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use React\Socket\UnixConnector;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

final class SimpleUnixClient extends SimpleBaseClient implements SimpleUnixInterface, SimpleBaseClientInterface
{
    private $serverSock;
    /**
     * LoopInterface
     * Socket name 'mySock.sock'
    */
    function __construct(&$loop, $sock, ...$configs)
    {
        $this->loop = &$loop;
        $this->serverSock = $sock;

        $this->uri = self::SOCK_FOLDER . $this->serverSock;

        $this->userConfig = [];
        foreach ($configs as $param) {
            if (is_string($param))
                $this->clientName = $param;
            else if (is_array($param))
                $this->userConfig += $param;
        }
    }

    public function connect()
    {
        if ($this->myConn !== null) {
            $this->connectClient();
            return;
        }

        $this->myConn = new \React\Socket\UnixConnector($this->loop);

        /**
         * Configure parent
         * SUS: SimpleUnixServer
         * Serialization: true (prevent data concatenation, but need client configured too)
        */
        parent::__construct(
            (isset($this->clientName) ? $this->clientName : 'SUC'), // SimpleUnixClient
            \array_merge(['serializeData' => true], $this->userConfig)
        );
        
        $this->outSystem->stdout('Connecting to ' . $this->serverSock, OutSystem::LEVEL_NOTICE);
    }
}