<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class FunctionMultiFunction
{
    Public $initialized = false;
    Public $functions;
    Protected $myName = null;
    Protected $outSystem;

    function __construct(array $config = [])
    {
        if (isset($config['functionName'])) $this->myName = $config['functionName'];

        $config = OutSystem::helpHandleAppName( 
            $config,
            ["outSystemName" => "FunctionMF-" . $this->myName]
        );
        $this->outSystem = new OutSystem($config);

        // Debug class initialization
        // $this->outSystem->stdout(BrunoNatali\Tools\Encapsulation::formatClassConfigs4InitializationPrint($config));
        
        $this->functions = [
            0 => function () {return true;} // Basic function to prevent non initialized to crash app
        ];
    }

    Public function add(callable $function, $index = null): int
    {
        if ($index === null) {
            if ($this->initialized) {
                $index = count($this->functions);
            } else {
                $index = 0; // Reset functions
                $this->initialized = true;
            }
        } else if (!$this->initialized) {
            unset($this->functions[0]); // Reset functions
            $this->initialized = true;
        }
        $this->outSystem->stdout("Add function $index", OutSystem::LEVEL_ALL);

        $this->functions[$index] = $function;
        return $index;
    }

    Public function exec(...$args): bool
    {
        $this->outSystem->stdout("Executing functions", OutSystem::LEVEL_ALL);

        foreach ($this->functions as $function) $function($args);

        return true; // Return when all functions are handled
    }
}