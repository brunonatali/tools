<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

use React\EventLoop\LoopInterface;

class Mysql implements MysqlInterface
{
    private $mysqlProcess = null;
    private $mysqlServer = null;
    private $mysqlDb = null;
    private $mysqlUser = null;
    private $mysqlPassword = null;

    // Auto try to solve any problems when not exist or not match things
    private $solveProblems = false; 

    private $loop = null;
    private $queue;
    private $outSystem;
    private $timers = [];

    private $info = [];
    private $dbResource = null;
    private $currentTable = null;

    private $errorMsg = null;
    private $errorCode = null;

    protected $status = self::MYSQL_DB_STATUS_DISCONNECTED;

    /* OK */
    function __construct(...$configs)
    {
        /**
         * Check if\PDO extension is enabled before continue
         * Will enable it, if can.
        */
        if (\extension_loaded('pdo_mysql') === false)
            if (!\function_exists('dl'))
                throw new \Exception("Load 'pdo_mysql' extension first");
            else
                try {
                    if (!\dl('pdo_mysql'))
                        throw new \Exception("Auto load 'pdo_mysql' could not found extension");
                } catch (\Exception $e) {
                    throw new \Exception("Auto load 'pdo_mysql' fail. " . $e->getMessage()); 
                }
        
        $autoConnect = true;

        $userConfig = [];
        foreach ($configs as &$config) {
            if ($config instanceof LoopInterface) 
                $this->loop = &$config;
            else if (\is_array($config))
                foreach($config as $name => $value) {
                    if (\is_string($value))
                        $value = \trim($value);
        
                    switch( \strtoupper(\trim($name)) ) {
                        case "PROCESS":
                            if (!\is_string($value)) 
                                throw new \Exception( "Configuration '$name' must be string.");

                            $this->mysqlProcess = $value;
                            break;
                        case "SERVER":
                            if (!\is_string($value)) 
                                throw new \Exception( "Configuration '$name' must be string.");

                            $this->mysqlServer = $value;
                            break;
                        case "DB":
                            if (!\is_string($value)) 
                                throw new \Exception( "Configuration '$name' must be string.");
                            $this->mysqlDb = $value;
                            break;
                        case "USER":
                            if (!\is_string($value)) 
                                throw new \Exception( "Configuration '$name' must be string.");

                            $this->mysqlUser = $value;
                            break;
                        case "PASSWORD":
                            if (!\is_string($value)) 
                                throw new \Exception( "Configuration '$name' must be string.");

                            $this->mysqlPassword = $value;
                            break;
                        case "SOLVE_PROBLEMS":
                            if (!\is_bool($value)) 
                                throw new \Exception( "Configuration '$name' must be boolean.");

                            $this->solveProblems = $value;
                            break;
                        default:
                            $userConfig += [$name => $value];
                            break;
                    }
                }
            else if (\is_bool($config))
                $autoConnect = $config;
        }

        if ($this->loop === null && $this->solveProblems)
            throw new \Exception( "Provide a LoopInterface.");

        if ($this->mysqlServer === null) 
            throw new \Exception( "Server could not be empty.");

        if ($this->mysqlUser === null) 
            throw new \Exception( "User name could not be empty.");

        if ($this->mysqlPassword === null) 
            throw new \Exception( "Password could not be empty.");

        $this->mysqlProcess = (
            SysInfo::getSystemType() === ConstantsInterface::SYSTEM_TYPE_WIN ? 
            self::MYSQL_PROC_WIN : 
            self::MYSQL_PROC_UNIX
        );

        // Enable debug print by default
        $userConfig += [
            'outSystemEnabled' => true
        ];

        $config = OutSystem::helpHandleAppName( 
            $userConfig,
            [
                "outSystemName" => 'MyDB'
            ]
        );
        $this->outSystem = new OutSystem($config);

        if ($this->solveProblems)
            $this->queue = new Queue($this->loop);

        if ($autoConnect)
            $this->connect(); 
    }

    /* OK */
    public function connect(bool $forceSolve = null): bool
    {
        $this->outSystem->stdout("Connecting to DB Server... ", false, OutSystem::LEVEL_NOTICE);

        if ($this->isAvailable()) {
            $this->outSystem->stdout("OK, already connected", OutSystem::LEVEL_NOTICE);
                
            return true;
        }

        // Could not connect while in break status 
        if ($this->status <= self::MYSQL_DB_STATUS_CONNECTING && 
            $this->status !== self::MYSQL_DB_STATUS_DISCONNECTED &&
            $this->status !== self::MYSQL_DB_STATUS_RECONNECTING) {
                $this->outSystem->stdout("Aborted, status " . $this->status, OutSystem::LEVEL_NOTICE);
                
                return false;
            } 

        $this->status = self::MYSQL_DB_STATUS_CONNECTING;

        if ($forceSolve === null) 
            $forceSolve = $this->solveProblems;

        $sqlConn = 'mysql:host=' . $this->mysqlServer . ';charset=utf8';

        try {
            $this->dbResource = new \PDO($sqlConn, $this->mysqlUser, $this->mysqlPassword);
            // Parse only in mysql
            $this->dbResource->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false); 
            // Prevent break on error, errors could be catched
            $this->dbResource->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION); 
            
            $this->status = self::MYSQL_DB_STATUS_CONNECTED;

            $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

            // Auto select database when pre configured
            if ($this->mysqlDb !== null) 
                $this->selectDataBase();

