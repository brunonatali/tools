<?php

namespace BrunoNatali\Tools\Communication;

interface SimpleHttpClientInterface 
{

    const CLIENT_REQUEST_METHOD_GET = 0X10;
    const CLIENT_REQUEST_METHOD_POST = 0X11;

    const CLIENT_REQUEST_TYPE_TEXT = 0X20;
    const CLIENT_REQUEST_TYPE_JSON = 0X21;

    public function start();

    public function request(string $url, callable $onAnswer = null, ...$params);

    public function setGlobalOnAnswer(callable $onAnswer);

    public function abort(int $id): bool;
}