<?php declare(strict_types=1);

namespace BrunoNatali\Tools\Communication;

use BrunoNatali\Tools\OutSystem;
use BrunoNatali\Tools\Queue;

use React\EventLoop\LoopInterface;
use React\Datagram\Socket;

class Ping implements PingInterface
{
    private $loop;
    private $socket = null;

    private $rawSocket;

    private $pingId;
    private $pingSequence = 0;

    private $info;

    private $queue;

    protected $outSystem;

    function __construct(...$configs)
    {
        $config = [];
        $info = [];

        foreach ($configs as $param) {
            if (is_array($param))
                $config += $param;
            else if ($param instanceof LoopInterface)
                $this->loop = $param;
        }

        if ($this->loop === null)
            throw new \Exception("Provide LoopInterface", 1);

        $this->pingId = \str_pad(dechex(
            ($tmpPingId = \intval($config['ping_id'] ?? \random_int(0xFF, 0xFFFE))) > 0 ? 
                $tmpPingId : 
                \random_int(0xFF, 0xFFFE)
        ), 4, "0", STR_PAD_LEFT);

        $config = OutSystem::helpHandleAppName( 
            $config,
            [
                "outSystemName" => 'PING'
            ]
        );
        $this->outSystem = new OutSystem($config);

        $this->queue = new Queue($this->loop, ($config['timeout'] ?? 0), $config);
    }

    public function send(
        string $destination, 
        int $timeout = 0, 
        $count = 1, 
        callable $onResponse = null,
        callable $onError = null
    ): bool {
        if ($this->socket === null) {
            $this->socket = $this->createIcmpV4();

            $this->socket->on('message', function ($chunk) {
                if ($this->checkReceived($chunk)) {
                    $this->queue->listProcess($chunk['sequence'], $chunk);

                    if (($destination = $this->getDestinationBySequence($chunk['sequence'])) !== null) {}
                        $this->cancel($destination);
                } else {
                    $this->outSystem->stdout(
                        "Repply data error for: " . ($chunk['sequence'] ?? 'unknow'), 
                        OutSystem::LEVEL_NOTICE
                    );

                    if (($destination = $this->getDestinationBySequence($chunk['sequence'])) !== null) {
                        $count = $this->info[$destination]['count'];
                        
                        if ($this->info[$destination]['on_error'])
                            ($this->info[$destination]['on_error'])();

                        /* Could not call send again yet
                            if (\is_int($count))
                                $count --;
                                
                            if ($count)
                                $this->send($destination, $timeout, $count, $onResponse, $onError);
                            else
                                $this->cancel($destination);
                        */
                    }
                } 
            });
        }

        // Remove / Empty any old request
        $this->cancel($destination);

        $this->info[$destination] = [
            'sequence' => $this->getNextSequence(),
            'on_error' => $onError,
            'count' => $count
        ];

        $this->queue->listAdd(
            $this->pingSequence,
            null, // On add
            function ($dataInfo) use ($destination, $timeout, $count, $onResponse, $onError) { // On Response
                $this->outSystem->stdout(
                    'Received ' . $dataInfo['total_len'] . ' bytes from: ' . 
                    $dataInfo['source'] . ' seq: ' . $dataInfo['sequence'] .
                    ' time: ' . (string) (\microtime(true) - $this->info[$destination]['time']), 
                    OutSystem::LEVEL_NOTICE
                );

                if ($onResponse)
                    ($onResponse)($dataInfo);

                if (\is_int($count))
                    $count --;
                    
                if ($count)
                    $this->send($destination, $timeout, $count, $onResponse, $onError);
            },
            function ($sequence) use ($destination, $timeout, $count, $onResponse, $onError) { // On error
                $this->outSystem->stdout("Repply timeout: $timeout for: $sequence[id]", OutSystem::LEVEL_NOTICE);

                if ($onError)
                    ($onError)();

                if (\is_int($count))
                    $count --;
                    
                if ($count)
                    $this->send($destination, $timeout, $count, $onResponse, $onError);
            },
            null,
            $timeout
        );

        $pkg = $this->genPingPacket(self::ICMP_TYPE_ECHO_REQUEST, self::ICMP_CODE_ECHO);

        $this->outSystem->stdout("Send id: $this->pingSequence count: $count to: $destination ", false, OutSystem::LEVEL_NOTICE);

        if ($this->sendRaw($pkg, $destination) !== false) {
            $this->info[$destination]['time'] = \microtime(true);
            $this->outSystem->stdout("OK",OutSystem::LEVEL_NOTICE);

            return true;
        } else {
            $this->outSystem->stdout("ERROR",OutSystem::LEVEL_NOTICE);

            return false;
        }
    }
    