            if ($forceSolve) 
                $this->queue->resume();
            return true;
		} catch (\PDOException $e) {
            // First of all pause queue to prevent itens to be dequeded
            if ($forceSolve)
                $this->queue->pause();

            $this->errorMsg = $e->getMessage();
            $this->errorCode = $e->getCode();

            $this->outSystem->stdout('Fail. ' . $this->errorMsg, OutSystem::LEVEL_NOTICE);

            $this->status = self::MYSQL_DB_STATUS_DISCONNECTED;

            if ($forceSolve) {

                unset($this->timers['connection']);
                $this->scheduleConnection(null, $forceSolve);

                return false;
            } else {

                throw new \Exception( $e->getMessage() );

            }
			//$this_func_args = func_get_args();
			//return ($forceSolve ? $this->try_to_solve_problem('connect', $this_func_args) : false);
		}
    }

    /* OK */
    public function disconnect(bool $changeStatus = true)
    {
        if ($changeStatus) {
            $this->status = self::MYSQL_DB_STATUS_DISCONNECTED;
            $this->outSystem->stdout("Disconnected from DB Server!", OutSystem::LEVEL_NOTICE);
        }
            
        $this->dbResource = null;
    }

    /* OK */
    public function reconnect(): bool
    {
        $this->outSystem->stdout("Reconneting DB server ...", OutSystem::LEVEL_NOTICE);

        $this->status = self::MYSQL_DB_STATUS_RECONNECTING;

        $this->disconnect(false);

        return $this->connect();
    }

    /* OK */
    public function selectDataBase(string $dbName = null): bool
    {
        if ($dbName === null)
            if ($this->mysqlDb === null) 
                throw new \Exception( "Data Base name could not be empty.");
            else
                $dbName = $this->mysqlDb;
        
        $this->outSystem->stdout("Selecting DB '$dbName': ", false, OutSystem::LEVEL_NOTICE);

        if (!$this->isAvailable()) {
            $this->outSystem->stdout("Fail. Server disconnected!", OutSystem::LEVEL_NOTICE);
            return false;
        }

        $result = $this->querySql("USE `$dbName`", $queryResult);

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems)
                        return $this->tryToSolveTheProblem('selectDataBase', \func_get_args());

                    return false;
                }

                $this->outSystem->stdout("Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);
        
                $this->info[$dbName] = [];
        
                return true;
            }
        
        $this->outSystem->stdout("Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
    }

    Public function selectTable(string $table)
    {
        $this->currentTable = trim($table);
    }

    /* OK */
    public function newDataBase(string $db = null): bool
    {
        if ($db == null) 
            throw new \Exception("Data Base name could not be empty.");

        $this->outSystem->stdout("Creating DB '$db': ", false, OutSystem::LEVEL_NOTICE);

        $result = $this->querySql(
            "CREATE DATABASE `$db`;
				GRANT ALL ON `$db`.* TO '" . $this->mysqlUser . "'@'localhost';
                FLUSH PRIVILEGES;", 
            $queryResult
        );

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems)
                        return $this->tryToSolveTheProblem('newDataBase', \func_get_args());

                    return false;
                }

                $this->outSystem->stdout("Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                if ($this->mysqlDb === null)
                    $this->mysqlDb = $db;

                $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

                return true;
            }
        
        $this->outSystem->stdout("Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
    }

    public function newTable(string $table) 
    {
        $this->outSystem->stdout("Creating table '$table': ", false, OutSystem::LEVEL_NOTICE);

        $result = $this->querySql(
            "CREATE table $table(id INT( 11 ) AUTO_INCREMENT PRIMARY KEY);", 
            $queryResult
        );

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems)
                        return $this->tryToSolveTheProblem('newTable', \func_get_args());

                    return false;
                }

                $this->outSystem->stdout("Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

                if (!isset($this->info[ $this->mysqlDb ][ $table ]))
                        $this->info[ $this->mysqlDb ][ $table ] = [];

                return true;
            }
        
        $this->outSystem->stdout("Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
    }
    
    public function newColumn($column, array $config = [])
    {
        $config += [
            'table' => $this->currentTable,
            'type' => 'BOOLEAN',
            'size' => 10,
            'useDefaults' => false 
        ];

		switch ($config['type']) {
			case "VARCHAR":
				$sql = "ALTER TABLE `" . $config['table'] . 
                    "` ADD `$column` VARCHAR(" . $config['size'] . ") ;";
			    break;
			case "TIMESTAMP":
				$sql = "ALTER TABLE `" . $config['table'] . 
                    "` ADD `$column` TIMESTAMP ;";

                if($config['useDefaults'] === TRUE) 
                    $default = "NOT NULL DEFAULT CURRENT_TIMESTAMP";
			    break;
			case "INT":
                if  ($config['size'] > 250) 
                    $config['size'] = 250;

				$sql = "ALTER TABLE `" . $config['table'] . 
                    "` ADD `$column` INT(" . $config['size'] . ") ;";
			    break;
			case "BOOLEAN":
				$sql = "ALTER TABLE `" . $config['table'] . "` ADD `$column` BOOLEAN ;";
			    break;
			default:
				stdout("ERROR - type unknow", 3);
				return(false);
			    break;
		}

        $this->outSystem->stdout(
            "Creating column '$column' as " . $config['type'] . "(" . $config['size'] . "): ", 
            false, 
            OutSystem::LEVEL_NOTICE
        );
		
		if(isset($default))
			$sql .= $default;
		else 
			$sql .= "NULL DEFAULT NULL";
            
        $result = $this->querySql($sql, $queryResult);

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems)
                        return $this->tryToSolveTheProblem('newColumn', \func_get_args());

                    return false;
                }

                $this->outSystem->stdout("Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

                if (!isset($this->info[ $this->mysqlDb ][ $config['table'] ][$column]))
                    $this->info[ $this->mysqlDb ][ $config['table'] ][$column] = [
                        'type' => $config['type'], 
                        'size' => $config['size']
                    ];

                return true;
            }
        
        $this->outSystem->stdout("Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
	}

    /**
     * (string) Additional parameters to SQL
     * (bool)   Try to solve any encountered problems     
     * (array)  [
     *              'table' => (string) table name,
     *              'select' => (string) column name
     *                              Or
     *                          (array)  columns list
     *          ]
    */
    public function read(...$params): array
    {
        if (!$this->isAvailable())
            return [];

        $param = ' ';
        $forceSolve = true;

        foreach($params as $arg)
            if (\is_array($arg))
                foreach ($arg as $key => $value)
                    switch ($key) {
                        case 'table':
                            if (\is_string($value) && ($value = \trim($value)) !== "")
                                $table = $value;
                            break;
                        case 'select':
                            if (\is_string($value) && ($value = \trim($value)) !== "") 
                                $select = $value;
                            else if (\is_array($value)) 
                                $select = \implode(', ', $value);
                            break;
                        default:
                            throw new \Exception("Unrecognized '$key'.");
                            break;
                    }
            else if (\is_string($arg))
                $param .= $arg;
            else if (\is_bool($arg))
                $forceSolve = $arg;

        if (!isset($table) || $table === null) {
            if ($this->currentTable === null) 
                throw new \Exception("Provide table to read.");
            else 
                $table = $this->currentTable;
        }

        $this->outSystem->stdout(
            "Reading '" . (isset($select) ? $select : '*') . 
                "' on '$table'" . ($param != ' ' ? " and '$param': " : ': '), 
            false, 
            OutSystem::LEVEL_NOTICE
        );

        if (!$this->checkResource()) {
            $this->outSystem->stdout("Fail. Rescheduling...", OutSystem::LEVEL_NOTICE);
            return $this->tryToSolveTheProblem('read', \func_get_args(), self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN);
        }

        $sql = trim(
            'SELECT ' . (
                isset($select) && $select != null ? 
                $select : 
                '*'
            ) . ' FROM ' . $table . $param
        );

        try {
            $result = $this->dbResource->query($sql);

            if (!$this->testResult($result, $sql)) {
                $this->outSystem->stdout("Fail. Rescheduling...", OutSystem::LEVEL_NOTICE);
                $this->tryToSolveTheProblem('read', \func_get_args(), self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN);
            }
            
            $answerArr = $result->fetchAll(\PDO::FETCH_ASSOC);

            if (\is_bool($answerArr)) {
                $this->outSystem->stdout("False.", OutSystem::LEVEL_NOTICE);
                return [];
            }

            $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);

            return $answerArr;

        } catch (\Exception $e) {
            $this->errorMsg = $e->getMessage();
            $this->errorCode = $e->getCode();

            $this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

            /* On read() error will not be handled, and an empty array will returned */
            return [];
        }
    }

    public function insert(array $whereWhat, array $config = [])
    {    
        $config += [
            'table' => $this->currentTable,
            'bulk' => false,
            'update_if_exist' => false
        ];

        if ($config['table'] === null) {
            $this->outSystem->stdout("Insert: Fail. Mismatch config", OutSystem::LEVEL_NOTICE);
            return false;
        }

        $whereWhatBkp = $whereWhat;

        /**
         * Check standards
        */
        if ($config['bulk']) {
            if (\count($whereWhatBkp) < 1) {
                $this->outSystem->stdout("Insert: Fail. Need at least 2 while bulk", OutSystem::LEVEL_NOTICE);
                return false;
            }

            $where = $whereWhatBkp[0];

            if (!\is_array($where)) {
                $this->outSystem->stdout("Insert: Fail. Mismatch 'where'", OutSystem::LEVEL_NOTICE);
                return false;
            }

            unset($whereWhatBkp[0]);

            foreach ($whereWhatBkp as $key => $what) {
                if (!\is_array($what)) {
                    $this->outSystem->stdout("Insert: Fail. Mismatch 'what'($key)", OutSystem::LEVEL_NOTICE);
                    return false;
                }

                if (!$this->checkStandardsValues($where, $whereWhatBkp[$key], $config['table'])) {
                    $this->outSystem->stdout("Insert: Fail. adjusting column", OutSystem::LEVEL_NOTICE);
                    return false;
                }
            }

        } else {
            if (empty($whereWhatBkp)) {
                $this->outSystem->stdout("Insert: Fail. Content is null", OutSystem::LEVEL_NOTICE);
                return false;
            }

            $where = \array_keys($whereWhatBkp);
            $whatArr = \array_values($whereWhatBkp);

            if (!$this->checkStandardsValues($where, $whatArr, $config['table'])) {
                $this->outSystem->stdout("Insert: Fail. adjusting column", OutSystem::LEVEL_NOTICE);
                return false;
            }
        }
        

        $sql = "INSERT INTO " . $config['table'] . " (`" . \implode('`,`', $where) . "`) VALUES ";

        if ($config['bulk']) {
            foreach ($whereWhatBkp as $what)
                $sql .= '(' . \implode(',', $what) . '),';
        
            $sql[ (\strlen($sql) - 1) ] = ' '; // Removes last comma
        } else {
            $sql .= '(' . \implode(',', $whatArr) . ') ';
        }
            
        if ($config['update_if_exist']) {
            $sql .= 'ON DUPLICATE KEY UPDATE '; 
            
            foreach ($where as $column) {
                $sql .= "`$column`=VALUES(`$column`),";
            }

            $sql[ (\strlen($sql) - 1) ] = ';'; // Replace last comma
        }
              
        $result = $this->querySql($sql, $queryResult);

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("$sql: Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems) {
						if ($config['bulk']) {
							$this->outSystem->stdout("Fail. Solve could not be done in BULK MODE, splitting ... ", OutSystem::LEVEL_NOTICE);
							
							$config['bulk'] = false;
							$result = false;
							foreach ($whereWhat as $index => $line) {
								var_dump($index);
								if ($index !== 0)
									var_dump($this->insert($line, $config));
							}
							
							return $result;
						}
						
                        return $this->tryToSolveTheProblem('insert', [$whereWhat, $config]);
					}

                    return false;
                }

                $this->outSystem->stdout("$sql: Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                $this->outSystem->stdout("$sql: OK", OutSystem::LEVEL_NOTICE);
        
                return true;
            }
        
        $this->outSystem->stdout("Insert: Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
    }

    public function update(string $column, $value, array $config)
    {
        $config += [
            'table' => $this->currentTable,
            'condition' => null,
            'values' => null
        ];

        if (\is_string($value)) {
            /* TEST IF WORK NULLIFYING FIELD
            if ($value == '')
                $value = "''";
            */
        } else if ($value === null) {
            $value = "NULL";
        } else if (\is_numeric($value)) {
            // Do nothing
        } else {
            $this->outSystem->stdout("Update: Fail. Value must be of type string or number", OutSystem::LEVEL_NOTICE);
            return false;
        }

        if ($config['table'] === null) {
            $this->outSystem->stdout("Update: Fail. Mismatch config", OutSystem::LEVEL_NOTICE);
            return false;
        }

        $sql = 'UPDATE ' . $config['table'] . ' SET ' . $column . "=?";

        $values = [$value];

        if ($config['condition'] !== null) {
            if (\is_string($config['condition']) && $config['condition'] != '' && 
                $config['values'] !== null) {

                $sql .= ' WHERE ' . $config['condition'];

                if (\is_array($config['values']))
                    $values += $config['values'];
                else
                    $values[] = $config['values'];

            } else if (isset($config['condition']['where']) && isset($config['condition']['like'])) {
                
                $sql .= ' WHERE ' . $config['condition']['where'] . '=?';

                if (\is_array($config['condition']['like'])) 
                    \array_merge($values, $config['condition']['like']);
                else
                    $values[] = $config['condition']['like'];

            } else {
                $sql = null;
            }
        } else {
            $sql = null;
        }

        if ($sql === null) {
            $this->outSystem->stdout("Update: Fail. Wrong condition value", OutSystem::LEVEL_NOTICE);
            return false;
        }

        $result = $this->execPreparedSql($sql, $values, $queryResult);

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("Update: Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems) 
                        return $this->tryToSolveTheProblem('update', [$column, $value, $config]);

                    return false;
                }

                $this->outSystem->stdout("Update: Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                $this->outSystem->stdout($this->debugPreparedSql($sql, $values) . ': OK', OutSystem::LEVEL_NOTICE);
        
                return true;
            }
        
        $this->outSystem->stdout("Update: Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
    }
    
    public function delete(array $config)
    {
        $config += [
            'table' => $this->currentTable,
            'condition' => null,
            'remove' => false // Tell if table will be droped or just emptied
        ];

        if ($config['table'] === null) {
            $this->outSystem->stdout("Update: Fail. Mismatch config", OutSystem::LEVEL_NOTICE);
            return false;
        }

        if ($config['condition'] !== null) {
            if (\is_string($config['condition']) && $config['condition'] != '') {
                $sql .= 'DELETE FROM ' . $config['table'] . ' WHERE ' . $config['condition'];
            } else if (isset($config['condition']['where']) && isset($config['condition']['like'])) {
                $sql .= 'DELETE FROM ' . $config['table'] . ' WHERE ' . $config['condition']['where'] . 
                    ' = ' . $config['condition']['like'];
            } else {
                $this->outSystem->stdout("Delete: Fail. Wrong condition value", OutSystem::LEVEL_NOTICE);
                return false;
            }
        } else {
            if ($config['remove'])
                $sql = 'DROP TABLE ' . $config['table'];
            else
                $sql = 'TRUNCATE TABLE ' . $config['table'];
        }

        $result = $this->querySql($sql, $queryResult);

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("Delete: Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems) 
                        return $this->tryToSolveTheProblem('delete', [$config]);

                    return false;
                }

                $this->outSystem->stdout("Delete: Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                $this->outSystem->stdout("$sql: OK", OutSystem::LEVEL_NOTICE);
        
                return true;
            }
        
        $this->outSystem->stdout("Delete: Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
    }

    public function querySql(string $sql, &$result = null, bool $retry = true)
    {
        if (!$this->isAvailable())
            return ($result = false);

        if (!$this->checkResource()) {
            $this->outSystem->stdout("Fail SQL-CHK.", false, OutSystem::LEVEL_NOTICE);

            if (!$retry)
                return ($result = false);

            $trace = \debug_backtrace()[1]; 

            if ($trace['function']) {
                $this->outSystem->stdout(
                    " Rescheduling...", 
                    $this->outSystem->getLastMsgHasEol(),
                    OutSystem::LEVEL_NOTICE
                );

                return $this->tryToSolveTheProblem(
                    $trace['function'], 
                    $trace['args'], 
                    self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN,
                    $trace['class']
                );
            } else {
                $this->outSystem->stdout(
                    " Unable to retry", 
                    $this->outSystem->getLastMsgHasEol(),
                    OutSystem::LEVEL_NOTICE
                );
            }

            return ($result = false);
        }

        try {

			$result = $this->dbResource->exec($sql);

            if (!$this->testResult($result, $sql)) {
                $this->outSystem->stdout("Fail SQL-EXEC.", false, OutSystem::LEVEL_NOTICE);

                if (!$retry) 
                    return false;

                $trace = \debug_backtrace()[1]; 

                if ($trace['function']) {
                    $this->outSystem->stdout(
                        " Rescheduling...", 
                        $this->outSystem->getLastMsgHasEol(),
                        OutSystem::LEVEL_NOTICE
                    );

                    return $this->tryToSolveTheProblem(
                        $trace['function'], 
                        $trace['args'], 
                        self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN,
                        $trace['class']
                    );
                } else {
                    $this->outSystem->stdout(
                        " Unable to retry", 
                        $this->outSystem->getLastMsgHasEol(),
                        OutSystem::LEVEL_NOTICE
                    );
                }

                return false;
            }

            return true;
            
		} catch (\PDOException $e) {
            $result = null;

            // Try to rollback even not is possible to prevent database damages
            try {
                $this->dbResource->rollBack();
            } catch (\PDOException $th) {
                // Nothing to do here;
            }
            
			$this->errorMsg = $e->getMessage();
            $this->errorCode = $e->getCode();

            //$this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);
            
            return false;
            
		}
    }

    public function execPreparedSql(string $sql, array $values, &$result = null, bool $retry = true)
    {
        if (!$this->isAvailable())
            return ($result = false);

        if (!$this->checkResource()) {
            $this->outSystem->stdout("Fail PSQL-CHK.", false, OutSystem::LEVEL_NOTICE);

            if (!$retry)
                return ($result = false);

            $trace = \debug_backtrace()[1]; 

            if ($trace['function']) {
                $this->outSystem->stdout(
                    " Rescheduling...", 
                    $this->outSystem->getLastMsgHasEol(),
                    OutSystem::LEVEL_NOTICE
                );

                return $this->tryToSolveTheProblem(
                    $trace['function'], 
                    $trace['args'], 
                    self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN,
                    $trace['class']
                );
            } else {
                $this->outSystem->stdout(
                    " Unable to retry", 
                    $this->outSystem->getLastMsgHasEol(),
                    OutSystem::LEVEL_NOTICE
                );
            }

            return ($result = false);
        }
        
        try {

            $prepared = $this->dbResource->prepare($sql);
            
            $result = $prepared->execute($values);

            if (!$result) {
                $this->outSystem->stdout("Fail PSQL-EXEC.", false, OutSystem::LEVEL_NOTICE);

                if (!$retry) 
                    return false;

                $trace = \debug_backtrace()[1]; 

                if ($trace['function']) {
                    $this->outSystem->stdout(
                        " Rescheduling...", 
                        $this->outSystem->getLastMsgHasEol(),
                        OutSystem::LEVEL_NOTICE
                    );

                    return $this->tryToSolveTheProblem(
                        $trace['function'], 
                        $trace['args'], 
                        self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN,
                        $trace['class']
                    );
                } else {
                    $this->outSystem->stdout(
                        " Unable to retry", 
                        $this->outSystem->getLastMsgHasEol(),
                        OutSystem::LEVEL_NOTICE
                    );
                }

                return false;
            }

            return true;
            
		} catch (\PDOException $e) {
            $result = null;

            // Try to rollback even not is possible to prevent database damages
            try {
                $this->dbResource->rollBack();
            } catch (\PDOException $th) {
                // Nothing to do here;
            }
            
			$this->errorMsg = $e->getMessage();
            $this->errorCode = $e->getCode();

            //$this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);
            
            return false;
		}
    }

    /* OK */
    public function changeColumnSpecs($column, array $config = []): bool
    {
        $config += [
            'table' => $this->currentTable,
            'type' => 'VARCHAR',
            'size' => 10,
            'useDefaults' => false 
        ];

		switch ($config['type']) {
			case "VARCHAR":
				$sql = "ALTER TABLE `" . $config['table'] . 
                    "` CHANGE `$column` `$column` VARCHAR(" . $config['size'] . ");";
			break;
			case "TIMESTAMP":
				$sql = "ALTER TABLE `" . $config['table'] . 
                    "` CHANGE `$column` `$column` TIMESTAMP ;";
                if ($config['useDefaults'] === true) 
                    $default = "NOT NULL DEFAULT CURRENT_TIMESTAMP";
			break;
			case "INT":
				$sql = "ALTER TABLE `" . $config['table'] . 
                    "` CHANGE `$column` `$column` INT(" . $config['size'] . ") ;";
			break;
			default:
                $this->outSystem->stdout("Fail. Unknow type", OutSystem::LEVEL_NOTICE);
				return false;
			break;
        }

        $this->outSystem->stdout(
            "Changing column " . $config['table'] . ".$column to " . 
                $config['type'] . "(" . $config['size'] . "): ", 
            false, 
            OutSystem::LEVEL_NOTICE
        );
        
		if (isset($default))
			$sql .= $default;
		else 
            $sql .= "NULL DEFAULT NULL";
            
        $result = $this->querySql($sql, $queryResult);

        if (\is_bool($result)) 
            if ($result === false) {
                if ($queryResult === null) {
                    $this->outSystem->stdout("Fail. " . $this->errorMsg, OutSystem::LEVEL_NOTICE);

                    if ($this->solveProblems)
                        return $this->tryToSolveTheProblem('changeColumnSpecs', \func_get_args());

                    return false;
                }

                $this->outSystem->stdout("Fail.", OutSystem::LEVEL_NOTICE);

                return false;
            } else {
                $this->outSystem->stdout("OK", OutSystem::LEVEL_NOTICE);
        
                return true;
            }
        
        $this->outSystem->stdout("Scheduled", OutSystem::LEVEL_NOTICE);

        return true;
	}

    /* OK */
    public function isAvailable(): bool
    {
        if ($this->status >= self::MYSQL_DB_STATUS_CONNECTED) 
            return true;

        return false;
    }
    
    /* OK */
    public function getColumnType(&$column, $table = null) 
    {
        $this->outSystem->stdout("Get column($column) specs: ", false, OutSystem::LEVEL_NOTICE);
 
        if (!$this->isAvailable())
            return false;

        if (!$this->checkResource()) {
            $this->outSystem->stdout("Fail. Rescheduling...", OutSystem::LEVEL_NOTICE);
            return $this->tryToSolveTheProblem('getColumnType', \func_get_args(), self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN);
        }

        if ($table == null)
            $table = $this->currentTable;

        if ($table == null) {
            $this->outSystem->stdout("Fail. Missing table name", OutSystem::LEVEL_NOTICE);
            return false;
        }
        
        $sql = 'DESCRIBE ' . $table;

		try {
            $result = $this->dbResource->query($sql);

            if (!$this->testResult($result, $sql)) {
                $this->outSystem->stdout("Fail. Rescheduling...", OutSystem::LEVEL_NOTICE);
                $this->tryToSolveTheProblem('read', \func_get_args(), self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN);
            }
            
            $result = $result->fetchAll(\PDO::FETCH_ASSOC);
            
			foreach ($result as $colResp) {
				if ($colResp['Field'] == $column) {
                    $result = \explode('(', $colResp['Type']);
                    
                    if(isset($result[1])) 
                        $column = \intval($result[1]);

                    $this->outSystem->stdout($result[0] . "=>($column) OK", OutSystem::LEVEL_NOTICE);
					return \strtoupper($result[0]);
				}
            }

            $this->outSystem->stdout('Fail. Column not found', OutSystem::LEVEL_NOTICE);
			return false;
		} catch (\PDOException $e) {
            $this->outSystem->stdout('Fail. ' . $e->getMessage(), OutSystem::LEVEL_NOTICE);
			return false;
		}
    }

    public function getLastErrorMessage(): string
    {
        return (string) $this->errorMsg;
    }

    public function getLastErrorCode(): int
    {
        return (int) $this->errorCode;
    }
    
    /* OK */
    private function getVarType($val): string
    {
		if (\is_string($val))
			return "VARCHAR";
		
		if ($val === NULL)
			return "NULL";
        
		if (\is_int($val))
            return "INT";

        if (\is_bool($val)) 
            return "BOOLEAN";
		
        if (\is_float($val)) 
            return "FLOAT";
        
        if (\strtotime($val) !== FALSE) 
            return "TIMESTAMP";
    }
    
    /* OK */
    private function getPdoConstant($val)
    {
        if($val === null) 
            return \PDO::PARAM_NULL;

        if(\is_string($val)) 
            return \PDO::PARAM_STR;

        if(\is_int($val)) 
            return \PDO::PARAM_INT;

        if(\is_bool($val)) 
            return \PDO::PARAM_BOOL;
	}

    /* OK */
    private function testResult(&$result, $sql = null): bool
    {
        if (\is_bool($result)) {
            $this->outSystem->stdout("Resource is lost.", null, OutSystem::LEVEL_NOTICE);
            
            $this->connect();

            if (!$this->isAvailable())
                return false;
                
            // Execute SQl again 
            if ($sql != null)
                $result = $this->dbResource->query($sql);
            
            // Check if still running on error
            if (\is_bool($result)) {
                $this->outSystem->stdout("Resource unrecoverable.", null, OutSystem::LEVEL_NOTICE);
                return false;
            }
        } 

        return true;
    }

    /* OK */
    private function checkResource($resource = null, $forceSolve = null): bool
    {
        if ($resource === null) 
            $resource = &$this->dbResource;

        if (!$resource || \is_bool($resource)) {
            if ($forceSolve === null)
                $forceSolve = $this->solveProblems;

            // Pause queue  ASAP
            if ($forceSolve)
                $this->queue->pause();

            $this->connect($forceSolve);

            return $this->isAvailable();
        }

        return true;
    }

    private function checkStandardsValues(array $where, array &$whatArr, string $table = null)
    {
        if ($table === null) {
			if ($this->currentTable === null)
				return false;
			
            $table = $this->currentTable;
        }

        if (count($where) !== count($whatArr))
            return false;

        foreach ($whatArr as $i => $what) {
            if (\is_array($what)) 
                return false;

            $type = $this->getVarType($what);
            
            if (!isset($this->info[ $this->mysqlDb ][ $table ]))
                $this->info[ $this->mysqlDb ][ $table ] = [];
                
            if (!isset($this->info[ $this->mysqlDb ][ $table ][ $where[$i] ])) {
                $size = $where[$i];
                $dbType = $this->getColumnType($size, $table); // column, table
                
                if ($dbType !== false) {
                    $this->info[ $this->mysqlDb ][ $table ][ $where[$i] ] = [
                        'type' => $dbType, 
                        'size' => $size
                    ];
                } else {
					$this->outSystem->stdout("Ignoring column type when it`s undefined", OutSystem::LEVEL_NOTICE);
					
					//Just normalize variable when it`s VARCHAR
					if ($type === "VARCHAR") 
						$whatArr[$i] = "'" . self::mysql_escape_mimic($what) . "'";
					else if ($type === "NULL")
                        $whatArr[$i] = "NULL";
                    else if ($type === "INT" && $what == '')
                        $whatArr[$i] = 0;
					
					continue;
					
					// Ignore registration when not exist because it`s  hanging on solving while could not change column specs on unexistent column
                    $this->info[ $this->mysqlDb ][ $table ][ $where[$i] ] = [
                        'type' => $type, 
                        'size' => ($type === "VARCHAR" ? \strlen($what) : 10)
                    ];
                }
            }

            if ($this->info[ $this->mysqlDb ][ $table ][ $where[$i] ]['type'] === "VARCHAR") {

                $whatLen = \strlen((string) $what);

                $whatArr[$i] = "'" . self::mysql_escape_mimic($what) . "'";

                if ($this->info[ $this->mysqlDb ][ $table ][ $where[$i] ]['size'] < $whatLen) {
                    if (!$this->changeColumnSpecs(
                        $where[$i], 
                        [
                            'table' => $table,
                            'type' => "VARCHAR",
                            'size' => $whatLen
                        ]
                    ))
                        return false;
                        
                    $this->info[ $this->mysqlDb ][ $table ][ $where[$i] ]['size'] = $whatLen; // Update registered size
                }
            } else if ($this->info[ $this->mysqlDb ][ $table ][ $where[$i] ]['type'] === "INT") {
                if ($what == '')
                    $whatArr[$i] = 0;
            } else if ($this->info[ $this->mysqlDb ][ $table ][ $where[$i] ]['type'] !== $type) {
                // If database type is different than var type

                if ($type === "VARCHAR") { 
                    $whatLen = \strlen((string) $what);
    
                    $whatArr[$i] = "'" . self::mysql_escape_mimic($what) . "'";

                    if (!$this->changeColumnSpecs(
                        $where[$i], 
                        [
                            'table' => $table,
                            'type' => "VARCHAR", // Change DB type to VARCHAR
                            'size' => $whatLen
                        ]
                    ))
                        return false;
                        
                    // Force update registered size
                    $this->info[ $this->mysqlDb ][ $table ][ $where[$i] ]['size'] = $whatLen; 
                } else if ($type === "INT" && $what == '') {
                    $whatArr[$i] = 0;
                }
            }
        }

        return true;
    }

    /* OK */
    private function scheduleConnection(number $time = null, bool $forceSolve = null)
    {
        $this->status = self::MYSQL_DB_STATUS_RECONNECTING;

        if ($time === null) 
            $time = self::MYSQL_RETRY_CONNECTION;
            
        $this->outSystem->stdout('Scheduling reconnection (' . $time . 's) ...', OutSystem::LEVEL_NOTICE);

        if (!isset($this->timers['connection'])) {
            $this->timers['connection'] = $this->loop
                ->addTimer($time, function () use ($forceSolve) {
                // Pre reset timer, but set as in use
                $this->timers['connection'] = true; 

                $this->outSystem->stdout('Trying scheduled connection.', OutSystem::LEVEL_NOTICE);

                $this->connect($forceSolve);

                unset($this->timers['connection']);
            });
        }
    }

    private function debugPreparedSql(string $query, array $params): string
    {
        $keys = array();
        
        foreach ($params as $key => $value) {
            if (\is_string($key)) {
                $keys[] = '/:'.$key.'/';
            } else {
                $keys[] = '/[?]/';
            }
        }

        return \preg_replace($keys, $params, $query, 1, $count);
    }

    private function tryToSolveTheProblem(
        string $funcName = null, 
        array $funcArgs = [], 
        $retSpecific = false,
        string $class = __NAMESPACE__
    ) {
        if ($funcName === null)
            $funcName = \debug_backtrace()[1]['function'];

        if ($funcName === null) {
            $this->outSystem->stdout(
                'Trying to solve the error: Fail. No callback name', 
                OutSystem::LEVEL_NOTICE
            );
        }

        if ($retSpecific === self::MYSQL_SPECIAL_JUST_CALL_ME_AGAIN) {
            // if ($this->solveProblems) <<<<<<<--- Check if is necessary. When solve probles is set to false
            $this->queue->push( function () {
                $this->outSystem->stdout("Retrying scheduled '$funcName'", OutSystem::LEVEL_NOTICE);
                \call_user_func_array(
                    [
                        ($class === __NAMESPACE__ ? $this : $class), 
                        $funcName
                    ], 
                    $funcArgs
                );
            });

            return $retSpecific;
        }

        if ($this->errorCode === null) {
            $this->outSystem->stdout("Could not solve, no error code", OutSystem::LEVEL_NOTICE);
            return $retSpecific;
        } 

        $this->outSystem->stdout(
            'Trying to solve the error (' . $this->errorCode . ") from $funcName : ", 
            false, 
            OutSystem::LEVEL_NOTICE
        );
        
        reSwitch:
		switch ($this->errorCode) {
            /**
             * handle generic 'Syntax error or access violation'
            */
            case "HY000":
                /**
                 * Master error code for error 2006
                */
            case "22001":
                /**
                 * Master error code for error 1406
                */
            case 42000:
                $err_msg = \explode(':', $this->errorMsg);

                $newErrorCode = intval( \array_pop($err_msg) );

                if ($newErrorCode) {
                    $this->outSystem->stdout(
                        "Changing to ($newErrorCode): ", 
                        false, 
                        OutSystem::LEVEL_NOTICE
                    );

                    $this->errorCode = $newErrorCode;
                    goto reSwitch;
                } else {
                    $this->errorCode = 'unknow';
                    goto reSwitch;
                }
            break;
            /**
             * [ERROR] Unknown database
             * [SOLUTION] Creates a new databe with db name provided in error message
            */
			case 1049:
				if ($this->errorMsg === null) {
					$this->outSystem->stdout('Fail. No error message', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                } 
                
                $err_msg = \explode('\'', $this->errorMsg);
                
				if (!isset($err_msg[1])) {
                    $this->outSystem->stdout('Fail. Unable to find db name', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                } 
                
                if (!$this->newDataBase($err_msg[1])) 
                    return $retSpecific;
            break;
            /**
             * [ERROR]  Access denied for user ...
             * [SOLUTION] Force disconnect & reconnect to renew credentials
            */
			case 1044:
				$this->disconnect();
                
                if (!$this->connect()) 
                    return $retSpecific;
            break;
            /**
             * [ERROR] MySQL server has gone away. Lost db resource
             * [SOLUTION] Force disassociate current DB resource and reconnect using checkResource()
            */
			case 2006:
				if ($this->errorMsg === null) {
					$this->outSystem->stdout('Fail. No error message', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                } 
                
                if (\strpos($this->errorMsg, 'MySQL server has gone away') === false)
                    return $retSpecific;

                $this->disconnect();

                if (!$this->checkResource())
                    return $retSpecific;
            break;
            /**
             * [ERROR] Table doesn't exist
             * [SOLUTION] Creates table
            */
			case "42S02":
				if ($this->errorMsg === null) {
					$this->outSystem->stdout('Fail. No error message', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                } 

                $err_msg = \explode('\'', $this->errorMsg);

                if (isset($err_msg[1])) {
                    $database_name = explode('.', $err_msg[1], 2);
                    if ($database_name[0] !== $this->mysqlDb) {
                        $this->outSystem->stdout(
                            'Fail. Selected DB ' . $this->mysqlDb . ' not match erro DB ' .$database_name[0], 
                            OutSystem::LEVEL_NOTICE
                        );
                        return $retSpecific;
                    }

                    $table_name = \str_replace( $this->mysqlDb . '.', "", $err_msg[1]);
                    
                    if ($table_name == '' || $table_name != $database_name[1]) {
                        $this->outSystem->stdout(
                            "Fail. Double check table name '$table_name' and  " . $database_name[1], 
                            OutSystem::LEVEL_NOTICE
                        );
                        return $retSpecific;
                    }

                    if (!$this->newTable($table_name)) 
                        return $retSpecific;
                    
                } else {
                    $this->outSystem->stdout('Fail. Unable to find table name', OutSystem::LEVEL_NOTICE);
                    return $retSpecific;
                }
            break;
            /**
             * [ERROR] Column doesn't exist
             * [SOLUTION] Creates column name
            */
			case "42S22":
				if (!isset($funcArgs[0])) {
                    $this->outSystem->stdout(
                        'Fail. Not all args set in pre-called function', 
                        OutSystem::LEVEL_NOTICE
                    );
					return $retSpecific;
                }
                
				if ($this->errorMsg === null) {
					$this->outSystem->stdout('Fail. No error message', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                }  

                $err_msg = \explode('\'', $this->errorMsg);
                
				if (!isset($err_msg[1])) {
                    $this->outSystem->stdout('Fail. Could not find column name', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                } 
                
				if ($funcName == "insert") {
                    if (\count($funcArgs) === 2 && 
                        isset($funcArgs[1]['table'])) {
                        
                        if ($funcArgs[1]['bulk']) { // Create based on first 
                            /**
                             * 0 => col_1, col_2 ...
                             * 1 => val_1, val_2 ...
                             * 2 => Foo, Bar ...
                            */
                            $key = array_search($err_msg[1], $funcArgs[0][0]);

                            if ($key !== false && isset($funcArgs[0][1][$key])) {
                                $len = \strlen((string) $funcArgs[0][1][$key]);

                                if (!$this->newColumn(
                                    $err_msg[1], 
                                    [
                                        'table' => $funcArgs[1]['table'],
                                        'type' => $this->getVarType($funcArgs[0][1][$key]),
                                        'size' => (
                                            \is_string($funcArgs[0][1][$key]) ?
                                            $len :
                                            ($len > 10 ? 10 : $len )
                                        )
        
                                    ]
                                )) 
                                    return $retSpecific;
        
                                break;
                            }
                            
                        } else if (isset($funcArgs[0][ $err_msg[1] ])) {
                            $len = \strlen((string) $funcArgs[0][ $err_msg[1] ]);
    
                            if (!$this->newColumn(
                                $err_msg[1], 
                                [
                                    'table' => $funcArgs[1]['table'],
                                    'type' => $this->getVarType($funcArgs[0][ $err_msg[1] ]),
                                    'size' => (
                                        \is_string($funcArgs[0][ $err_msg[1] ]) ?
                                        $len :
                                        ($len > 10 ? 10 : $len )
                                    )
    
                                ]
                            )) 
                                return $retSpecific;
    
                            break;
                        }
                    }
                }
                
                $this->outSystem->stdout('Fail. Could not be handled with this imputs', OutSystem::LEVEL_NOTICE);
				return $retSpecific;
            break;
            /**
             * [ERROR] Data too long for column. Input data not fit in current column size
             * [SOLUTION] Increase column size
            */
            case "1406":
                if ($this->errorMsg === null) {
					$this->outSystem->stdout('Fail. No error message', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                } 
                
                $err_msg = \explode('\'', $this->errorMsg);
                
				if (!isset($err_msg[1])) {
                    $this->outSystem->stdout('Fail. Unable to find column name', OutSystem::LEVEL_NOTICE);
					return $retSpecific;
                } 

                $changeColumnSpecsConfig = [
                    'size' => 0
                ];

                foreach ($funcArgs as $funcArg) {
                    if (\is_array($funcArg)) { // Add configs like table name 
                        $changeColumnSpecsConfig += $funcArg;
                    } else if (\is_string($funcArg) && $funcArg != $err_msg[1]) { // If not the column name
                        if ($changeColumnSpecsConfig['size']) {
                            $this->outSystem->stdout(
                                'Fail. Unable determine which arg provided is the column value', 
                                OutSystem::LEVEL_NOTICE
                            );
					        return $retSpecific;
                        } else {
                            $changeColumnSpecsConfig['size'] = \strlen((string) $funcArg);
                        }
                    }
                }

                if (!$changeColumnSpecsConfig['size']) {
                    $this->outSystem->stdout('Fail. Unable determine column value size', OutSystem::LEVEL_NOTICE);
                    return $retSpecific;
                }

                if (!$this->changeColumnSpecs($err_msg[1], $changeColumnSpecsConfig)) 
                    return $retSpecific;
                    
                
            break;
            default:
                $this->outSystem->stdout(
                    "Fail. Code '" . $this->errorCode . "' not handled", 
                    OutSystem::LEVEL_NOTICE
                );
        
                $this->errorCode = null;
                $this->errorMsg = null;

				return $retSpecific;
			break;
        }
        
		$this->errorCode = null;
        $this->errorMsg = null;

        /**
         * After the problem is solved, try running the previous function again
        */
        return \call_user_func_array(
            [
                ($class === __NAMESPACE__ ? $this : $class), 
                $funcName
            ], 
            $funcArgs
        );

        return true;
    }
	
	// Use this for non prepared PDO statements
	static function mysql_escape_mimic($inp) {
		if (is_array($inp))
			return array_map(__METHOD__, $inp);

		if (!empty($inp) && \is_string($inp)) {
			return str_replace(
				['\\', "\0", "\n", "\r", "'", '"', "\x1a"], 
				['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], 
				$inp
			);
		}

		return $inp;
	} 
}