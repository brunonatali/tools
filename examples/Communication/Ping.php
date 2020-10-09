<?php declare(strict_types=1);

require realpath(__DIR__ . '/../../../../') . '/autoload.php';

use BrunoNatali\Tools\Communication\Ping; 

use BrunoNatali\Tools\OutSystem;
use React\EventLoop\Factory;

$config = [
    "outSystemName" => 'ping-test',
    "outSystemEnabled" => true
];

$outSystem = new OutSystem($config);

$loop = Factory::create();

$ping = new Ping($loop, $config);

$outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

// Send a single Ping every 2 seconds 
$loop->addPeriodicTimer(2.0, function () use (&$ping) {
    $ping->send('8.8.8.8');
});

// Send 5 Pings with 4 seconds timeout starting 10 seconds from run
$loop->addTimer(10.0, function () use (&$ping) {
    $ping->send('1.2.3.4', 4, 5); // Timeouts
});

$loop->run();