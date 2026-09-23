<?php

namespace App\Services;

use Config\AssetIngestion;
use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * CR-ASSET-01: Bulk Asset Ingestion & Reconciliation Service
 *
 * Responsibilities:
 * - High-throughput batch parsing and normalization of field asset CSVs.
 * - Geodetic bounding box validation (EAST_JAVA / SIDOARJO_REGENCY).
 * - Canonical nomenclature mapping (UP3, ULP, Feeder, Construction).
 * - 3-Tier Classification: AUTO_ACCEPT, AUTO_REVIEW, QUARANTINE.
 * - Identity confidence assessment: NEW, IDENTICAL, ENRICH, CONFLICT, DUPLICATE.
 * - Decoupled from topology: All valid electrical assets enter assets table with ASSET_EXISTS.
 */
class AssetMassIngestionService
{
    protected BaseConnection $db;
    protected AssetIngestion $config;
    protected array $feederCache = [];
    protected array $ulpCache = [];
    protected array $constructionCache = [];
    protected array $existingAssetCache = [];

    public function __construct(?BaseConnection $db = null, ?AssetIngestion $config = null)
    {
        $this->db = $db ?? Database::connect();
        $this->config = $config ?? new AssetIngestion();
        $this->warmCaches();
    }

    /**
     * Pre-warm caches for master tables to ensure high-throughput bulk processing.
     */
    protected function warmCaches(): void
    {
        // 1. Penyulang Cache
        $tablePenyulang = $this->db->tableExists('db_penyulang') ? 'db_penyulang' : 'penyulang';
        if ($this->db->tableExists($tablePenyulang)) {
            $feeders = $this->db->table($tablePenyulang)->get()->getResultArray();
            foreach ($feeders as $f) {
                $id = (int)$f['id'];
                $name = strtoupper(trim((string)($f['nama_penyulang'] ?? '')));
                $code = strtoupper(trim((string)($f['kode_penyulang'] ?? '')));
                if ($name !== '') $this->feederCache[$name] = $f;
                if ($code !== '') $this->feederCache[$code] = $f;
                // Also normalized without spaces
                $nameNoSpace = str_replace(' ', '', $name);
                if ($nameNoSpace !== '') $this->feederCache[$nameNoSpace] = $f;
            }
        }

        // 2. ULP Cache
        $tableUlp = $this->db->tableExists('db_ulp') ? 'db_ulp' : ($this->db->tableExists('ulps') ? 'ulps' : 'ulp');
        if ($this->db->tableExists($tableUlp)) {
            $ulps = $this->db->table($tableUlp)->get()->getResultArray();
            foreach ($ulps as $u) {
                $name = strtoupper(trim((string)($u['nama_ulp'] ?? '')));
                $code = strtoupper(trim((string)($u['kode_ulp'] ?? '')));
                if ($name !== '') $this->ulpCache[$name] = $u;
                if ($code !== '') $this->ulpCache[$code] = $u;
            }
        }

        // 3. Construction Types Cache
        if ($this->db->tableExists('construction_types')) {
            $codeCol = $this->db->fieldExists('construction_code', 'construction_types') ? 'construction_code' : ($this->db->fieldExists('code', 'construction_types') ? 'code' : 'kode_konstruksi');
            $nameCol = $this->db->fieldExists('construction_name', 'construction_types') ? 'construction_name' : ($this->db->fieldExists('name', 'construction_types') ? 'name' : 'nama_konstruksi');
            
            $constructions = $this->db->table('construction_types')->get()->getResultArray();
            foreach ($constructions as $ct) {
                $code = strtoupper(trim((string)($ct[$codeCol] ?? '')));
                if ($code !== '') {
                    $this->constructionCache[$code] = (int)$ct['id'];
                    $cleaned = str_replace(['-', ' ', '_'], '', $code);
                    $this->constructionCache[$cleaned] = (int)$ct['id'];
                }
            }
        }
    }

