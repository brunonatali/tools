<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

interface MysqlInterface
{
    Const MYSQL_PROC_UNIX = '/var/run/mysqld/mysqld.pid';
    Const MYSQL_PROC_WIN = ''; // Currently not know where proc is

    Const MYSQL_RETRY_CONNECTION = 5.0;  //  Time that system will retry connection to DB if fails (in seconds)

    const MYSQL_DB_STATUS_DISCONNECTED = 0x01;
    const MYSQL_DB_STATUS_FORCE_DISCONNECTED = 0x02;
    const MYSQL_DB_STATUS_RECONNECTING = 0x03;
    const MYSQL_DB_STATUS_CONNECTING = 0x04;
    const MYSQL_DB_STATUS_CONNECTED = 0x05;

    const MYSQL_SPECIAL_JUST_CALL_ME_AGAIN = 0xFFF1;
    /*
    Const DB_CONNECTION = [
        'disconnected' => 0x01,  // Disconnected
        'forceDisconnected' => 0x02,  // Disconnected not try reconnecting
        'reconnecting' => 0x03,  // Disconnected, scheduled new connection
        'connecting' => 0x04,  // Connecting
        'connected' => 0x05   // Connected
    ];
    */
}
?>
