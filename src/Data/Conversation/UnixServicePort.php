<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Data\Conversation;

use BrunoNatali\Tools\Communication\SimpleUnixServer; 

use BrunoNatali\Tools\OutSystem;

class UnixServicePort implements \BrunoNatali\Tools\ConventionsInterface
{
    public $server;

    private $parsers = [];

    public $info = null;

    function __construct(&$loop, string $name, array $config = [])
    {
        $config += [
            "outSystemName" => 'USP', // UnixServicePort
            "outSystemEnabled" => true, // Enable stdout 
            "autoStart" => false, // disable auto start
            "serializeData" => true // prevent data concatenation enabled
        ];

        $this->server = new SimpleUnixServer(
            $loop, 
            \trim($name) . '-service.sock', 
            $config
        );

        $this->server->onData(function ($data, $id) {
            $data = \json_decode($data, true);

            if (!\is_array($data) || !isset($data['ident']))
                $this->server->disconnectClient($id); // Molformed data causes client disconnection

            $result = $this->parseData($data, $id);
            
            if (\is_string($result) && $result != '')
                $this->server->write($result, $id);
            else if (\is_bool($result))
                if($result)
                    $this->serverAnswerAck($id);
                else
                    $this->serverAnswerNack($id);
        });

        $outSystem = new OutSystem($config);

        if ($config['autoStart'])
            $this->server->start();
    }

    public function start()
    {
        $this->server->start();
    }

    private function parseData(array $data, int $id)
    {
        foreach ($this->parsers as $key => $parser) {
            if ($data['ident'] === $parser['ident']) {
                $myServer = &$this->server;
                return ($parser['callback'])(
                    (isset($data['content']) ? $data['content'] : []), 
                    $id, 
                    $myServer
                );
            }
        }

        return false;
    }

    public function addParser(int $parserIdentifier, callable $callback)
    {
        if(!isset($this->parsers[ $parserIdentifier ])) {
            $this->parsers[ $parserIdentifier ] = [
                'ident' => $parserIdentifier,
                'callback' => $callback
            ];

            return true;
        }

        return false;
    }

    public function serverAnswerAck($id)
    {
        $answer = [
            'ident' => self::DATA_TYPE_ACK
        ];

        if ($this->info !== null) {
            $answer['info'] = $this->info;
            $this->info = null;
        }

        $this->server->write(\json_encode($answer), $id);
    }

    public function serverAnswerNack($id)
    {
        $answer = [
            'ident' => self::DATA_TYPE_NACK
        ];

        if ($this->info !== null) {
            $answer['info'] = $this->info;
            $this->info = null;
        }

        $this->server->write(\json_encode($answer), $id);
    }
}