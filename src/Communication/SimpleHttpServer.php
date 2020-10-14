<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use BrunoNatali\Tools\OutSystem;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Server;
use React\Http\Message\Response;
use React\Stream\ThroughStream;
use Psr\Http\Message\ServerRequestInterface;

use React\Promise\PromiseInterface;
use React\Promise\Deferred;

/**
 * Configurations are:
 * 
 * - LoopInterface
 * - (string) Server name for debug
 * - (array) Configs
 *  - 'ip' => (string) Listen IP - Default 0.0.0.0
 *  - 'port' => (string) Listen Port - Default 80
 *  - 'stream' => (bool) Use stream method - Default false
 *  - 'cert' => (string) Path to cert.pem - Default null
 *  - 'on_header' => (callable) Function to process received header - Default null 
 *  - 'on_query' => (callable) Function to process received query - Default null 
 *  - 'on_server_params' => (callable) Function to process server params - Default null 
 *  - 'sequence' => (int) Constant of desired order to process header, query and server params - Default ...HQS
 * 
 * NOTE 1. For all callable functions an array is passed as argument containing:
 *  - 'header' => Received headers
 *  - 'body' => If header Content-Type is JSON, body is parsed into array, otherwise raw data
 *  - 'stream' => (only if is enabled) Stream resource to communicate directely
 * 
 * NOTE 2. For all callable functions return an bool will cause imediately close with result as follows:
 *  - true, an sucess response to requester. Default 200
 *  - false, an error response to requester. Default 400
 * You can pass custom HTTP code by setting 'code' key to previous array before return
 * 
 * WARNING: Returning an array on 'body' will cause the body to be converted to JSON and header 'Content-Type' 
 *  replaced by 'application/json'
 * 
 * NOTE 3. To work with streams ensure return null on all callable functions. You still could pass HTTP code
 *  to array by setting 'code' key
*/
class SimpleHttpServer implements SimpleHttpServerInterface
{
    private $loop = null;

    private $socket = null;

    private $server = null;

    private $config = [];

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
                $serverName = $config;

        // Declare unset parameters
        $this->config += [
            'ip' => '0.0.0.0',
            'port' => '80',
            'stream' => false,
            'cert' => null,
            'on_header' => null, // function ($content) { return null; }
            'on_query' => null, // function ($query, $content) { return null; }
            'on_server_params' => null, // function ($params, $content) { return null; }
            'sequence' => self::SERVER_HTTP_PROC_ORDER_HQS
        ];

        if ($this->loop === null)
            $this->loop = Factory::create();

