<?php declare(strict_types=1);

namespace BrunoNatali\Tools\File;

use BrunoNatali\Inotify\Factory as Inotify;
use BrunoNatali\Tools\OutSystem;

use React\EventLoop\LoopInterface;

class OnFileChange implements OnFileChangeInterface
{
    /**
     * @var EventLoop
    */
    private $loop = null;

    /**
     * @var string Path and file name
    */
    private $file = null;

    /**
     * @var callable Triggered right after polling or new value from inotify
    */
    public $call = null;

    /**
     * @var Inotify
    */
    private $inotify = null;

    /**
     * @var array Stores app configuration
    */
    private $config = [];

    /**
     * @var array Registered timers
    */
    private $timers = [];

    /**
     * @var int|bool Buffer for filemtime() (Unix timestamp)
    */
    private $lastModifiedDate = null;

    /**
     * @var int Stores current method to get file notification
    */
    private $system = self::USE_SYSTEM_POLLING;
    
    /**
     * @var OutSystem
    */
    protected $outSystem;

    /**
     * This class was created to help monitor the change of attributes of a 
     * file, ie access, writing and removal
     * 
     * @param string Path and file name to watch
     * @param LoopInterface
     * @param callable Function to call after file changed
     * @param array Configurações possíveis são:
     *  'client_name' => (string) Debug output app name,
     *  'auto_start' => (bool) If will strt monitoring right after initialization,
     *  'force_ppolling' => (bool) Force system to use polling method or if will try to use intify,
     *  'polling_time' => (float) Time to polling if force_ppolling or inotify not instantiated,
     *  'specific_attr' => (int|array) Provide an Inotify constant to filter specific attr change
     *      NOTE 1. To specify more than one constant place each one in array. 
     *      NOTE 2. 'specific_attr' has no effect when running on polling method
    */
    function __construct(string $filePathName, LoopInterface &$loop, callable $callback, array $config = [])
    {
        $this->loop = &$loop;
        $this->file = $filePathName;
        $this->call = $callback;
        $this->config = $config;

        if ($this->file == null)
            throw new \Exception("Provide a file name", self::ERROR_FILE_NAME_ABSENT);
            
        if ($this->call == null)
            throw new \Exception("Provide a function to call OnFileChange", self::ERROR_FILE_CALL_ABSENT);

        if ($this->loop == null)
            throw new \Exception("Provide a LoopInterface variable", self::ERROR_FILE_LOOP_ABSENT);

        // Default configs
        $this->config += [
            'client_name' => 'FILE-X',
            'auto_start' => true,
            'force_ppolling' => false,
            'polling_time' => 1.0, 
            'specific_attr' => false
        ];

        /**
         * Instantiate a debug output system or declare an ghost
         *  OutSystem class to do not crash the code 
        */
        if (\class_exists('OutSystem')) {
            $config = OutSystem::helpHandleAppName( 
                $this->config,
                [
                    "outSystemName" => $this->config['client_name']
                ]
            );
            $this->outSystem = new OutSystem($config);
        } else {
            eval('class OutSystem
            {
                const LEVEL_NOTICE = 0xFF;
                public function stdout(...$discard) {}
            }');
            $this->outSystem = new OutSystem();
        }

        if (!$this->config['force_ppolling']) {
            /**
             * Check inotify extension & class declaration
            */
            if (\class_exists('Inotify'))
                try {
                    $this->inotify = new Inotify($this->loop);
                    $this->system = self::USE_SYSTEM_INOTIFY;
                } catch (\Exception $e) {
                    switch ($e->getCode()) {
                        case Inotify::EXCEPTION_EXTENSION_LOAD: 
                            $this->outSystem->stdout(
                                "[WARNING] Could not load 'inotify' extension. Consider install, to improve performance", 
                                OutSystem::LEVEL_NOTICE
                            );
                            break;
                        case Inotify::EXCEPTION_EXTENSION_INIT:
                            $this->outSystem->stdout(
                                "[WARNING] Error loading 'inotify'. System will be set to polling mode", 
                                OutSystem::LEVEL_NOTICE
                            );
                            break;
                    }
                }
            else
                $this->outSystem->stdout(
                    "[WARNING] Install 'brunonatali/inotify' through composer to improve performance", 
                    OutSystem::LEVEL_NOTICE
                );
        }

        if ($this->config['auto_start'])
            $this->start();
    }

    public function start(): bool
    {
        if (!$this->stop())
            return false;

        $toReturn = true;

        switch ($this->system) {
            case self::USE_SYSTEM_POLLING:
                // Very first polling request
                try {
                    self::isFileChanged($this->file, $this->lastModifiedDate);
                } catch (\Exception $e) {
                    $this->outSystem->stdout(
                        "Error reading file '$this->file': " . $e->getMessage(), 
                        OutSystem::LEVEL_NOTICE
                    );
                    $toReturn = false;
                }

                $this->timers['polling_timer'] = $this->loop->addPeriodicTimer(
                    $this->config['polling_time'],
                    function () {
                        if ($this->lastModifiedDate) {
                            try {
                                if (self::isFileChanged($this->file, $this->lastModifiedDate))
                                    ($this->call)($this->lastModifiedDate);
                            } catch (\Exception $e) {
                                $this->outSystem->stdout(
                                    "Error reading file '$this->file': " . $e->getMessage(), 
                                    OutSystem::LEVEL_NOTICE
                                );
                            }
                        }
                    }
                );
                break;
            case self::USE_SYSTEM_INOTIFY:
                if ($this->config['specific_attr']) {
                    if (\is_int($this->config['specific_attr'])) {
                        $notify->on($this->config['specific_attr'], [$this, 'call']);
                        return $notify->add($this->file, $this->config['specific_attr']);
                    } else if (\is_array($this->config['specific_attr'])) {
                        $mask = 0;
                        foreach ($this->config['specific_attr'] as $flag) {
                            $mask = $mask | $flag;
                            $notify->on($flag, [$this, 'call']);
                        }

                        return $notify->add($this->file, $mask);
                    } 
                    
                    return false;
                } else {
                    $notify->on('all', [$this, 'call']);
                    return $notify->add($this->file, IN_ATTRIB | IN_MODIFY);
                }
                break;
            default:
                return false;
                break;
        }

        return $toReturn;
    }

    public function stop(): bool
    {
        switch ($this->system) {
            case self::USE_SYSTEM_POLLING:
                if (!isset($this->timers['polling_timer']) || $this->timers['polling_timer'] === null)
                    $this->loop->cancelTimer($this->timers['polling_timer']);
                break;
            case self::USE_SYSTEM_INOTIFY:
                return $notify->remove($this->file);
                break;
        }

        return true;
    }

    public function setPollingTime(float $time): bool
    {
        if ($this->system !== self::USE_SYSTEM_POLLING)
            return false;

        $restart = false;

        if (isset($this->timers['polling_timer']) && $this->timers['polling_timer'] !== null) {
            $restart = true;
            if (!$this->stop())
                return false;
        }

        $this->config['polling_time'] = $time;

        if ($restart)
            return $this->start();

        return true;
    }

    /**
     * Auxiliary function to this class to check if file has changed
     * Could be called on demand to do a instant check
     * 
     * @param string $file File path and name
     * @param int &$lastModifiedDate Unix timestamp from last modification check
    */
    public static function isFileChanged(string $file, int &$lastModifiedDate = null): bool
    {
        if (!\file_exists($file))
            throw new \Exception("File '$file' not exist", self::ERROR_FILE_NOT_EXIST);

        \clearstatcache(true, $file);
            
        $result =  $lastModifiedDate !== ($currentDate = \filemtime($file));
        $lastModifiedDate = $currentDate;
        return $result;
    }
}