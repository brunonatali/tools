<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class UsefulFunction
{
    Public static function implodeRecursive(array $input, int $deep = 10)
    {
        $toReturn = null;
        foreach ($input as $key => $value) {
            $toReturn .= (string)$key . ": ";
            if (is_array($value)) {
                $deep --;
                $toReturn .= ($deep ? $this->implodeRecursive($value, $deep) : "[...]");
            } else {
                $toReturn .= (is_bool($value) ? ($value ? "true" : "false") : (string)$value);
            }
        }
    }
}