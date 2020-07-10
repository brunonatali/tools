<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use React\Socket\UnixServer as Server;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\DataManipulation;

final class SimpleUnixServer extends SimpleBaseServer implements SimpleUnixInterface, SimpleBaseServerInterface
{
    private $sock;
    private $socketPath;
    /**
     * LoopInterface
     * Socket name 'mySock.sock'
    */
    function __construct(&$loop, $sock, ...$configs)
    {
        $this->loop = &$loop;
        $this->sock = $sock;

        $this->userConfig = [];
        foreach ($configs as $param) {
            if (is_string($param))
                $this->serverName = $param;
            else if (is_array($param))
                $this->userConfig += $param;
        }
    }

    public function start()
    {
        self::checkSocketFolder();
        $this->socketPath = self::checkSocket($this->sock);

        $this->server = new Server($this->socketPath, $this->loop);
        \chmod($this->socketPath, 0777);

        /**
         * Configure parent
         * SUS: SimpleUnixServer
         * Serialization: true (prevent data concatenation, but need client configured too)
        */
        parent::__construct(
            (isset($this->serverName) ? $this->serverName : 'SUS'), 
            \array_merge(['serializeData' => true], $this->userConfig)
        );
        
        $this->outSystem->stdout('Listening on: ' . $this->server->getAddress(), OutSystem::LEVEL_NOTICE);
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