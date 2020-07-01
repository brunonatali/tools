<?php declare(strict_types=1);

require __DIR__ . '/../../../../autoload.php';

use BrunoNatali\Tools\Communication\SimpleUnixClient; 

use BrunoNatali\Tools\OutSystem;
use React\EventLoop\Factory;

$config = [
    "outSystemName" => 'test',
    "outSystemEnabled" => true
];

$outSystem = new OutSystem($config);

$loop = Factory::create();

$client = new SimpleUnixClient($loop, 'test-server.sock', $config);

/**
 * Configure callbacks 
*/
$outSystem->stdout("Configuring callbacks: ", false, OutSystem::LEVEL_NOTICE);

$client->onClose(function () use ($outSystem) {
    $outSystem->stdout("Close handled outside", OutSystem::LEVEL_NOTICE);
});
$client->onData(function ($data) use ($outSystem) {
    $outSystem->stdout("Answer received from server", OutSystem::LEVEL_NOTICE);
});
$client->onConnect(function () use ($outSystem, $client, $loop) {
    $outSystem->stdout("Connected. Starting test ...", OutSystem::LEVEL_NOTICE);

    /**
     * Imediatelly write
     * 
     * Many write calls at once, using a for loop (like example), cause concatenating.
     * An simple method is implemented to prevent data lost due to this issue, but 
     * all data still post / receiving at same time.
     * A better handle to this is using BrunoNatali\Tools\Queue
    */
    for ($i=0; $i < 5; $i++) { 
        $client->write('Lorem Ipsum');
    }

    /**
     * Call write after 5 seconds AND close connection
    */
    $loop->addTimer(5.0, function () use ($client) {
        for ($i=0; $i < 5; $i++) { 
            $client->write('Foobar');
        }

        $client->close();
    });
});

$outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

/**
 * Start server
*/
$outSystem->stdout("Starting server ...", OutSystem::LEVEL_NOTICE);
$server->start();

/**
 * Write function
*/
function write()
{

}