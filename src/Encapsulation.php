<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class Encapsulation
{

    Public static function formatClassConfigs4InitializationPrint(array $args = []): string
    {
        if (!empty($args)) {
            $compose = "Started with configs:";
            foreach ($args as $key => $value) {
                $compose .= PHP_EOL . "\t";
                if (is_int($key)) $compose .= "'$key' => ";
                if (is_array($value)) {
                    $compose .= UsefulFunction::implodeRecursive($value);
                } else if (is_bool($value)) {
                    $compose .= ($value ? 'true' : 'false');
                } else if (is_object($value)) {
                    if (method_exists($value, '__toString'))
                        $compose .= (string)$value;
                    else 
                        $compose .= get_class($value);
                } else {
                    $compose .= $value;
                }
            }
        } else {
            $compose = "Started with no configs.";
        }
        return $compose;
    }
}