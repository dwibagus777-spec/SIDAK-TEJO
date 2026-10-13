<?php
$data = json_decode(file_get_contents('scratch/resp_global_topology_audit.json'), true);
$matrix = $data['feeder_matrix'] ?? [];
$totalSections = 0;
$feedersWithSections = 0;
$feedersWithoutSections = 0;
$feederSectionList = [];

foreach ($matrix as $f) {
    $sc = (int)($f['section_count'] ?? 0);
    $totalSections += $sc;
    if ($sc > 0) {
        $feedersWithSections++;
        $feederSectionList[] = [
            'id' => $f['penyulang_id'],
            'name' => $f['nama_penyulang'],
            'ulp' => $f['nama_ulp'],
            'section_count' => $sc,
            'assets' => $f['total_jtm_assets']
        ];
    } else {
        $feedersWithoutSections++;
    }
}

echo "Total Feeders: " . count($matrix) . "\n";
echo "Total Sections in 'sections' table: {$totalSections}\n";
echo "Feeders with sections (>0): {$feedersWithSections}\n";
echo "Feeders with 0 sections: {$feedersWithoutSections}\n\n";

echo "Sample Feeders with sections:\n";
for ($i = 0; $i < min(15, count($feederSectionList)); $i++) {
    $fs = $feederSectionList[$i];
    printf("  Feeder #%-3d | %-24s | %-18s | Sections: %2d | Assets: %3d\n",
        $fs['id'], $fs['name'], $fs['ulp'], $fs['section_count'], $fs['assets']
    );
}
