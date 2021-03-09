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
     * Compares two multidimensional (or not) arrays and return true if array is 
     *  exactly equal (===). Eg.
     *  $a = [1, 2, 3, 'a' => 4, ['b' => 5]];
     *  $b = ['a' => 4, 1, 2, 3, ['b' => 5]];
     *  returns TRUE
     * 
     * If you set $bInA to TRUE you can compare array B with array A, to verify if 
     *  content of array B is the same as array A. Eg. 
     *  $a = [1, 2, 3, 4, 5];
     *  $b = [1, 3, 5];
     *  returns TRUE
     * 
     * @param array &$arrayA First array 
     * @param array &$arrayB Second array
     * @param bool $bInA Check only if array B have same indexes & values in array A 
     * 
     * @return bool  
    */
    public static function areArraysTheSame(array &$arrayA, array &$arrayB, bool $bInA = false): bool
    {
        if (!$bInA && \count($arrayA) !== \count($arrayB))
            return false;

        foreach($arrayB as $k => $b) {
            if (!isset($arrayA[$k])) 
                return false;

            if (\is_array($b)) {
                if (!\is_array($arrayA[$k]) || !self::areArraysTheSame($arrayA[$k], $b, $bInA))
                    return false;
            } else if ($b !== $arrayA[$k]) {
                return false;
            }
        }
        
        return true;
    }
}