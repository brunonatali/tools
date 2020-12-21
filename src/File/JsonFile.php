<?php declare(strict_types=1);

namespace BrunoNatali\Tools\File;

class JsonFile
{

    public static function readAsArray(string $file, ...$incPath): array
    {
        foreach ($incPath as $newPath) 
            \set_include_path($newPath);

        //$content = @\file_get_contents($file, FILE_USE_INCLUDE_PATH | FILE_TEXT);
        $content = @\file_get_contents($file, true, null); // for php < v6 (probably bug in php v7.4)
        if ($content === false)
            return [];

        if (!\is_array($content = \json_decode($content, true)))
            return [];

        return $content;
    }

    public static function saveArray(string $file, array $dataArray, bool $overwrite = false, ...$usrArgs): bool
    {
        if (empty($dataArray))
            return false;

        if (\file_exists($file)) { // Check if file is writable
            if (!\is_writable($file))
                return false;
        } else { // Check if directory is writable
            if (!\is_writable(\dirname($file)))
                return false;
        }

        $args = [$file];
        $flags = 0;
        foreach ($usrArgs as $value) {
            if (\is_string($value))
                $args[] = $value;
            else if (\is_integer($value))
                $flags = $flags | $value;
        }
        
        return (
            \is_integer( 
                \file_put_contents(
                    $file, 
                    \json_encode( 
                        \BrunoNatali\Tools\UsefulFunction::array_merge_recursive(
                            ($overwrite ? [] : \call_user_func_array([__NAMESPACE__ . '\JsonFile', 'readAsArray'], $args)), 
                            $dataArray
                        ),
                        $flags
                    ), 
                    FILE_USE_INCLUDE_PATH | FILE_TEXT
                )
            ) ? true : false
        );
    }
}