    public function createIcmpV4()
    {
        $this->outSystem->stdout("Creating socket (v4)...",OutSystem::LEVEL_NOTICE);

        $this->rawSocket = @\socket_create(AF_INET, SOCK_RAW, \getprotobyname('icmp'));
        
        if ($this->rawSocket === false) 
            throw new \Exception("Unable to create socket", 2);
            
        if (!\is_resource($sock = \socket_export_stream($this->rawSocket)))
            throw new \Exception("Unable to export socket", 3);
            
        return new Socket($this->loop, $sock);
    }

    private function sendRaw(string $data, string $destination)
    {
        return \socket_sendto($this->rawSocket, $data, \strlen($data), 0, $destination, 0);
    }

    private function genPingPacket(int $type, int $code, string $data = null)
    {
        $calcType = \str_pad(dechex($type), 2, "0", STR_PAD_LEFT);
        $calcCode = \str_pad(dechex($code), 2, "0", STR_PAD_LEFT);
        $calcId = \str_pad(dechex($this->pingId), 4, "0", STR_PAD_LEFT);
        $calcSequencie = \str_pad(dechex($this->pingSequence), 4, "0", STR_PAD_LEFT);

        if (!$data)
            $data = 'ABCDEFGHIJKLMN0PQRST'; //20
            
        $checkSum = $this->calcCheckSum(
            $calcType . $calcCode . $calcId . $calcSequencie . \bin2hex($data)
        );

        return \hex2bin($calcType . $calcCode . $checkSum . $calcId . $calcSequencie) . $data;
    }

    private function checkReceived(string &$data): bool
    {
        if (($totalLen = \strlen($data)) < 28) {
            $this->outSystem->stdout("Chk: Min len", null, OutSystem::LEVEL_NOTICE);
            return false;
        }

        $splitData = \str_split(\bin2hex(\substr($data, 0, 28)), 4);
        $payload = \substr($data, 28);

        $data = [
            'total_len' => $totalLen
        ];

        // $ipv4Header = \hexdec($splitData[0]);
        $data['len'] = \hexdec($splitData[1]);
        // $identification = \hexdec($splitData[2]);
        // $flags = \hexdec($splitData[3]);
        // $ttl = \hexdec($splitData[4][0] . $splitData[4][1]);
        // $protocol = \hexdec($splitData[4][2] . $splitData[4][3]);
        // $headerChecksum = \hexdec($splitData[5]);
        $data['source'] = \hexdec($splitData[6][0] . $splitData[6][1]) . '.' . 
            \hexdec($splitData[6][2] . $splitData[6][3]) . '.' .
            \hexdec($splitData[7][0] . $splitData[7][1]) . '.' .
            \hexdec($splitData[7][2] . $splitData[7][3]);
        $data['destination'] = \hexdec($splitData[8][0] . $splitData[8][1]) . '.' . 
            \hexdec($splitData[8][2] . $splitData[8][3]) . '.' .
            \hexdec($splitData[9][0] . $splitData[9][1]) . '.' .
            \hexdec($splitData[9][2] . $splitData[9][3]);
        $data['type'] = \hexdec($splitData[10][0] . $splitData[10][1]);
        $data['code'] = \hexdec($splitData[10][2] . $splitData[10][3]);
        $data['checkSum'] = \hexdec($splitData[11]);
        $data['id'] = \hexdec($splitData[12]);
        $data['sequence'] = \hexdec($splitData[13]);

        return true;
    }

    private function calcCheckSum(string $hexString): string
    {
        $sum = 0;

        foreach (\str_split($hexString, 4) as $frac) // 4 -> 0xFFFF (16 bits)
            $sum += \hexdec($frac);
        
        $result = (~ $sum) & 0xFFFF; // $base = 0xFFFF;

        return \str_pad(dechex($result - 2), 4, "0", STR_PAD_LEFT); // Need to check why calc is 2 +
    }

    private function cancel(string $destination): bool
    {
        if (!isset($this->info[$destination]))
            return false;
        
        $this->queue->listRemove($this->info[$destination]['sequence']);

        unset($this->info[$destination]);
        return true;
    }

    private function getDestinationBySequence(int $sequence)
    {
        foreach ($this->info as $destination => $value) {
            if ($value['sequence'] === $sequence)
                return $destination;
        }

        return null;
    }

    private function getNextSequence(): int
    {
        if (++$this->pingSequence > 0xFFFF)
            $this->pingSequence = 1;

        return $this->pingSequence;
    }
}