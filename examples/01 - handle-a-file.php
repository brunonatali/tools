<?php declare(strict_types=1);

/** 
*   FILE INFORMATION
*
*   This was designed to provide such as examples and make some basic tests
*   Normally you can bypass "test..." functions.
*/

use BrunoNatali\Tools\FileHandler;
use BrunoNatali\Tools\OutSystem;

// Include autoload
try {
	if (!@include_once(__DIR__ . '/../vendor/autoload.php'))
		if (!@include_once(__DIR__ . '/../../../autoload.php')) // Case running directelly from examples folder
			throw new Exception('Could not find autoload.php');
} catch (Exception $e) {
	echo $e->getMessage();
}

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
$file->getContent()->then(function ($content) use ($debug, &$fileContent, $file) {
    $debug->stdout("Get this file content");
    $fileContent = $content;
    testContent($fileContent);
    $fileContent = '';
    getPartialContent();
}, 
function ($e) use ($debug) {
    $debug->stdout("Error get this file content: " . $e->getMessage());
});

$debug->stdout("Get file content & generate a opened error");
$fileContentB = '';
getPartialContent();

$loop->run();

function getPartialContent()
{
    global $file, $fileContent, $debug;
    $file->getBytes(200)->then(function ($content) use ($debug, &$fileContent) {
        $currentContentLen = strlen($content);
        $debug->stdout("Catch -> " . $currentContentLen);
        $fileContent .= $content;
        if ($currentContentLen === 0) testContent($fileContent);
        else getPartialContent();
    }, 
    function ($e) use ($debug) {
        $debug->stdout("Error get content part: " . $e->getMessage());
    });
}

function testConstruct($object): void
{
    global $debug, $file;
    if (is_object($object) && $object instanceof BrunoNatali\Tools\FileHandlerInterface) {
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