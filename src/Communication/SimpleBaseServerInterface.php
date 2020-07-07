<?php

namespace BrunoNatali\Tools\Communication;

interface SimpleBaseServerInterface 
{
    public function onClose($funcOrId = null): bool;

    public function onData($funcOrData, $id = null);

    public function write($data, $id = null): bool;

    public function getClientsId(): array;

}