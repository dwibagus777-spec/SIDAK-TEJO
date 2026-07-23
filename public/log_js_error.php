<?php
$raw = file_get_contents('php://input');
if ($raw) {
    file_put_contents(__DIR__ . '/js_errors.txt', date('Y-m-d H:i:s') . ' - ' . $raw . "\n", FILE_APPEND);
}
echo "Logged";
