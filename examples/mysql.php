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
 * First call to database, table & columns
 * 
 * Will create everithing
*/
$outSystem->stdout(
    "Insert: " . $mysql->insert(
        [
            'column_a' => 'bruno', 
            'column_b' => 'natali',
            'column_c' => 10
        ],
        [
            'table' => 'table_test_1' 
        ]
    ), 
    OutSystem::LEVEL_NOTICE
);

/**
 * First call to table 2 & it`s columns
 * 
 * Will create new table & columns
*/
$outSystem->stdout(
    "Insert: " . $mysql->insert(
        [
            ['column_a', 'column_b', 'column_c'],
            ['a', 'b', 1],
            ['c', 'd', 2],
            ['e', 'f', 3],
            ['g', 'h', 4],
        ],
        [
            'table' => 'table_test_2',
            'bulk' => true 
        ]
    ), 
    OutSystem::LEVEL_NOTICE
);

/**
 * Simple read new tables & values
*/
;
printRead(
    'table_test_1', 
    $tableRead = $mysql->read([
        'table' => 'table_test_1',
        'select' => ['column_a', 'column_b', 'column_c'] // Explicit select (all) columns 
    ])
);

printRead(
    'table_test_2', 
    $tableRead = $mysql->read([
        'table' => 'table_test_2',
        // 'select' => [] // Implicit select all columns
    ])
);

/**
 * Will insert 1 line at table 2
 * 
 * A column size change will call to grow to var value
*/
$outSystem->stdout(
    "Insert: " . $mysql->insert(
        [
            'column_a' => 'bruno', 
            'column_b' => 'natali',
            'column_c' => 10
        ],
        [
            'table' => 'table_test_2' 
        ]
    ), 
    OutSystem::LEVEL_NOTICE
);

/**
 * Will insert a bulk lines at first table
*/
$outSystem->stdout(
    "Insert: " . $mysql->insert(
        [
            ['column_a', 'column_b', 'column_c'],
            ['a', 'b', 1],
            ['c', 'd', 2],
            ['e', 'f', 3],
            ['g', 'h', 4],
        ],
        [
            'table' => 'table_test_1',
            'bulk' => true 
        ]
    ), 
    OutSystem::LEVEL_NOTICE
);

/**
 * Read againg table values
*/
;
printRead(
    'table_test_1', 
    $tableRead = $mysql->read([
        'table' => 'table_test_1',
        'select' => ['column_a', 'column_b', 'column_c'] // Explicit select (all) columns 
    ])
);

printRead(
    'table_test_2', 
    $tableRead = $mysql->read([
        'table' => 'table_test_2',
        // 'select' => [] // Implicit select all columns
    ])
);

/**
 * Test change column_c type to VARCHAR & write number at column_a
*/
$outSystem->stdout(
    "Insert: " . $mysql->insert(
        [
            'column_a' => 100, 
            'column_b' => 'Foo',
            'column_c' => 'Bar'
        ],
        [
            'table' => 'table_test_2' 
        ]
    ), 
    OutSystem::LEVEL_NOTICE
);

// Just print to show what change
printRead(
    'table_test_2', 
    $tableRead = $mysql->read([
        'table' => 'table_test_2',
        // 'select' => [] // Implicit select all columns
    ])
);

function printRead($tableName, $tableRead)
{
    global $outSystem;
    
    $outSystem->stdout("Read table: $tableName" . PHP_EOL, false,OutSystem::LEVEL_NOTICE);

    foreach ($tableRead as $ln => $line) {
        $outSystem->stdout("   Line: $ln" . PHP_EOL, false,OutSystem::LEVEL_NOTICE);
        foreach ($line as $key => $value) 
            $outSystem->stdout("\t$key => $value" . PHP_EOL, null,OutSystem::LEVEL_NOTICE);
    }

    $outSystem->stdout("", null,OutSystem::LEVEL_NOTICE); // EOL
}

$loop->run();