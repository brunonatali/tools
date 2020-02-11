<?php declare(strict_types=1);

/** 
*   FILE INFORMATION
*
*   This was designed to provide such as examples and make some basic tests
*   Normally you can bypass "test..." functions.
*/

use BrunoNatali\Tools\FileHandler;
use BrunoNatali\Tools\OutSystem;

require __DIR__ . '/../vendor/autoload.php';

// Configuration
$debugConfig = [
    "outSystemName" => "Test-FileHandler",
    "outSystemEnabled" => true
];

// Pre-declaration
$loop = React\EventLoop\Factory::create();
$debug = new OutSystem($debugConfig);

$debug->stdout("Start");

$file = new FileHandler($loop, __FILE__); // Load this file into API
testConstruct($file);

$fileContent = null;
$file->getContent()->then(function ($content) use ($debug, &$fileContent) {
    $debug->stdout("Get this file content");
    $fileContent = $content;
}, 
function ($e) use ($debug) {
    $debug->stdout("Error get this file content: " . $e->getMessage());
});
testContent($fileContent);



$loop->run();


function testConstruct($object): void
{
    global $debug;
    if (is_object($object) && $object instanceof FileHandlerInterface) {
        $debug->stdout("Object creation successfully");
    } else {
        $debug->stdout("Object created with wrong class: " . get_class($file));
        exit;
    }
}

function testContent($content): void
{
    global $debug;
    $naturalFileGetContents = file_get_contents(__FILE__);
    $naturalFileLen = strlen($naturalFileGetContents);
    $apiFileLen = strlen($content);

    $debug->stdout("File content specs:\r\n\tPHP-lenght: $naturalFileLen\r\n\tAPI-lenght: $apiFileLen");

    if ($naturalFileLen !== $apiFileLen) {
        $debug->stdout("API file content:\r\n\r\n", false);
        $debug->stdout($content);
        exit;
    }
}