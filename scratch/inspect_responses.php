<?php

$netFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_network_118.json';
$netRaw = file_get_contents($netFile);
$net = json_decode($netRaw, true);

echo "=== API-NETWORK VERIFICATION (Feeder 118: Bahagia Steel 1) ===\n";
echo "Status: " . ($net['status'] ?? 'N/A') . "\n";
echo "Total Assets: " . ($net['data']['summary']['total_assets'] ?? 'N/A') . "\n";
echo "Feeder Assets: " . ($net['data']['summary']['feeder_asset_count'] ?? 'N/A') . "\n";
echo "Network Truth Status: " . ($net['data']['meta']['network_truth']['status'] ?? 'N/A') . "\n";
echo "Authoritative Transline Count: " . ($net['data']['meta']['network_truth']['authoritative_transline_count'] ?? 'N/A') . "\n";
echo "Has Authoritative Transline: " . (($net['data']['meta']['network_truth']['has_authoritative_transline'] ?? false) ? 'true' : 'false') . "\n";
echo "Preview Available: " . (($net['data']['meta']['network_truth']['preview_available'] ?? false) ? 'true' : 'false') . "\n";
echo "Has Spatial Preview: " . (($net['data']['meta']['network_truth']['has_spatial_preview'] ?? false) ? 'true' : 'false') . "\n";
echo "Preview Segments Count: " . ($net['data']['meta']['network_truth']['preview_segments_count'] ?? 'N/A') . "\n";
echo "Transline Official Edges Count: " . count($net['data']['transline']['properties']['edges'] ?? []) . "\n";
echo "Translines Array Count: " . count($net['data']['translines'] ?? []) . "\n";
echo "Preview Segments Array Count: " . count($net['data']['preview_segments'] ?? []) . "\n";

echo "\nFirst preview segment metadata:\n";
if (!empty($net['data']['preview_segments'])) {
    $p0 = $net['data']['preview_segments'][0];
    echo "  Type: " . ($p0['type'] ?? 'N/A') . "\n";
    echo "  Persisted: " . (($p0['persisted'] ?? false) ? 'true' : 'false') . "\n";
    echo "  Authoritative: " . (($p0['authoritative'] ?? false) ? 'true' : 'false') . "\n";
    echo "  Topology: " . (($p0['topology'] ?? false) ? 'true' : 'false') . "\n";
}

$anaFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_118.json';
$anaRaw = file_get_contents($anaFile);
$ana = json_decode($anaRaw, true);

echo "\n=== API-CONDUCTOR-ANALYTICS VERIFICATION (Feeder 118: Bahagia Steel 1) ===\n";
echo "Status: " . ($ana['status'] ?? 'N/A') . "\n";
echo "Scope: " . ($ana['summary']['scope'] ?? 'N/A') . "\n";
echo "Is Authoritative Only: " . (($ana['summary']['is_authoritative_only'] ?? false) ? 'true' : 'false') . "\n";
echo "Preview Included: " . (($ana['summary']['preview_included'] ?? false) ? 'true' : 'false') . "\n";
echo "Transline Resmi Count: " . ($ana['summary']['transline_resmi_count'] ?? 'N/A') . "\n";
echo "Total Panjang Meter: " . ($ana['summary']['total_panjang_meter'] ?? 'N/A') . "\n";
echo "Total Panjang KM: " . ($ana['summary']['total_panjang_km'] ?? 'N/A') . "\n";
echo "Distinct Assets Count: " . ($ana['summary']['distinct_assets_count'] ?? 'N/A') . "\n";
echo "Breakdown Feeders Count: " . count($ana['breakdown']['feeders'] ?? []) . "\n";
echo "Breakdown Sections Count: " . count($ana['breakdown']['sections'] ?? []) . "\n";
echo "Breakdown ULPs Count: " . count($ana['breakdown']['ulps'] ?? []) . "\n";
echo "Items Count: " . count($ana['items'] ?? []) . "\n";

$f23File = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_23.json';
if (file_exists($f23File)) {
    $f23 = json_decode(file_get_contents($f23File), true);
    echo "\n=== API-CONDUCTOR-ANALYTICS VERIFICATION (Feeder 23: Gedangan) ===\n";
    echo "Status: " . ($f23['status'] ?? 'N/A') . "\n";
    echo "Scope: " . ($f23['summary']['scope'] ?? 'N/A') . "\n";
    echo "Transline Resmi Count: " . ($f23['summary']['transline_resmi_count'] ?? 'N/A') . "\n";
    echo "Total Panjang Meter: " . ($f23['summary']['total_panjang_meter'] ?? 'N/A') . "\n";
    echo "Total Panjang KM: " . ($f23['summary']['total_panjang_km'] ?? 'N/A') . "\n";
    echo "Distinct Assets Count: " . ($f23['summary']['distinct_assets_count'] ?? 'N/A') . "\n";
    echo "Items Count: " . count($f23['items'] ?? []) . "\n";
    if (!empty($f23['items'])) {
        $it0 = $f23['items'][0];
        echo "Sample Segment 0:\n";
        echo "  Conductor Type: " . ($it0['jenis_konduktor'] ?? 'N/A') . "\n";
        echo "  Conductor Size: " . ($it0['ukuran_konduktor'] ?? 'N/A') . "\n";
        echo "  Panjang (m): " . ($it0['panjang_meter'] ?? 'N/A') . "\n";
        echo "  Distance Provenance: " . ($it0['distance_provenance'] ?? 'N/A') . "\n";
        echo "  Source Pole: " . ($it0['source_pole']['nama_asset'] ?? 'N/A') . "\n";
        echo "  Target Pole: " . ($it0['target_pole']['nama_asset'] ?? 'N/A') . "\n";
    }
}


