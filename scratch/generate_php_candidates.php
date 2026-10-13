<?php

$mapped = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/mapped_26_kodes.json'), true);

$lines = [];
$lines[] = '    public function getB2AcceptedCandidates(): array';
$lines[] = '    {';
$lines[] = '        return [';

foreach ($mapped as $c) {
    $geo = var_export($c['geometry'], true);
    $geo = str_replace(["\n", "  "], ["", " "], $geo);
    $lines[] = "            [";
    $lines[] = "                'natural_key'       => '{$c['natural_key']}',";
    $lines[] = "                'penyulang_id'      => {$c['penyulang_id']},";
    $lines[] = "                'source_row_no'     => {$c['source_row_no']},";
    $lines[] = "                'target_row_no'     => {$c['target_row_no']},";
    $lines[] = "                'source_asset_name' => '{$c['source_asset_name']}',";
    $lines[] = "                'target_asset_name' => '{$c['target_asset_name']}',";
    $lines[] = "                'source_kode_asset' => '{$c['source_kode_asset']}',";
    $lines[] = "                'target_kode_asset' => '{$c['target_kode_asset']}',";
    $lines[] = "                'distance_meters'   => {$c['distance_meters']},";
    $lines[] = "                'conductor_type'    => '{$c['conductor_type']}',";
    $lines[] = "                'conductor_size'    => '{$c['conductor_size']}',";
    $lines[] = "                'confidence'        => {$c['confidence']},";
    $lines[] = "                'geometry'          => {$geo},";
    $lines[] = "            ],";
}

$lines[] = '        ];';
$lines[] = '    }';

file_put_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/method_get_candidates.php', implode("\n", $lines));
echo "Regenerated method in scratch/method_get_candidates.php with authoritative kode_asset (" . count($mapped) . " candidates)\n";
