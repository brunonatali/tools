<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

final class SimpleTcpClient extends SimpleBaseClient implements SimpleBaseClientInterface
{
    private $ip;
    private $port;
    /**
     * LoopInterface
     * IP IPv4 server address
     * PORT 1-65535
    */
    function __construct(&$loop, $ip, $port, ...$configs)
    {
        $this->loop = &$loop;

        // Need to check this inputs:
        $this->ip = $ip;
        $this->port = $port;

        $this->uri = $this->ip . ':' . $this->port;

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

        $this->myConn = new \React\Socket\TcpConnector($this->loop);

        /**
         * Configure parent
         * SUS: SimpleUnixServer
         * Serialization: false (data concatenation prevention is disabled by default in TCP)
        */
        parent::__construct(
            (isset($this->clientName) ? $this->clientName : 'STC'), // SimpleTcpClient
            \array_merge(['serializeData' => false], $this->userConfig)
        );
        
        $this->outSystem->stdout('Connecting to ' . $this->uri, OutSystem::LEVEL_NOTICE);
    }
}