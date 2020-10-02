<?php declare(strict_types=1);

namespace BrunoNatali\Tools\File;

use BrunoNatali\Tools\OutSystem;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;

class OnFileChange implements OnFileChangeInterface
{
    private $loop = null;

    private $file = null;

    private $call = null;

    private $config = [];

    private $timers = [];

    private $lastModifiedDate = null;

    private $system = self::USE_SYSTEM_POLLING;
    
    protected $outSystem;

    function __construct(...$configs)
    {
        /**
         * Construct app configs
        */
        foreach ($configs as $config) 
            if ($config instanceof LoopInterface)
                $this->loop = $config;
            else if (\is_array($config))
                $this->config += $config;
            else if (\is_string($config))
                $this->file = $config;
            else if (\is_callable($config))
                $this->call = $config;

        if ($this->file === null)
            throw new \Exception("Provide a file name", self::ERROR_FILE_NAME_ABSENT);
            
        if ($this->call === null)
            throw new \Exception("Provide a function to call OnFileChange", self::ERROR_FILE_CALL_ABSENT);
        
        // Declare unset parameters
        $this->config += [
            'client_name' => 'FILE-X',
            'auto_start' => true,
            'polling_time' => 1 // 1 second
        ];

        if ($this->loop === null)
            $this->loop = Factory::create();

        $config = OutSystem::helpHandleAppName( 
            $this->config,
            [
                "outSystemName" => $this->config['client_name']
            ]
        );
        $this->outSystem = new OutSystem($config);

        /**
         * Check inotify extension & try to load if is possible
        */
        if (\extension_loaded('inotify') === false) {
            if (\function_exists('dl'))
                try {
                    if (!\dl('inotify'))
                        $this->outSystem->stdout(
                            "[WARNING] Could not find 'inotify' extension. Consider install, to improve performance", 
                            OutSystem::LEVEL_NOTICE
                        );
                    else
                        $this->system = self::USE_SYSTEM_INOTIFY;
                } catch (\Exception $e) {
                    $this->outSystem->stdout(
                        "[WARNING] Auto load 'inotify' extension fail. " . $e->getMessage() .
                            "Consider load into php.ini, to improve performance", 
                        OutSystem::LEVEL_NOTICE
                    );
                }
            else
                $this->outSystem->stdout(
                    "[WARNING] Consider load 'inotify' into php.ini, to improve performance", 
                    OutSystem::LEVEL_NOTICE
                );
        } else {
            $this->system = self::USE_SYSTEM_INOTIFY;
        }

        if ($this->config['auto_start'])
            $this->start();
    }

    public function start()
    {
        switch ($this->system) {
            case self::USE_SYSTEM_POLLING:
                $this->timers['polling_timer'] = $this->loop->addPeriodicTimer(
                    $this->config['polling_time'],
                    function () {
                        if ($this->lastModifiedDate) {
                            if (self::isFileChanged($this->file, $this->lastModifiedDate))
                                ($this->call)($this->lastModifiedDate);
                        } else {
                            self::isFileChanged($this->file, $this->lastModifiedDate);
                        }
                    }
                );
                break;
            case self::USE_SYSTEM_INOTIFY:
                # code...
                break;
        }
    }

    public function stop()
    {
        switch ($this->system) {
            case self::USE_SYSTEM_POLLING:
                if (!isset($this->timers['polling_timer']) || $this->timers['polling_timer'] === null)
                    $this->loop->cancelTimer($this->timers['polling_timer']);
                break;
            case self::USE_SYSTEM_INOTIFY:
                # code...
                break;
        }
    }

    public function setPollingTime(int $time):bool
    {
        if (!isset($this->timers['polling_timer']) || $this->timers['polling_timer'] === null)
            return false;

        $this->stop();

        $this->config['polling_time'] = $time;

        $this->start();

        return null;
    }

    public static function isFileChanged(string $file, int &$lastModifiedDate = null): bool
    {
        if (!\file_exists($file))
            throw new \Exception("File '$file' not exist", self::ERROR_FILE_NOT_EXIST);

        $result =  $lastModifiedDate !== ($currentDate = \filemtime($file));
        $lastModifiedDate = $currentDate;
        \clearstatcache();

        return $result;
    }
}