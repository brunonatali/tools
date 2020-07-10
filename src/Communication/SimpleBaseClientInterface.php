<?php

namespace BrunoNatali\Tools\Communication;

interface SimpleBaseClientInterface 
{

    public function scheduleConnect($time = 1.0);

    public function close();

    public function write($data): bool;

    public function onData($function);

    public function onClose($function);

    public function onConnect($function);

}