        $config = OutSystem::helpHandleAppName( 
            $this->config,
            [
                "outSystemName" => (isset($serverName) ? $serverName : 'HTTP-S')
            ]
        );
        $this->outSystem = new OutSystem($config);
    }

    public function start(bool $startLoop = true)
    {
        $this->socket = new \React\Socket\Server(
            $this->config['ip'] . ':' . $this->config['port'], 
            $this->loop
        );

        if ($this->config['cert'] !== null && \file_exists($this->config['cert']))
            $this->socket = new \React\Socket\SecureServer(
                $this->socket, 
                $this->loop, 
                [
                    'local_cert' => $this->config['cert']
                ]
            );

        $this->newServer();

        $this->server->listen($this->socket);

        $this->outSystem->stdout(
            (
                $this->config['cert'] !== null ?
                'Listening on ' . str_replace('tls:', 'https:', $this->socket->getAddress()) :
                'Listening on ' . str_replace('tcp:', 'http:', $this->socket->getAddress())
            ),
            OutSystem::LEVEL_NOTICE
        );

        if ($startLoop)
            $this->loop->run();
    }

    private function newServer()
    {
        $this->server = new Server($this->loop, function (ServerRequestInterface $request) {
            
            $originalHeader = $request->getHeaders();

            $content = [
                'header' => $originalHeader,
                'attribute' => $request->getAttributes(),
                'path' => $request->getUri()->getPath(),
                'cookie' => $request->getCookieParams()
            ];

            if ($this->config['stream'])
                $content['stream'] = new ThroughStream();

            if (isset($content['header']['Content-Type'])) {
                if ($content['header']['Content-Type'][0] === 'application/json') {
                    $originalBody = \json_decode((string) $request->getBody(), true);
                } else {
                    $originalBody = (string) $request->getBody();
                }
            } else {
                $originalBody = (string) $request->getBody();
            }
            $content['body'] = $originalBody;
            
            $response = null;

            switch ($this->config['sequence']) {
                case self::SERVER_HTTP_PROC_ORDER_QHS:
                    $response = $this->procQuery($request->getQueryParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procHeader($content);
                    if ($response !== null)
                        break;
                    $response = $this->procServerParams($request->getServerParams(), $content);
                    break;
                case self::SERVER_HTTP_PROC_ORDER_QSH:
                    $response = $this->procQuery($request->getQueryParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procServerParams($request->getServerParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procHeader($content);
                    break;
                case self::SERVER_HTTP_PROC_ORDER_HQS:
                    $response = $this->procHeader($content);
                    if ($response !== null)
                        break;
                    $response = $this->procQuery($request->getQueryParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procServerParams($request->getServerParams(), $content);
                    break;
                case self::SERVER_HTTP_PROC_ORDER_HSQ:
                    $response = $this->procHeader($content);
                    if ($response !== null)
                        break;
                    $response = $this->procServerParams($request->getServerParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procQuery($request->getQueryParams(), $content);
                    break;
                case self::SERVER_HTTP_PROC_ORDER_SQH:
                    $response = $this->procServerParams($request->getServerParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procQuery($request->getQueryParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procHeader($content);
                    break;
                case self::SERVER_HTTP_PROC_ORDER_SHQ:
                    $response = $this->procServerParams($request->getServerParams(), $content);
                    if ($response !== null)
                        break;
                    $response = $this->procHeader($content);
                    if ($response !== null)
                        break;
                    $response = $this->procQuery($request->getQueryParams(), $content);
                    break;
            }
            
            if ($response instanceof PromiseInterface) {
                $deferred = new Deferred();
                
                $response->then(function ($response) use (&$deferred, &$content, $originalHeader, $originalBody) {
                    $content['body'] = $response['response'];

                    $deferred->resolve(
                        $this->handleResponse($response['result'], $content, $originalHeader, $originalBody)
                    );
                })->otherwise(function (\Exception $x) {
                    var_dump('(e1)->', $x);
                })->otherwise(function (\Exception $x) {
                    var_dump('(e2)->', $x);
                })->otherwise(function (\Exception $x) {
                    var_dump('(e3)->', $x);
                });

                return $deferred->promise();
            }

            return $this->handleResponse($response, $content, $originalHeader, $originalBody);
        });
    }

    private function handleResponse($result, array $content, $originalHeader, $originalBody)
    {
        if ($result === null) {
            if ($this->config['stream'])
                return new Response(
                    (isset($content['code']) ? isset($content['code']) : 200),
                    ['Content-Type' => 'text/plain'],
                    $stream
                );
            else
                return new Response(204);
                
        } else if (\is_bool($result)) {
            if (isset($content['header']))
                if ($content['header'] === $originalHeader)
                    $content['header'] = [];

            if (isset($content['body'])) {
                if ($content['body'] === $originalBody) {
                    $content['body'] = '';
                } else if (\is_array($content['body'])) {
                    $content['body'] = \json_encode($content['body']);
                    $content['header']['Content-Type'] = 'application/json';
                }
            }

            if ($result)
                return new Response(
                    (isset($content['code']) ? $content['code'] : 200),
                    $content['header'],
                    $content['body']
                );
            else 
                return new Response(
                    (isset($content['code']) ? $content['code'] : 418), // 418 I'm a teapot
                    $content['header'],
                    $content['body']
                );
        }

        // Server error
        return new Response(500);
    }

    private function procHeader(&$content)
    {
        if (!\is_callable($this->config['on_header']))
            return null;

        return ($this->config['on_header'])($content);
    }

    private function procQuery($query, &$content)
    {
        if (!\is_callable($this->config['on_query']))
            return null;

        return ($this->config['on_query'])($query, $content);
    }

    private function procServerParams($params, &$content)
    {
        if (!\is_callable($this->config['on_server_params']))
            return null;

        return ($this->config['on_server_params'])($params, $content);
    }

    private function answer(int $code, &$content)
    {
        return new Response(
            $code,
            (isset($content['header']) ? $content['header'] : []),
            (isset($content['body']) ? $content['body'] : '')
        );
    }
}