    /**
     * Load existing assets for targeted feeders to memory for sub-millisecond lookups.
     */
    public function loadExistingAssetsForFeeders(array $feederIds): void
    {
        if (empty($feederIds) || !$this->db->tableExists('assets')) {
            return;
        }

        $builder = $this->db->table('assets')
            ->select('id, kode_asset, nama_asset, penyulang_id, ulp_id, section_id, construction_type_id, lokasi, latitude, longitude')
            ->whereIn('penyulang_id', $feederIds)
            ->where('deleted_at IS NULL', null, false);

        $rows = $builder->get()->getResultArray();
        foreach ($rows as $r) {
            $code = strtoupper(trim((string)$r['kode_asset']));
            $feederId = (int)$r['penyulang_id'];
            $key = "{$feederId}:{$code}";
            $this->existingAssetCache[$key] = $r;
        }
    }

    /**
     * Ingest and reconcile CSV file in bulk mode.
     */
    public function ingestCsv(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException("CSV file does not exist or is not readable: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV file: {$filePath}");
        }

        // Read header
        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            fclose($handle);
            throw new \RuntimeException("Empty or invalid CSV file: {$filePath}");
        }

        $headerMap = $this->resolveHeaderMap($rawHeader);
        $this->validateHeaderRequirements($headerMap);

        $rowNumber = 1; // header is row 1
        $batchSeenKeys = [];
        $affectedFeederIds = [];

        $autoAccept = [];
        $autoReview = [];
        $quarantine = [];

        $breakdown = [
            'new'          => 0,
            'enrich'       => 0,
            'skip'         => 0,
            'conflict'     => 0,
            'collision'    => 0,
            'duplicate'    => 0,
            'invalid_gps'  => 0,
            'unresolved'   => 0,
        ];

        // Pass 1: Parse, clean, identify feeders to pre-warm asset cache
        $rawRows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            // Skip empty rows
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }

            $mapped = $this->mapRowToFields($row, $headerMap);
            $rawRows[] = [
                'row_num' => $rowNumber,
                'data'    => $mapped,
            ];

            // Detect feeder
            $feederName = strtoupper(trim($mapped['penyulang'] ?? ''));
            $feeder = $this->resolveFeeder($feederName);
            if ($feeder && !in_array((int)$feeder['id'], $affectedFeederIds, true)) {
                $affectedFeederIds[] = (int)$feeder['id'];
            }
        }
        fclose($handle);

        // Pre-warm existing assets for all affected feeders
        $this->loadExistingAssetsForFeeders($affectedFeederIds);

        // Pass 2: Reconcile and classify each row
        foreach ($rawRows as $item) {
            $rNum = $item['row_num'];
            $data = $item['data'];

            $result = $this->processRow($data, $rNum, $batchSeenKeys);

            switch ($result['tier']) {
                case 'AUTO_ACCEPT':
                    $autoAccept[] = $result;
                    if ($result['action'] === 'INSERT') {
                        $breakdown['new']++;
                    } elseif ($result['action'] === 'ENRICH') {
                        $breakdown['enrich']++;
                    } else {
                        $breakdown['skip']++;
                    }
                    break;

                case 'AUTO_REVIEW':
                    $autoReview[] = $result;
                    if ($result['reason_code'] === 'HOLD_IDENTITY_CONFLICT') {
                        $breakdown['conflict']++;
                    } elseif ($result['reason_code'] === 'HOLD_SPATIAL_COLLISION') {
                        $breakdown['collision']++;
                    } else {
                        $breakdown['unresolved']++;
                    }
                    break;

                case 'QUARANTINE':
                default:
                    $quarantine[] = $result;
                    if (str_contains($result['reason_code'], 'GPS') || str_contains($result['reason_code'], 'GEODETIC')) {
                        $breakdown['invalid_gps']++;
                    } elseif ($result['reason_code'] === 'DUPLICATE_SOURCE_KEY') {
                        $breakdown['duplicate']++;
                    } else {
                        $breakdown['unresolved']++;
                    }
                    break;
            }
        }

        return [
            'file_path'          => $filePath,
            'file_name'          => basename($filePath),
            'total_rows_scanned' => count($rawRows),
            'summary'            => [
                'auto_accept_count' => count($autoAccept),
                'auto_review_count' => count($autoReview),
                'quarantine_count'  => count($quarantine),
            ],
            'breakdown'          => $breakdown,
            'auto_accept'        => $autoAccept,
            'auto_review'        => $autoReview,
            'quarantine'         => $quarantine,
        ];
    }

    /**
     * Map CSV header indices to canonical fields.
     */
    protected function resolveHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $rawHeader) {
            $h = strtolower(trim((string)$rawHeader));
            $hClean = preg_replace('/[^a-z0-9]/', '', $h);

            if (str_contains($hClean, 'up3')) {
                $map['up3'] = $idx;
            } elseif (str_contains($hClean, 'ulp')) {
                $map['ulp'] = $idx;
            } elseif (str_contains($hClean, 'jenisasset') || str_contains($hClean, 'tipeasset')) {
                $map['jenis_asset'] = $idx;
            } elseif (str_contains($hClean, 'namaasset') || str_contains($hClean, 'kodeasset') || $hClean === 'nama') {
                $map['nama_asset'] = $idx;
            } elseif (str_contains($hClean, 'penyulang') || str_contains($hClean, 'feeder')) {
                $map['penyulang'] = $idx;
            } elseif (str_contains($hClean, 'konstruksi') || str_contains($hClean, 'construction')) {
                $map['konstruksi'] = $idx;
            } elseif (str_contains($hClean, 'conductor') || str_contains($hClean, 'konduktor') || str_contains($hClean, 'material')) {
                $map['conductor'] = $idx;
            } elseif (str_contains($hClean, 'kapasitas') || str_contains($hClean, 'panjang')) {
                $map['kapasitas'] = $idx;
            } elseif (str_contains($hClean, 'alamat') || str_contains($hClean, 'lokasi')) {
                $map['lokasi'] = $idx;
            } elseif (str_contains($hClean, 'latitude') || $hClean === 'lat') {
                $map['latitude'] = $idx;
            } elseif (str_contains($hClean, 'longitude') || $hClean === 'lng' || $hClean === 'lon') {
                $map['longitude'] = $idx;
            }
        }

        return $map;
    }

    protected function validateHeaderRequirements(array $headerMap): void
    {
        $required = ['nama_asset', 'penyulang', 'latitude', 'longitude'];
        $missing = [];
        foreach ($required as $r) {
            if (!isset($headerMap[$r])) {
                $missing[] = $r;
            }
        }
        if (!empty($missing)) {
            throw new \RuntimeException("Missing mandatory CSV columns: " . implode(', ', $missing));
        }
    }

    protected function mapRowToFields(array $row, array $headerMap): array
    {
        $mapped = [
            'up3'          => isset($headerMap['up3']) ? trim((string)($row[$headerMap['up3']] ?? '')) : '',
            'ulp'          => isset($headerMap['ulp']) ? trim((string)($row[$headerMap['ulp']] ?? '')) : '',
            'jenis_asset'  => isset($headerMap['jenis_asset']) ? trim((string)($row[$headerMap['jenis_asset']] ?? '')) : 'JTM',
            'nama_asset'   => isset($headerMap['nama_asset']) ? trim((string)($row[$headerMap['nama_asset']] ?? '')) : '',
            'penyulang'    => isset($headerMap['penyulang']) ? trim((string)($row[$headerMap['penyulang']] ?? '')) : '',
            'konstruksi'   => isset($headerMap['konstruksi']) ? trim((string)($row[$headerMap['konstruksi']] ?? '')) : '',
            'conductor'    => isset($headerMap['conductor']) ? trim((string)($row[$headerMap['conductor']] ?? '')) : '',
            'kapasitas'    => isset($headerMap['kapasitas']) ? trim((string)($row[$headerMap['kapasitas']] ?? '')) : '',
            'lokasi'       => isset($headerMap['lokasi']) ? trim((string)($row[$headerMap['lokasi']] ?? '')) : '',
            'latitude'     => isset($headerMap['latitude']) ? trim((string)($row[$headerMap['latitude']] ?? '')) : '',
            'longitude'    => isset($headerMap['longitude']) ? trim((string)($row[$headerMap['longitude']] ?? '')) : '',
        ];

        return $mapped;
    }

    /**
     * Process a single row and classify into AUTO_ACCEPT, AUTO_REVIEW, or QUARANTINE.
     */
    public function processRow(array $data, int $rowNumber, array &$batchSeenKeys): array
    {
        $rawAssetCode = strtoupper(trim($data['nama_asset'] ?? ''));
        $rawFeeder = strtoupper(trim($data['penyulang'] ?? ''));
        $rawLat = $data['latitude'] ?? '';
        $rawLng = $data['longitude'] ?? '';

        // 1. Mandatory Field Check
        if ($rawAssetCode === '' || $rawFeeder === '') {
            return [
                'row_number'  => $rowNumber,
                'tier'        => 'QUARANTINE',
                'action'      => 'REJECT',
                'reason_code' => 'MISSING_MANDATORY_FIELDS',
                'message'     => 'Asset code or feeder name is empty',
                'data'        => $data,
            ];
        }

        // 2. Geodetic Validation
        $geoValidation = $this->validateGeodetic($rawLat, $rawLng);
        if (!$geoValidation['valid']) {
            return [
                'row_number'  => $rowNumber,
                'tier'        => 'QUARANTINE',
                'action'      => 'REJECT',
                'reason_code' => $geoValidation['reason_code'],
                'message'     => $geoValidation['message'],
                'data'        => $data,
            ];
        }

        $lat = $geoValidation['latitude'];
        $lng = $geoValidation['longitude'];

        // 3. Batch Duplicate Key Check
        $batchKey = "{$rawFeeder}:{$rawAssetCode}";
        if (isset($batchSeenKeys[$batchKey])) {
            return [
                'row_number'  => $rowNumber,
                'tier'        => 'QUARANTINE',
                'action'      => 'REJECT',
                'reason_code' => 'DUPLICATE_SOURCE_KEY',
                'message'     => "Duplicate asset key '{$rawAssetCode}' in feeder '{$rawFeeder}' within same batch (first seen at row {$batchSeenKeys[$batchKey]})",
                'data'        => $data,
            ];
        }
        $batchSeenKeys[$batchKey] = $rowNumber;

        // 4. Feeder Hierarchy Resolution
        $feeder = $this->resolveFeeder($rawFeeder);
        if (!$feeder) {
            return [
                'row_number'  => $rowNumber,
                'tier'        => 'AUTO_REVIEW',
                'action'      => 'HOLD_UNKNOWN_FEEDER',
                'reason_code' => 'UNKNOWN_FEEDER',
                'message'     => "Feeder '{$rawFeeder}' could not be matched to master penyulang table",
                'data'        => $data,
            ];
        }

        $feederId = (int)$feeder['id'];
        $ulpId = (int)($feeder['ulp_id'] ?? 1);

        // 5. Construction Resolution
        $constructionResolution = $this->resolveConstruction($data['konstruksi'] ?? '');

        // 6. Canonical Asset Payload Preparation
        $canonicalPayload = [
            'kode_asset'           => $rawAssetCode,
            'nama_asset'           => $rawAssetCode,
            'jenis_asset'          => !empty($data['jenis_asset']) && $data['jenis_asset'] !== '0' ? $data['jenis_asset'] : 'JTM',
            'ulp_id'               => $ulpId,
            'penyulang_id'         => $feederId,
            'section_id'           => null,
            'construction_type_id' => $constructionResolution['construction_type_id'],
            'lokasi'               => !empty($data['lokasi']) && $data['lokasi'] !== '0' ? $data['lokasi'] : null,
            'latitude'             => number_format($lat, 7, '.', ''),
            'longitude'            => number_format($lng, 7, '.', ''),
            'status'               => 'NORMAL',
            'source_row'           => $rowNumber,
            'canonical_construction' => $constructionResolution['canonical_code'],
            'conductor'            => !empty($data['conductor']) && $data['conductor'] !== '0' ? $data['conductor'] : 'AAAC',
        ];

        // 7. Identity Confidence Evaluation against DB
        $dbKey = "{$feederId}:{$rawAssetCode}";
        $existingAsset = $this->existingAssetCache[$dbKey] ?? null;

        if (!$existingAsset) {
            // Check for spatial collision (different code at same coordinate)
            $collision = $this->findSpatialCollision($lat, $lng, $feederId);
            if ($collision) {
                return [
                    'row_number'    => $rowNumber,
                    'tier'          => 'AUTO_REVIEW',
                    'action'        => 'HOLD_SPATIAL_COLLISION',
                    'reason_code'   => 'HOLD_SPATIAL_COLLISION',
                    'message'       => "Coordinate collision with existing asset '{$collision['kode_asset']}' (ID: {$collision['id']}) within {$this->config->spatialDuplicateThresholdMeters}m",
                    'payload'       => $canonicalPayload,
                    'existing_asset'=> $collision,
                ];
            }

            // High Confidence NEW Asset -> AUTO_ACCEPT
            return [
                'row_number'       => $rowNumber,
                'tier'             => 'AUTO_ACCEPT',
                'action'           => 'INSERT',
                'confidence'       => 'HIGH',
                'reason_code'      => 'NEW_ASSET',
                'message'          => "New asset '{$rawAssetCode}' accepted for insertion",
                'payload'          => $canonicalPayload,
            ];
        }

        // Asset exists in DB -> Evaluate distance
        $exLat = (float)$existingAsset['latitude'];
        $exLng = (float)$existingAsset['longitude'];
        $distance = $this->haversineDistanceMeters($lat, $lng, $exLat, $exLng);

        if ($distance > $this->config->coordinateMatchToleranceMeters) {
            // Coordinate conflict (> 1.0m difference)
            return [
                'row_number'    => $rowNumber,
                'tier'          => 'AUTO_REVIEW',
                'action'        => 'HOLD_IDENTITY_CONFLICT',
                'reason_code'   => 'HOLD_IDENTITY_CONFLICT',
                'message'       => "Asset '{$rawAssetCode}' exists in DB but coordinates differ by {$distance}m (tolerance: {$this->config->coordinateMatchToleranceMeters}m)",
                'payload'       => $canonicalPayload,
                'existing_asset'=> $existingAsset,
                'delta_meters'  => $distance,
            ];
        }

        // Coordinates match (<= 1.0m) -> Check if enrichment needed
        $needsEnrich = false;
        $enrichFields = [];

        if (empty($existingAsset['construction_type_id']) && !empty($canonicalPayload['construction_type_id'])) {
            $needsEnrich = true;
            $enrichFields['construction_type_id'] = $canonicalPayload['construction_type_id'];
        }
        if (empty($existingAsset['lokasi']) && !empty($canonicalPayload['lokasi'])) {
            $needsEnrich = true;
            $enrichFields['lokasi'] = $canonicalPayload['lokasi'];
        }

        if ($needsEnrich) {
            $canonicalPayload['id'] = (int)$existingAsset['id'];
            $canonicalPayload['enrich_fields'] = $enrichFields;
            return [
                'row_number'    => $rowNumber,
                'tier'          => 'AUTO_ACCEPT',
                'action'        => 'ENRICH',
                'confidence'    => 'HIGH',
                'reason_code'   => 'ENRICH_EXISTING_ASSET',
                'message'       => "Asset '{$rawAssetCode}' matched existing ID {$existingAsset['id']}, enriched metadata",
                'payload'       => $canonicalPayload,
                'existing_asset'=> $existingAsset,
            ];
        }

        // Completely identical -> SKIP
        return [
            'row_number'    => $rowNumber,
            'tier'          => 'AUTO_ACCEPT',
            'action'        => 'SKIP',
            'confidence'    => 'HIGH',
            'reason_code'   => 'IDENTICAL_ASSET',
            'message'       => "Asset '{$rawAssetCode}' is identical to existing DB record ID {$existingAsset['id']}",
            'payload'       => $canonicalPayload,
            'existing_asset'=> $existingAsset,
        ];
    }

    /**
     * Validate geodetic coordinates against bounding box policy.
     */
    public function validateGeodetic($rawLat, $rawLng, ?string $policy = null): array
    {
        if ($rawLat === null || $rawLat === '' || $rawLng === null || $rawLng === '') {
            return [
                'valid'       => false,
                'reason_code' => 'INVALID_GEODETIC_COORDINATES',
                'message'     => 'Empty or null coordinate values',
            ];
        }

        if (!is_numeric($rawLat) || !is_numeric($rawLng)) {
            return [
                'valid'       => false,
                'reason_code' => 'INVALID_GEODETIC_COORDINATES',
                'message'     => "Non-numeric coordinates: lat='{$rawLat}', lng='{$rawLng}'",
            ];
        }

        $lat = (float)$rawLat;
        $lng = (float)$rawLng;

        // Zero-coordinate check
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            return [
                'valid'       => false,
                'reason_code' => 'INVALID_GEODETIC_COORDINATES',
                'message'     => 'Zero or near-zero coordinates (0, 0)',
            ];
        }

        $policyKey = $policy ?? $this->config->activeGeodeticPolicy;
        $bbox = $this->config->geodeticPolicies[$policyKey] ?? $this->config->geodeticPolicies['EAST_JAVA'];

        if ($lat < $bbox['min_lat'] || $lat > $bbox['max_lat'] ||
            $lng < $bbox['min_lng'] || $lng > $bbox['max_lng']) {
            return [
                'valid'       => false,
                'reason_code' => 'GEODETIC_OUT_OF_BOUNDS',
                'message'     => sprintf(
                    "Coordinates (%.6f, %.6f) outside bounding box %s [%.4f to %.4f, %.4f to %.4f]",
                    $lat, $lng, $policyKey, $bbox['min_lat'], $bbox['max_lat'], $bbox['min_lng'], $bbox['max_lng']
                ),
            ];
        }

        return [
            'valid'     => true,
            'latitude'  => $lat,
            'longitude' => $lng,
        ];
    }

    /**
     * Resolve Feeder master record.
     */
    public function resolveFeeder(string $rawFeeder): ?array
    {
        $normalized = strtoupper(trim($rawFeeder));
        if ($normalized === '') return null;

        if (isset($this->feederCache[$normalized])) {
            return $this->feederCache[$normalized];
        }

        $noSpace = str_replace(' ', '', $normalized);
        if (isset($this->feederCache[$noSpace])) {
            return $this->feederCache[$noSpace];
        }

        return null;
    }

    /**
     * Resolve construction code to canonical alias and DB ID.
     */
    public function resolveConstruction(string $rawConstruction): array
    {
        $cleaned = strtoupper(trim($rawConstruction));
        if ($cleaned === '' || $cleaned === '0') {
            return [
                'canonical_code'       => null,
                'construction_type_id' => null,
            ];
        }

        $canonical = $this->config->constructionAliases[$cleaned] ?? $cleaned;
        $cleanedCanonical = str_replace(['-', ' ', '_'], '', $canonical);

        $typeId = $this->constructionCache[$canonical] ?? ($this->constructionCache[$cleanedCanonical] ?? null);

        return [
            'canonical_code'       => $canonical,
            'construction_type_id' => $typeId,
        ];
    }

    /**
     * Find spatial collision with existing assets on the same feeder.
     */
    protected function findSpatialCollision(float $lat, float $lng, int $feederId): ?array
    {
        $threshold = $this->config->spatialDuplicateThresholdMeters;
        foreach ($this->existingAssetCache as $existing) {
            if ((int)$existing['penyulang_id'] !== $feederId) {
                continue;
            }
            $exLat = (float)$existing['latitude'];
            $exLng = (float)$existing['longitude'];
            $dist = $this->haversineDistanceMeters($lat, $lng, $exLat, $exLng);
            if ($dist <= $threshold) {
                return $existing;
            }
        }
        return null;
    }

    /**
     * Calculate Haversine distance in meters.
     */
    public function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (abs($lat1) < 0.00001 && abs($lon1) < 0.00001) return 0.0;
        if (abs($lat2) < 0.00001 && abs($lon2) < 0.00001) return 0.0;

        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) ** 2;

        return round(2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
}
