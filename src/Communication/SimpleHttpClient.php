<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use BrunoNatali\Tools\OutSystem;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;
use Clue\React\HttpProxy\ProxyConnector;

/**
 * Configurations are:
 * 
*/
class SimpleHttpClient implements SimpleHttpClientInterface
{
    private $loop = null;

    private $client = null;

    private $onAnswer = null;

    private $config = [];

    private $underProcessRequests = [];

    protected $outSystem;

    function __construct(...$configs)
    {
        /**
         * Construct app configs
        */
        foreach ($configs as $config) 
            if ($config instanceof LoopInterface)
                $this->loop = $config;
            else if (\is_array($config))
                $this->config += $config;
            else if (\is_string($config))
                $clientName = $config;
            else if (\is_numeric($config))
                $this->config['timeout'] = $config;

        // Declare unset parameters
        $this->config += [
            'proxy' => null, // '127.0.0.1:8080'
            'dns' => null,
            'timeout' => 0
        ];

        if ($this->loop === null)
            $this->loop = Factory::create();

        $config = OutSystem::helpHandleAppName( 
            $this->config,
            [
                "outSystemName" => (isset($clientName) ? $clientName : 'HTTP-C')
            ]
        );
        $this->outSystem = new OutSystem($config);
    }

    public function start(bool $startLoop = true)
    {
        if ($this->config['proxy'] !== null) {
            $proxy = new ProxyConnector($this->config['proxy'], new Connector($this->loop));

            $connector = new Connector($this->loop, array(
                'tcp' => $proxy,
                'dns' => false
            ));

            $this->client = new Browser($this->loop, $connector);
        } else if ($this->config['dns'] !== null) {
            $connector = new Connector($this->loop, [
                'dns' => $this->config['dns']
            ]);

            $this->client = new Browser($this->loop, $connector);
        } else {
            $this->client = new Browser($this->loop);
        }

        if ($startLoop)
            $this->loop->run();
    }

