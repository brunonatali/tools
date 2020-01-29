<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class UsefulFunction
{
    Public static function implodeRecursive(array $input, int $deep = 10): string
    {
        $toReturn = null;
        foreach ($input as $key => $value) {
            $toReturn .= ($toReturn ? ", " . (string)$key . ": " : (string)$key . ": ");
            if (is_array($value)) {
                $deep --;
                $toReturn .= ($deep ? UsefulFunction::implodeRecursive($value, $deep) : "[...]");
            } else {
                $toReturn .= (is_bool($value) ? ($value ? "true" : "false") : (string)$value);
            }
        }
		if ($toReturn === null) return "[]";
		return "[$toReturn]";
    }
}