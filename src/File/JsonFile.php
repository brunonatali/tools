<?php declare(strict_types=1);

namespace BrunoNatali\Tools\File;

class JsonFile
{

    public static function readAsArray(string $file, ...$incPath): array
    {
        foreach ($incPath as $newPath) 
            \set_include_path($newPath);

        $content = @\file_get_contents($file, FILE_USE_INCLUDE_PATH | FILE_TEXT);
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

        return (
            \is_integer( 
                \file_put_contents(
                    $file, 
                    \json_encode( 
                        \array_merge(
                            ($overwrite ? [] : self::readJsonAsArray($file, $incPath)), 
                            $dataArray
                        )
                    ), 
                    FILE_USE_INCLUDE_PATH | FILE_TEXT
                )
            ) ? true : false
        );
    }
}