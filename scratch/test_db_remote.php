<?php

$host = '91.108.119.5';
$port = 3306;
$t = @fsockopen($host, $port, $errno, $errstr, 5);
if ($t) {
    echo "Port 3306 on {$host} is OPEN!\n";
    fclose($t);
} else {
    echo "Port 3306 on {$host} is CLOSED or FIREWALLED ({$errno}: {$errstr})\n";
}
