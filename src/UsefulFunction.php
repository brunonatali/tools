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
}