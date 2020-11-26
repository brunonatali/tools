<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class FunctionMultiFunction
{
    public $functions = [];

    private $sysConfig = [];

    protected $initialized = false;

    protected $outSystem;

    function __construct(array $configs = [])
    {
        $this->sysConfig = $configs += [
            'name' => null // Function Multi Function
        ];

        $config = OutSystem::helpHandleAppName( 
            $this->sysConfig,
            [
                "outSystemName" => "FMF" . ($this->sysConfig['name'] ? '-' . $this->sysConfig['name'] : '')
            ]
        );

        $this->outSystem = new OutSystem($config);
    }

    public function add($callback, int $index = null): int
    {
        if ($index === null && ($index = \array_key_last($this->functions)) === null)
            $index = 0;

        $this->outSystem->stdout("Add function $index", OutSystem::LEVEL_ALL);

        $this->functions[$index] = $callback;

        return $index;
    }

    public function del(int $index): bool
    {
        if (isset($this->functions[$index])) {
            unset($this->functions[$index]);
            return true;
        }

        return false;
    }

    public function exec(...$args): bool
    {
        if (empty($this->functions))
            return false;

        $this->outSystem->stdout("Executing functions", OutSystem::LEVEL_ALL);

        foreach ($this->functions as $callback)
            ($callback)(...$args);
            
        return true; // Return when all functions are handled
    }
}