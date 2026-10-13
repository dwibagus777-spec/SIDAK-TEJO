<?php
$ch = curl_init('https://sidaktejo.site/remediation/audit');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Audit-Token: sidak_transline_audit_2026']);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$code}\n";
echo "Body: {$res}\n";