    /**
     * Returns false or Request ID that could be canceled
    */
    public function request(string $url, callable $onAnswer = null, ...$params)
    {
        if ($this->client === null) {
            $this->outSystem->stdout("Start application first", OutSystem::LEVEL_NOTICE);
            return false;
        }

        $requestParams = [ 
            'header' => [],
            'method' => null,
            'type' => null
        ];
        
        foreach ($params as $value) 
            if (\is_string($value)) 
                $requestParams['body'] = $value;
            else if (\is_array($value))
                $requestParams['header'] += $value;
            else if (\is_callable($value))
                $onError = $value;
            else if (\is_integer($value))
                switch ($value) {
                    case self::CLIENT_REQUEST_METHOD_GET:
                        $requestParams['method'] = self::CLIENT_REQUEST_METHOD_GET;
                        break;
                    case self::CLIENT_REQUEST_METHOD_POST:
                        $requestParams['method'] = self::CLIENT_REQUEST_METHOD_POST;
                        break;
                    case self::CLIENT_REQUEST_TYPE_TEXT:
                        $requestParams['type'] = self::CLIENT_REQUEST_TYPE_TEXT;
                        break;
                    case self::CLIENT_REQUEST_TYPE_JSON:
                        $requestParams['type'] = self::CLIENT_REQUEST_TYPE_JSON;
                        break;
                    
                    default:
                        $this->outSystem->stdout("Wrong request method/type", OutSystem::LEVEL_NOTICE);
                        return false;
                        break;
                }

        if (isset($requestParams['header']['body'])) {
            if (\is_array($requestParams['header']['body'])) {
                $requestParams['body'] = \json_encode($requestParams['header']['body']);

                if ($requestParams['type'] === null)
                    $requestParams['type'] = self::CLIENT_REQUEST_JSON;

            } else if (isset($requestParams['body'])) {
                $requestParams['body'] .= (string) $requestParams['header']['body'];
            } else {
                $requestParams['body'] = (string) $requestParams['header']['body'];
            }

            if (\strlen($requestParams['body'])) {
                if ($requestParams['method'] === self::CLIENT_REQUEST_METHOD_GET) {
                    $this->outSystem->stdout("Error using GET while body is not empty", OutSystem::LEVEL_NOTICE);
                    return false;
                }

                $requestParams['method'] = self::CLIENT_REQUEST_METHOD_POST;
            }

            unset($requestParams['header']['body']);
        }

        if ($requestParams['type'] !== null) {
            if ($requestParams['type'] === self::CLIENT_REQUEST_TYPE_TEXT)
                $requestParams['header']['Content-Type'] = 'text/html; charset=utf-8';
            else if ($requestParams['type'] === self::CLIENT_REQUEST_TYPE_JSON)
                $requestParams['header']['Content-Type'] = 'application/json';
        }

        if ($onAnswer === null) {
            if ($this->onAnswer === null) 
                $this->outSystem->stdout("Answer will be discarded", OutSystem::LEVEL_NOTICE);
            else
                $onAnswer = $this->onAnswer;
        }

        if (!isset($onError))
            $onError = function (\Exception $e) {
                $this->outSystem->stdout('There was an error', $e->getMessage(), OutSystem::LEVEL_NOTICE);
            };

        $myRequestId = $this->getNextAvailableProcRequestId();

        if ($requestParams['method'] === null ||
            $requestParams['method'] === self::CLIENT_REQUEST_METHOD_GET) {
            $this->underProcessRequests[$myRequestId] = [ 'promise' =>
                $this->client->get(
                    $url, 
                    (!empty($requestParams['header']) ? $requestParams['header'] : [])
                )->then(
                    function (ResponseInterface $response) use ($onAnswer, $myRequestId) {
                        $headers = $response->getHeaders();
                        $body = (string) $response->getBody();

                        if (isset($headers['Content-Type']) &&
                            $headers['Content-Type'] == 'application/json') {
                            $jsonBody = \json_decode($body, true);
                            
                            if (\is_array($jsonBody) && !empty($jsonBody)) 
                                $body = $jsonBody;
                        }

                        if (isset($this->underProcessRequests[$myRequestId]['timer']))
                            $this->loop->cancelTimer($this->underProcessRequests[$myRequestId]['timer']);
                        unset($this->underProcessRequests[$myRequestId]);

                        ($onAnswer)($headers, $body);
                    },
                    function (\Exception $e) use ($onError, $myRequestId) {
                        if (isset($this->underProcessRequests[$myRequestId]['timer']))
                            $this->loop->cancelTimer($this->underProcessRequests[$myRequestId]['timer']);
                        unset($this->underProcessRequests[$myRequestId]);
                        ($onError)($e);
                    }
                )
            ];
        } else {
            $this->underProcessRequests[$myRequestId] = [ 'promise' =>
                $this->client->post(
                    $url, 
                    (!empty($requestParams['header']) ? $requestParams['header'] : []),
                    (isset($requestParams['body']) ? $requestParams['body'] : '')
                )->then(
                    function (ResponseInterface $response) use ($onAnswer, $myRequestId) {
                        $headers = $response->getHeaders();
                        $body = (string) $response->getBody();
    
                        if (isset($headers['Content-Type']) &&
                            $headers['Content-Type'] == 'application/json') {
                            $jsonBody = \json_decode($body, true);
                            
                            if (\is_array($jsonBody) && !empty($jsonBody)) 
                                $body = $jsonBody;
                        }

                        if (isset($this->underProcessRequests[$myRequestId]['timer']))
                            $this->loop->cancelTimer($this->underProcessRequests[$myRequestId]['timer']);
                        unset($this->underProcessRequests[$myRequestId]);
    
                        ($onAnswer)($headers, $body);
                    },
                    function (\Exception $e) use ($onError, $myRequestId) {
                        if (isset($this->underProcessRequests[$myRequestId]['timer']))
                            $this->loop->cancelTimer($this->underProcessRequests[$myRequestId]['timer']);
                        unset($this->underProcessRequests[$myRequestId]);
                        ($onError)($e);
                    }
                )
            ];
        }

        if ($this->config['timeout'])
            $this->underProcessRequests[$myRequestId]['timer'] =
                $this->loop->addTimer($this->config['timeout'], function () use ($myRequestId) {
                    if (isset($this->underProcessRequests[$myRequestId]) &&
                        \is_object($this->underProcessRequests[$myRequestId]['promise'])) {
                        $this->underProcessRequests[$myRequestId]['promise']->cancel();
                        unset($this->underProcessRequests[$myRequestId]);
                    }
                });

        return $myRequestId;
    }

    public function setGlobalOnAnswer(callable $onAnswer)
    {
        $this->onAnswer = $onAnswer;
    }

    public function abort(int $id): bool
    {
        if (!isset($this->underProcessRequests[$id]))
            return false;

        if (!\is_object($this->underProcessRequests[$id]['promise'])) {
            unset($this->underProcessRequests[$id]);
            return false;
        } 
            
        $this->underProcessRequests[$id]['promise']->cancel();
        if (isset($this->underProcessRequests[$id]['timer']))
            $this->loop->cancelTimer($this->underProcessRequests[$id]['timer']);
        unset($this->underProcessRequests[$id]);

        return true;
    }

    private function getNextAvailableProcRequestId(): int
    {
        $id = \count($this->underProcessRequests);

        while (isset($this->underProcessRequests[$id]))
            $id ++;

        return $id;
    }
}