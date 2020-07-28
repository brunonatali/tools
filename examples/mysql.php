<?php declare(strict_types=1);

require __DIR__ . '/../../../autoload.php';

use BrunoNatali\Tools\Mysql; 

use BrunoNatali\Tools\OutSystem;
use React\EventLoop\Factory;

$config = [
    "outSystemName" => 'example',
    "outSystemEnabled" => true
];

$outSystem = new OutSystem($config);

$loop = Factory::create();

$mysql = new Mysql(
    $loop,
    \array_merge(
        $config,
        [
            'SERVER' => 'localhost',
                /* When true, will solve comon errors, ex. something not exist (like db or column), creating it */
            'SOLVE_PROBLEMS' => true, 
            'DB' => 'db_test', /* [WARNING] This will create database db_test */
            'USER' => 'root',
            'PASSWORD' => ''
        ]
    )
);

/**
 * Simple read
*/
$tableRead = $mysql->read([
    'table' => 'table_test',
    'select' => ['column_a', 'column_c']
]);

\var_dump('First read', $tableRead);

$outSystem->stdout(
    "Insert: " . $mysql->insert(
        [
            'column_a' => 'bruno', 
            'column_b' => 'natali',
            'column_c' => 10
        ],
        [
            'table' => 'table_test' 
        ]
    ), 
    OutSystem::LEVEL_NOTICE
);

$loop->run();