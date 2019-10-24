<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface MysqlInterface exteds SystemInteractionInterface
{
    Const MYSQL_PROC_UNIX = '/var/run/mysqld/mysqld.pid';
    Const MYSQL_PROC_WIN = ''; // Currently not know where proc is

    Const TIMER_TRY_CONNECT_DB = 5.0;  //  Time that system will retry connection to DB if fails (in seconds)

    Const DB_CONNECTION = [
        'disconnected' => 0x01,  // Disconnected
        'forceDisconnected' => 0x02,  // Disconnected not try reconnecting
        'reconnecting' => 0x03,  // Disconnected, scheduled new connection
        'connecting' => 0x04,  // Connecting
        'connected' => 0x05   // Connected
    ];
}
?>
