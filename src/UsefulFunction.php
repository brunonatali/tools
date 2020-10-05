<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class UsefulFunction
{
    public static function implodeRecursive(array $input, int $deep = 10): string
    {
        $toReturn = null;
        foreach ($input as $key => $value) {
            $toReturn .= ($toReturn ? ", " . (string)$key . ": " : (string)$key . ": ");
            if (\is_array($value)) {
                $deep --;
                $toReturn .= ($deep ? UsefulFunction::implodeRecursive($value, $deep) : "[...]");
            } else {
                $toReturn .= (is_bool($value) ? ($value ? "true" : "false") : (string)$value);
            }
        }
		if ($toReturn === null) return "[]";
		return "[$toReturn]";
    }

    /**
     * Recursive merge arrays replacing value when $key is the same
    */
    public static function array_merge_recursive (array ...$arrays): array
    {
        $merged = $arrays[0];
        unset($arrays[0]);

        foreach($arrays as &$array)
            foreach ($array as $key => &$value)
                if (\is_array($value) 
                    && isset($merged[$key]) && \is_array($merged[$key])) {
                    $merged[$key] = static::array_merge_recursive ($merged[$key], $value);
                } else {
                    $merged[$key] = $value;
                }

        return $merged;
    }

    /**
     * List folder contents
    */
    public static function listFolder(string $path, bool $onlyDir = false): array
    {
        $list = \array_diff(\scandir($path), ['..', '.']);

        if ($onlyDir) {
            if ($path{ (\strlen($path) -1) } !== '/') 
                $path .= '/'; 

            foreach ($list as $index => $name) 
                if (!\is_dir($path . $name))
                    unset($list[ $index ]);
        }

        return $list;
    }

    /**
     * Traces functions and / or classes 
    */
    public static function debug_backtrace(array &$function = [], array &$class = [])
    {
        foreach (debug_backtrace() as $trace) {
            if ($trace['function'] === 'debug_backtrace')
                continue;
            
            if (isset($trace['class']))
                $class[] = $trace['class'];

            $function[] = $trace['function'];
        }
        
        return null;
    }

    /**
     * Compares two array and return tru if array is exactly equal (===)
    */
    public static function areArraysTheSame(array &$arrayA, array &$arrayB): bool
    {
        if (\count($arrayA) !== \count($arrayB))
            return false;

        foreach($arrayA as $k => $a) {
            if (!isset($arrayB[$k])) 
                return false;

            if (\is_array($a)) {
                if (\is_array($arrayB[$k])) {
                    if (!self::areArraysTheSame($a, $arrayB[$k]))
                        return false;
                }

                return false;
            } else if ($a !== $arrayB[$k]) {
                return false;
            }
        }
        
        return true;
    }
}