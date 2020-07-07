<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use React\Socket\TcpServer as Server;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

final class SimpleTcpServer extends SimpleBaseServer implements SimpleBaseServerInterface
{
    private $ip;
    private $port;

    /**
     * LoopInterface
     * IP address 127.0.0.1 (local only) / 0.0.0.0 (everyone) 
     * Port number 
    */
    function __construct(&$loop, $ip, $port, ...$configs)
    {
        $this->loop = &$loop;

        $this->ip = $ip;
        $this->port = $port;

        $this->userConfig = [];
        foreach ($configs as $param) {
            if (is_string($param))
                $this->serverName = $param;
            else if (is_array($param))
                $this->userConfig += $param;
        }

        /**
         * backlog - Used to limit the number of outstanding connections in the socket's listen queue
         * ipv6_v6only - Overrides the OS default regarding mapping IPv4 into IPv6
         * so_reuseport - Allows multiple bindings to a same ip:port pair, even from separate processes
         * so_broadcast - Enables sending and receiving data to/from broadcast addresses
         * tcp_nodelay - Setting this option to TRUE will set SOL_TCP,NO_DELAY=1 appropriately, thus 
         *                  disabling the TCP Nagle algorithm
        */
        $this->opts = [
            'so_reuseport' => (
                isset($this->userConfig['so_reuseport']) ? 
                $this->userConfig['so_reuseport'] : 
                false
            )
        ];
    }

    public function start()
    {
        
        $this->server = new Server(
            $this->ip . ':' . $this->port, 
            $this->loop,
            $this->opts
        );

        /**
         * Configure parent
         * STS: SimpleTcpServer
         * Serialization: true (prevent data concatenation, but need client configured too)
        */
        parent::__construct(
            (isset($this->serverName) ? $this->serverName : 'STS'), 
            \array_merge(['serializeData' => false], $this->userConfig)
        );
        
        $this->outSystem->stdout('Listening on: ' . $this->server->getAddress(), OutSystem::LEVEL_NOTICE);
    }
}