<?php declare(strict_types=1);

namespace BrunoNatali\Tools;

class SysInfo implements SysInfoInterface
{
    Public static function netstat(...$imputs): array
    {
        $filter = false;
        $remFilter = null;
        $header = false;
        foreach ($imputs as $arg) {
            if (is_array($arg)) { // filter response using grep
                $filter = implode("\|", $arg) . "'";
            } else if ($arg === true) { // enable response header
                $header = true;
            } else if (is_string($arg)) { // remove words filter
                if ($remFilter) $remFilter .= "\|" . $arg;
                else $remFilter = $arg;
            }
        }
        // removed '-w', but need to find out another solution
        //$cmd = "netstat -an" . ($filter ? " | grep -w " . ($header ? '\'Proto RefCnt\|Proto Recv-Q\|' : "'") . $filter . ($remFilter ? " | grep -v '" . $remFilter . "'" : "")  : ($header ? " | grep -v 'Active Internet connections\|Active UNIX domain sockets" . ($remFilter ? "\|" . $remFilter . "'" : "'") : " | grep -v 'Proto RefCnt\|Proto Recv-Q\|Active Internet connections\|Active UNIX domain sockets" . ($remFilter ? "\|" . $remFilter . "'" : "'")));
        $cmd = "netstat -an" . ($filter ? " | grep " . ($header ? '\'Proto RefCnt\|Proto Recv-Q\|' : "'") . $filter . ($remFilter ? " | grep -vw '" . $remFilter . "'" : "")  : ($header ? " | grep -vw 'Active Internet connections\|Active UNIX domain sockets" . ($remFilter ? "\|" . $remFilter . "'" : "'") : " | grep -vw 'Proto RefCnt\|Proto Recv-Q\|Active Internet connections\|Active UNIX domain sockets" . ($remFilter ? "\|" . $remFilter . "'" : "'")));
        exec($cmd, $out);

        $outcount = count($out) - 1;
        if ($outcount > 0) {
            if (strpos($out[ $outcount ], "Proto Re") !== false) {
                unset($out[ $outcount ]);
            } else if (strpos($out[ 0 ], "Proto Re") !== false && strpos($out[ 1 ], "Proto Re") !== false) {
                unset($out[ 0 ]);
                $out = array_values($out);
            }
        }

        return $out;
    }

    Public static function ps(...$imputs): array
    {
        $filter = false;
        $header = false;
        foreach ($imputs as $arg) {
            if (is_array($arg)) { // filter response using grep
                $filter = implode("\|", $arg) . "'";
            } else if ($arg === true) { // enable response header
                $header = true;
            }
        }
        $cmd = "ps -aux" . ($filter ? " | grep " . ($header ? '\'USER\|' : "'") . $filter  : ($header ? '' : " | grep -v USER")) . " | grep -v 'grep\|ps'";
        exec($cmd, $out);
        return $out;
    }

    Public static function getCommandByProcName(...$imputs)
    {
        $catchArgs = false;
        foreach ($imputs as $key => $arg) {
            if ($arg === true) { // enable catch cmd args
                unset($imputs[$key]);
                $catchArgs = true;
            }
        }

        $commands = [];
        foreach (static::ps($imputs) as $procLine) {
            if ($catchArgs) 
                $column = explode(' ', $procLine);
            else
                $column = explode(' ', $procLine, 8);    
            if ($commands) {
                foreach ($commands as $storedCmd)
                    if ($storedCmd !== $column[7]) {
                        $commands[] = $column[7];
                        break;
                    }
            } else {
                $commands[] = $column[7];
            }
        }

        $cmdsFound = count($commands);
        if ($cmdsFound > 1) {
            return $commands;
        } else if ($cmdsFound) {
            return $commands[0];
        }

        return false;
    }

    Public static function getSystemType(): int
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			return self::SYSTEM_TYPE_WIN;
		} else {
			return self::SYSTEM_TYPE_UNIX;
		}
    }
}
?>
