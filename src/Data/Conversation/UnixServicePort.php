<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Data\Conversation;

use BrunoNatali\Tools\Communication\SimpleUnixServer; 

use BrunoNatali\Tools\OutSystem;

class UnixServicePort implements \BrunoNatali\Tools\ConventionsInterface
{
    public $server;

    private $parsers = [];

    public $info = null;

    protected $globalStatus = 0;

    private $globalStatusHistory;

    function __construct(&$loop, string $name, array $config = [])
    {
        $config += [
            "outSystemName" => 'USP', // UnixServicePort
            "outSystemEnabled" => true, // Enable stdout 
            "autoStart" => false, // disable auto start
            "serializeData" => true, // prevent data concatenation enabled
            "statusStore" => 10 // Store about 10 status code
        ];

        $this->server = new SimpleUnixServer(
            $loop, 
            \trim($name) . '-service.sock', 
            $config
        );

        $this->server->onData(function ($data, $id) {
            $data = \json_decode($data, true);

            if (!\is_array($data) || !isset($data['ident'])) {
                 // Molformed data causes client disconnection without warning
                $this->server->disconnectClient($id);
                return;
            }

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

        $this->globalStatusHistory = \array_fill(0, \intval($config['statusStore']), null);

        /**
         * Register some generic parsers
         * Note. This could be overridden from caller
        */
        // Send current module status 
        $this->parsers[ self::DATA_TYPE_STATUS ] = [
            'ident' => self::DATA_TYPE_STATUS,
            'callback' => function ($content, $id, $myServer) {
                return \json_encode(
                    [
                        'ident' => self::DATA_TYPE_STATUS,
                        'info' => [
                            'now' => $this->globalStatus,
                            'history' => $this->globalStatusHistory
                        ]
                    ]
                );
            }
        ];

        // Clear current module status (leaving history untouched) 
        $this->parsers[ self::ACTION_CLEAR_STATUS ] = [
            'ident' => self::ACTION_CLEAR_STATUS,
            'callback' => function ($content, $id, $myServer) {
                $this->globalStatus = 0;

                return true;
            }
        ];

        /**
         * Auto start
        */
        if ($config['autoStart'])
            $this->server->start();
    }

    public function start()
    {
        $this->server->start();
    }

    private function parseData(array $data, int $id)
    {
        if (isset($this->parsers[ $data['ident'] ])) {
            $myServer = &$this->server;

            return ($this->parsers[ $data['ident'] ]['callback'])(
                (isset($data['content']) ? $data['content'] : []), 
                $id, 
                $myServer
            );
        }

        return false;
    }

    
    public function addParser(int $parserIdentifier, callable $callback)
    {
        if (!isset($this->parsers[ $parserIdentifier ])) {
            $this->parsers[ $parserIdentifier ] = [
                'ident' => $parserIdentifier,
                'callback' => $callback
            ];

            return true;
        }

        return false;
    }

    
    public function reportStatus(int $statusCode, bool $broadcast = true, string $info = null)
    {
        $this->globalStatus = $statusCode;

        $index = null;
        foreach ($this->globalStatusHistory as $key => $value) 
            if ($value === null) {
                $index = $key;
                break;
            }

        $history = [
            'code' => $statusCode,
            'info' => $info,
            'time' => \time()
        ];

        if ($index !== null)
            $this->globalStatusHistory[$index] = $history;
        else {
            $last = count($this->globalStatusHistory);
            \array_unshift(
                $this->globalStatusHistory, 
                $history
            );
            unset($this->globalStatusHistory[$last]);
        }

        if ($broadcast)
            $this->server->write(
                \json_encode(
                    [
                        'ident' => self::DATA_TYPE_STATUS,
                        'info' => [
                            'now' => $this->globalStatus,
                            'history' => $this->globalStatusHistory
                        ]
                    ]
                )
            );
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