<?php
$code = file_get_contents(__DIR__ . '/complete_replacement.php');
// Strip <?php
$body = preg_replace('/^<\?php\s*/', '', $code);
$testCode = "<?php\nclass DummyController {\n" . $body . "\n}\n";
file_put_contents(__DIR__ . '/wrapped_test.php', $testCode);
$res = exec('php -l ' . escapeshellarg(__DIR__ . '/wrapped_test.php'), $output, $ret);
echo implode("\n", $output) . "\n";
if ($ret === 0) {
    echo "SYNTAX OK!\n";
} else {
    echo "SYNTAX ERROR!\n";
}
