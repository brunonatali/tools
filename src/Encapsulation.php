<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class Encapsulation
{

    Public static function formatClassConfigs4InitializationPrint(array $args = []): string
    {
        if (!empty($args)) {
            $compose = "Started with configs:";
            foreach ($compose as $key => $value) {
                $compose .= PHP_EOL . "\t";
                if (is_int($key)) {
                    $compose .= (is_array($value) ? implode(';', $value) : (is_bool($value) ? ($value ? 'true' : 'false') : $value));
                } else {
                    $compose .= "'$key' => " . (is_array($value) ? implode(';', $value) : (is_bool($value) ? ($value ? 'true' : 'false') : $value));
                }
            }
        } else {
            $compose = "Started with no configs.";
        }
        return $compose;
    }
}