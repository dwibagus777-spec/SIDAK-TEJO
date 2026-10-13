<?php
// Quick diagnostic: dump raw JSON from /audit and /forensic-reconciliation
$AUDIT_TOKEN = 'sidak_transline_audit_2026';
$BASE_URL    = 'https://sidaktejo.site';

function curlGet(string $url, array $headers): string
{
    $hArgs = '';
    foreach ($headers as $h) {
        $hArgs .= ' -H ' . escapeshellarg($h);
    }
    $tmpBody = tempnam(sys_get_temp_dir(), 'diag_');
    $cmd = "curl.exe -s -k --max-time 60{$hArgs} -o " . escapeshellarg($tmpBody) . " " . escapeshellarg($url);
    shell_exec($cmd);
    $body = file_get_contents($tmpBody);
    @unlink($tmpBody);
    return $body;
}

$auditHeaders = ['X-Audit-Token: ' . $AUDIT_TOKEN];

echo "=== /remediation/audit ===\n";
$a = curlGet($BASE_URL . '/remediation/audit', $auditHeaders);
echo $a . "\n\n";

echo "=== /remediation/forensic-reconciliation ===\n";
$r = curlGet($BASE_URL . '/remediation/forensic-reconciliation', $auditHeaders);
echo $r . "\n";
