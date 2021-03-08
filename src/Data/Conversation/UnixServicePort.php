<?php declare(strict_types=1);

/**
 * UnixServicePort foi criado para facilitar a comunicação entre serviços
 * padronizando a comunicação e facilitando a implementação de protocolos
*/

namespace BrunoNatali\Tools\Data\Conversation;

use BrunoNatali\Tools\Communication\SimpleUnixServer; 

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\OutSystemInterface;

use React\EventLoop\LoopInterface;

class UnixServicePort implements \BrunoNatali\Tools\ConventionsInterface
{
    /**
     * @var SimpleUnixServer class
    */
    public $server;

    /**
     * @var array Handle registered parsers list
    */
    private $parsers = [];

    /**
     * @var array Handle channels 
    */
    private $channel = [];

    /**
     * @var array Handle channel client subscribers
    */
    private $subscribers = [];

    public $info = null;

    private $globalStatus = 0;

    private $globalStatusHistory;

    /**
     * @param LoopInterface $loop created trought React\EventLoop\Factory::create();
     * @param string $serviceName Desired socket name (-service.sock will be added at the end)
     * @param array $config App configuration
     *  Configurations are:
     *      outSystemName - Displayed name on debug (default USP [Unix Service Port])
     *      outSystemEnabled - Enable debug output (default true)
     *      outSystemLevel - Debug output level. Follow BrunoNatali\Tools\OutSystemInterface
     *          (Default is show all debug info [LEVEL_NOTICE])
     *      autoStart - Tell if listen socket will be automatic started (default false)
     *      serializeData - When enabled, this configuration prevents data to be crawled to 
     *          late and concatenation ocurs, losing package.
     *      statusStore - Amount of status stored (default 10)
    */
    function __construct(LoopInterface &$loop, string $serviceName, array $config = [])
    {
        $config += [
            "outSystemName" => 'USP',
            "outSystemEnabled" => true,
            "outSystemLevel" => OutSystemInterface::LEVEL_NOTICE,
            "autoStart" => false,
            "serializeData" => true,
            "statusStore" => 10
        ];

        $this->server = new SimpleUnixServer(
            $loop, 
            \trim($serviceName) . '-service.sock', 
            $config
        );

        $this->server->onData(function ($data, $id) {
            $data = \json_decode($data, true);

            if (!\is_array($data) || !isset($data['ident'])) {
                 // Molformed data causes client disconnection without warning
                $this->server->disconnectClient($id);
                return;
            }

            /**
             * Channel subscriber can only subscribe to other channel or unsubscribe from current
             * 
             * Unsubscribe before make other request type
             * */ 
            if (isset($this->subscribers[$id])) {
                if ($data['ident'] === self::ACTION_UNSUBSCRIBE)
                    return $this->clientUnsubscribeFromChannel($this->subscribers[$id], $id);
                else if ($data['ident'] !== self::ACTION_SUBSCRIBE)
                    return false;
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

        $this->server->onClose(function ($id) {
            if (isset($this->subscribers[$id])) 
                $this->clientUnsubscribeFromChannel($this->subscribers[$id], $id);
        });

        $outSystem = new OutSystem($config);

        $this->globalStatusHistory = \array_fill(0, \intval($config['statusStore']), null);

        /**
         * Register some generic parsers
         * Note. This could be overridden by caller after construct
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

        // Let client to subscribe to one channel 
        $this->parsers[ self::ACTION_SUBSCRIBE ] = [
            'ident' => self::ACTION_SUBSCRIBE,
            'callback' => function ($content, $id, $myServer) {
                if (isset($content['channel'])) 
                    return $this->clientSubscribeToChannel($content['channel'], $id);

                return false;
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
        $this->parsers[ $parserIdentifier ] = [
            'ident' => $parserIdentifier,
            'callback' => $callback
        ];
    }

    public function addChannel(string $channelName, callable $callback)
    {
        $this->channel[ $channelName ] = [
            'subscribers' => [],
            'callback' => $callback
        ];
    }

    public function emitChannel(string $channelName, string $content)
    {
        if (!isset($this->channel[$channelName]))
            return false;

        foreach ($this->channel[$channelName]['subscribers'] as $subscriber)
            $this->server->write($content, $subscriber);

        return true;
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

    public function getGlobalStatus()
    {
        return $this->globalStatus;
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

    private function clientSubscribeToChannel(string $channelName, $clientId): bool
    {
        if (!isset($this->channel[$channelName]))
            return false;

        if (isset($this->subscribers[$clientId]))
            if ($this->subscribers[$clientId] !== $channelName)
                $this->clientUnsubscribeFromChannel($this->subscribers[$clientId], $clientId);
            else
                return true; // Already subscribed to channel

        $this->subscribers[$clientId] = $channelName;
        $this->channel[$channelName]['subscribers'][$clientId] = $clientId;
        return true;
    }

    private function clientUnsubscribeFromChannel(string $channelName, $clientId): bool
    {
        if (!isset($this->channel[ $channelName ]))
            return false;

        unset($this->channel[$channelName]['subscribers'][$clientId]);
        unset($this->subscribers[$clientId]);
        return true;
    }
}