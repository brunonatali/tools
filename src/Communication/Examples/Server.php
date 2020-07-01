<?php declare(strict_types=1);

require __DIR__ . '/../../../../autoload.php';

use BrunoNatali\Tools\Communication\SimpleUnixServer; 

use BrunoNatali\Tools\OutSystem;
use React\EventLoop\Factory;

$config = [
    "outSystemName" => 'test',
    "outSystemEnabled" => true
];

$outSystem = new OutSystem($config);

$loop = Factory::create();

$server = new SimpleUnixServer($loop, 'test-server.sock', $config);

/**
 * Configure callbacks 
*/
$outSystem->stdout("Configuring callbacks: ", false, OutSystem::LEVEL_NOTICE);

$server->onClose(function ($id) use ($outSystem) {
    $outSystem->stdout("Close handled outside from '$id'", OutSystem::LEVEL_NOTICE);
});
$server->onData(function ($data, $id) use ($outSystem, $server) {
    $outSystem->stdout("Received '$data' from client '$id'", OutSystem::LEVEL_NOTICE);

    $server->write('ok', $id);
});

$outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

/**
 * Start server
*/
$outSystem->stdout("Starting server ...", OutSystem::LEVEL_NOTICE);
$server->start();