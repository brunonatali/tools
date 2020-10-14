<?php

namespace BrunoNatali\Tools\Communication;

interface PingInterface 
{
    const ICMP_TYPE_ECHO_REPLY = 0x00;
    const ICMP_TYPE_DESTINATION_UNREACHABLE = 0x03;
    const ICMP_TYPE_REDIRECT = 0x05;
    const ICMP_TYPE_ECHO_REQUEST = 0x08;
    const ICMP_TYPE_TIME_EXCEEDED = 0x11;
    const ICMP_TYPE_PARAMETER_PROBLEM = 0x12;

    const ICMP_CODE_ECHO = 0x00;

    /**
     * Send ICMP echo request 
     * 
     * @param string $destination  destination IP address to ping
     * @param int $timeout  timeout on non repply in seconds
     * @param $count  number of requests to sent
     * @param callable $onResponse  function to call on response. Inputs float $time, array $dataInfo
     * @param callable $onError  function to call on timeout
     * 
     * @return bool Result of sending ICMP to the socket
    */
    public function send(
        string $destination, 
        int $timeout = 0, 
        $count = 1, 
        callable $onResponse = null,
        callable $onError = null
    ): bool;

    /**
     * Creates RAW socket to send ICMP ping and DGRAM socket to receive response
    */
    public function createIcmpV4();

    /**
     * Send ICMP echo request 
     * 
     * @param string $destination  destination IP address to ping
     * 
     * @return bool Cancelation result
    */
    public function cancel(string $destination): bool;

    /**
     * Return last Ping error message 
     * 
     * @return string error
    */
    public function getErrorMessage(): string;

    /**
     * Return last Ping error code 
     * 
     * @return int error
    */
    public function getErrorCode(): int;
}