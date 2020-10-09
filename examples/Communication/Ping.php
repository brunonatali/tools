<?php declare(strict_types=1);

require realpath(__DIR__ . '/../../../../') . '/autoload.php';
require realpath(__DIR__ . '/../../src/') . '/Communication/PingInterface.php';
require realpath(__DIR__ . '/../../src/') . '/Communication/Ping.php';

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

$loop->addPeriodicTimer(2.0, function () use (&$ping) {
    $ping->send('8.8.8.8');
});

$loop->addTimer(10.0, function () use (&$ping) {
    $ping->send('1.2.3.4', 4, 5);
});

$loop->run();