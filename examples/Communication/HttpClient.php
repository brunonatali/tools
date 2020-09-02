<?php declare(strict_types=1);

require __DIR__ . '/../../../../../autoload.php';

use BrunoNatali\Tools\Communication\SimpleHttpClient; 

use BrunoNatali\Tools\OutSystem;
use React\EventLoop\Factory;

$config = [
    "outSystemName" => 'test',
    "outSystemEnabled" => true
];

$outSystem = new OutSystem($config);

$loop = Factory::create();

$client = new SimpleHttpClient(
    $loop, 
    $config,
    5.0 // 5 seconds time out
);

/**
 * Start client
*/
$outSystem->stdout("Starting...", OutSystem::LEVEL_NOTICE);

$client->start(false); // Starting as false cause we will add some requests before loop

/**
 * Make some requests
*/
$outSystem->stdout("Requesting google.com", OutSystem::LEVEL_NOTICE);
$client->request(
    'http://google.com/', 
    function ($headers, $body) {
        var_dump($headers, $body);
    }
);

$outSystem->stdout("Requesting non existent content", OutSystem::LEVEL_NOTICE);
$client->request('http://some.error.url.com.br/');

$loop->run();