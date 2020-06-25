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

    public static function saveArray(string $file, array $dataArray, bool $overwrite = false, ...$incPath): bool
    {
        if (empty($dataArray))
            return false;

        $args = [$file];
        foreach ($incPath as $value)
            $args[] = $value;
        
        return (
            \is_integer( 
                \file_put_contents(
                    $file, 
                    \json_encode( 
                        \array_merge(
                            ($overwrite ? [] : \call_user_func_array([__NAMESPACE__ . '\JsonFile', 'readAsArray'], $args)), 
                            $dataArray
                        )
                    ), 
                    FILE_USE_INCLUDE_PATH | FILE_TEXT
                )
            ) ? true : false
        );
    }
}