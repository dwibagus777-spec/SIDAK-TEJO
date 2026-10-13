<?php

$file = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json';
$data = json_decode(file_get_contents($file), true);

$feeders = $data['feeder_matrix'] ?? [];
foreach ($feeders as $f) {
    if (($f['existing_translines'] ?? 0) > 0) {
        echo sprintf("Audit 2026-09-13 | Feeder #%-3d | %-20s | Existing Translines: %d\n",
            $f['penyulang_id'],
            $f['nama_penyulang'],
            $f['existing_translines']
        );
    }
}
