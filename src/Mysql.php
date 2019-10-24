<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use BrunoNatali\Tools\SystemInteraction;
use BrunoNatali\EventLoop\LoopInterface;

class Mysql implements MysqlInterface
{
    Private $mysqlProcess;
    Private $mysqlServer = '127.0.0.1';
    Private $mysqlDb;
    Private $mysqlUser;
    Private $mysqlPassword;
    Private $currentTable = null;

    Private $solveProblems = true; // Auto try to solve any problems when not exist or not match things

    Private $loop = null;
    Private $theSystem = null;
    Private $theResource = null;

    Protected $status = self::DB_CONNECTION['disconnected'];
    Private $timers = [];

    function __construct(array $config = [], LoopInterface &$loop = null, SystemInteractionInterface &$sysInteraction = null)
    {
        if ($sysInteraction !== null) $this->theSystem = &$sysInteraction;
        else $this->theSystem = new SystemInteraction();

        if ($loop !== null) {
            if ($loop instanceof LoopInterface) $this->loop = &$loop;
            else throw new Exception( "Provide a LoopInterface.");
        }

        $this->mysqlProcess = ($this->theSystem->getSystem() === self::SYSTEM['WIN'] ? self::MYSQL_PROC_WIN : self::MYSQL_PROC_UNIX);

        foreach($config as $name => $value){
            $name = strtoupper(trim($name));
            $value = trim($value);
			if ($name != "" && $value != ""){
				switch($name){
					case "PROCESS":
						$this->mysqlProcess = $value;
						break;
					case "SERVER":
						$this->mysqlServer = $value;
						break;
					case "DB":
						$this->mysqlDb = $value;
						break;
					case "USER":
						$this->mysqlUser = $value;
						break;
					case "PASSWORD":
						$this->mysqlPassword = $value;
						break;
					case "SOLVE_PROBLEMS":
						$this->solveProblems = $value;
						break;
                    default :
                        throw new Exception( "Configuration '$name' not accepted.");
                        break;
				}
			} else {
                throw new Exception( "Configuration must not be null.");
            }
		}

        if (!$this->connect()) throw new Exception("[DB CONNECT] " . $this->errorMsg);
    }

    Public function connect(bool $forceSolve = null): bool
    {
        if (!$this->mysqlServer) throw new Exception( "Server could not be empty.");
        if (!$this->mysqlDb) throw new Exception( "Data Base name could not be empty.");
        if (!$this->mysqlUser) throw new Exception( "User name could not be empty.");
        if (!$this->mysqlPassword) throw new Exception( "Password could not be empty.");

        if ($this->status <= self::DB_CONNECTION['connecting'] && $this->status != self::DB_CONNECTION['disconnected'])
            return false; // Could not connect due status break

        $this->status = self::DB_CONNECTION['connecting'];
        if ($forceSolve === null) $forceSolve = $this->$solveProblems;

        $sqlConn = 'mysql:host=' . $this->mysqlServer . ';dbname=' . $this->mysqlDb . ';charset=utf8';

        try {
			$this->theResource = new PDO($sqlConn, $this->mysqlUser, $this->mysqlPassword);
			$this->theResource->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->status = self::DB_CONNECTION['connected'];
            return true;
		} catch (PDOException $e) {
            if ($forceSolve) {
                $this->scheduleConnection(null, $forceSolve);
                return false;
            } else {
                $this->errorMsg = $e->getMessage();
                $this->errorCode = intval($e->getCode());
                throw new Exception( $e->getMessage() );
            }
			//$this_func_args = func_get_args();
			//return ($forceSolve ? $this->try_to_solve_problem('connect', $this_func_args) : false);
		}
    }

    Public function newDataBase(string $db): bool
    {
        if (!$db) throw new Exception("Data Base name could not be empty.");
        if (!$this->mysqlServer) throw new Exception("Server could not be empty.");
        if (!$this->mysqlUser) throw new Exception("User name could not be empty.");
        if (!$this->mysqlPassword) throw new Exception("Password could not be empty.");

        if (!$this->isAvailable()) return false;

        try {
            $this->checkResource(false);
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
            return false;
        }

        try {
			$this->theResource
            ->exec("CREATE DATABASE `$db`;
					GRANT ALL ON `$db`.* TO '" . $this->mysqlUser . "'@'localhost';
					FLUSH PRIVILEGES;");

            $this->mysqlDb = $db;
			return true;
		} catch (PDOException $e) {
			$this->theResource->rollBack();
			$this->errorMsg = $e->getMessage();
			$this->errorCode = intval($e->getCode());
			return false;
		}
    }

    Public function read(...$params): array
    {
        $param = ' ';
        forach ($params as $arg) {
            if (is_array($arg)) {
                foreach ($arg as $key => $value) {
                    switch ($key) {
                        case 'table':
                            if (is_string($value) && ($value = trim($value)) !== "")
                                $table = $value;
                            break;
                        case 'select'
                            if (is_string($value) && ($value = trim($value)) !== "") $select = $value;
                            else if (is_array($value)) $select = implode(', ', $value);
                            break;
                    }
                }
            } else if (is_string($arg)) {
                $param .= $arg;
            } else if (is_bool($arg)) {
                $forceSolve = $arg;
            }
        }

        try {
            $this->checkResource(false);
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
            return false;
        }


        if ($table === null) {
            if ($this->currentTable === null) throw new Exception("Provide table to read.");
            else $table = $this->currentTable;
        }

        $sql = trim('SELECT ' . ($select != null ? $select : '*') . ' FROM ' . $table . $param);
        try {
            $result = $this->theResource->query($sql);
            try {
                $this->checkResource(false, $result);
            } catch (\Exception $e) {
                $this->errorMsg = $e->getMessage();
                return false;
            }

            if (!is_array($answerArr = $result->fetchAll(PDO::FETCH_ASSOC))) return [];
            return $answerArr;
        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
            throw new Exception("[READ] " . $this->errorMsg);
        }
    }

    Public function useTable(string $table)
    {
        $this->currentTable = trim($table);
    }

    Public function isAvailable(): bool
    {
        if ($this->status >= self::DB_CONNECTION['connected']) return true;
        return false;
    }

    Private function checkResource($forceSolve = true, $resource = null)
    {
        if (!$resource) $resource = &$this->theResource;
        if (!$resource || is_bool($resource)) {
            if (!$this->connect($forceSolve)) throw new Exception("[RECONNECT] " . $this->errorMsg);
        }
    }

    Private function scheduleConnection($time = null, bool $forceSolve = null): bool
    {
        if ($this->loop === null) return false;
        $this->status = self::DB_CONNECTION['reconnecting'];

        if ($time === null) $time = self::TIMER_TRY_CONNECT_DB;

        if (!isset($this->timers[ 'connection' ])) {
            $me = &$this;
            $this->timers[ 'connection' ] = $this->loop
                ->addTimer($time, function () use ($me, $forceSolve) {
                $me->timers[ 'connection' ] = true;
                $me->connect($forceSolve);
                unset($me->timers[ 'connection' ]);
            });
        }
        return true;
    }
}
?>
