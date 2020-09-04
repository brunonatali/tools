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
    
    public function connect(bool $forceSolve = null): bool;
    public function disconnect(bool $changeStatus = true);
    public function reconnect(): bool;
    public function selectDataBase(string $dbName = null): bool;
    Public function selectTable(string $table);
    public function newDataBase(string $db = null): bool;
    public function newTable(string $table);
    public function newColumn($column, array $config = []);
    public function read(...$params): array;
    public function insert(array $whereWhat, array $config = []);
    public function update(string $column, $value, array $config);
    public function delete(array $config);
    public function querySql(string $sql, &$result = null, bool $retry = true);
    public function changeColumnSpecs($column, array $config = []): bool;
    public function isAvailable(): bool;
    public function getColumnType(&$column, $table = null);
    public function getLastErrorMessage(): string;
    public function getLastErrorCode(): int;

}
?>
