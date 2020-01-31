<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

Class MultiFunction 
{
    Protected $outSystem;
    Private $sharedConfig;

    function __construct(array $config = [])
    {
        $config = OutSystem::helpHandleAppName( 
            $config,
            ["outSystemName" => "MultiFunction"]
        );
        $this->outSystem = new OutSystem($config);

        // Debug class initialization
        // $this->outSystem->stdout(BrunoNatali\Tools\Encapsulation::formatClassConfigs4InitializationPrint($config));

        $this->sharedConfig = $config;
    }

    Public function new(string $name, callable $function = null, $index = null)
    {
        $this->$name = new FunctionMultiFunction(
            array_merge($this->sharedConfig, ['functionName' => $name])
        );

        if (is_callable($function)) $this->$name->add($function, $index);
        $this->outSystem->stdout("New instance $name", OutSystem::LEVEL_ALL);
    }

    Public function destroy(string $name)
    {
        unset($this->$name);
    }
}