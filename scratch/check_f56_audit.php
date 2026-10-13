<?php
$data = json_decode(file_get_contents(__DIR__ . '/feeder56_pre_audit.json'), true);
if (isset($data['feeder_matrix'])) {
    foreach ($data['feeder_matrix'] as $f) {
        if ($f['penyulang_id'] == 56 || stripos($f['nama_penyulang'], 'BANJARAN') !== false) {
            echo "Found in feeder_matrix:\n";
            print_r($f);
            break;
        }
    }
}
