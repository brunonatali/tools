<?php

namespace BrunoNatali\Tools\Communication;

interface SimpleBaseClientInterface 
{

    public function scheduleConnect($time = 1.0);

    public function close();

    public function write($data): bool;

    public function onData(callable $function);

    public function onConnect(callable $function);

    public function onTimeOut(callable $function);
    
    public function onClose(callable $function);

}