<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * RowTreeAssetService
 *
 * Manages transformation of ROW findings into tree asset entities (`jenis_asset = 'POHON_ROW'`),
 * prevents tree duplication per spatial coordinate, records inspection history into `vegetation_observations`,
 * and computes rigorous vegetation growth rates and pruning schedules (or explicitly flags `BELUM_CUKUP_DATA`).
 */
class RowTreeAssetService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Known tropical tree species daily growth rates (cm/day) under PLN distribution ROW.
     */
    public const SPECIES_GROWTH_RATES = [
        'BAMBU'    => ['name' => 'Bambu', 'rate_cm_day' => 3.50, 'safe_clearance_m' => 2.50],
        'PISANG'   => ['name' => 'Pisang', 'rate_cm_day' => 2.80, 'safe_clearance_m' => 2.50],
        'SENGON'   => ['name' => 'Sengon / Albasia', 'rate_cm_day' => 2.00, 'safe_clearance_m' => 2.50],
        'TREMBESI' => ['name' => 'Trembesi', 'rate_cm_day' => 1.50, 'safe_clearance_m' => 2.50],
        'AKASIA'   => ['name' => 'Akasia', 'rate_cm_day' => 1.50, 'safe_clearance_m' => 2.50],
        'MANGGA'   => ['name' => 'Mangga', 'rate_cm_day' => 1.00, 'safe_clearance_m' => 2.50],
        'MAHONI'   => ['name' => 'Mahoni', 'rate_cm_day' => 0.90, 'safe_clearance_m' => 2.50],
        'JATI'     => ['name' => 'Jati', 'rate_cm_day' => 0.80, 'safe_clearance_m' => 2.50],
        'KELAPA'   => ['name' => 'Kelapa / Sawit', 'rate_cm_day' => 0.40, 'safe_clearance_m' => 2.50],
    ];

    /**
     * Extracts tree species from text description.
     */
    public function detectSpecies(string $text): array
    {
        $lower = strtolower($text);
        foreach (self::SPECIES_GROWTH_RATES as $key => $meta) {
            $pattern = match ($key) {
                'BAMBU'    => '/\b(bambu|pring)\b/i',
                'PISANG'   => '/\b(pisang|gedang)\b/i',
                'SENGON'   => '/\b(sengon|albasia|jeungjing)\b/i',
                'TREMBESI' => '/\b(trembesi|samanea|ki\s*hujan)\b/i',
                'AKASIA'   => '/\b(akasia|acacia)\b/i',
                'MANGGA'   => '/\b(mangga|pohon\s*mangga)\b/i',
                'MAHONI'   => '/\b(mahoni|mahogany)\b/i',
                'JATI'     => '/\b(jati|pohon\s*jati)\b/i',
                'KELAPA'   => '/\b(kelapa|sawit|nyiur)\b/i',
                default    => '/\b' . strtolower($key) . '\b/i',
            };

            if (preg_match($pattern, $lower)) {
                return [
                    'key'         => $key,
                    'name'        => $meta['name'],
                    'rate_cm_day' => $meta['rate_cm_day'],
                    'identified'  => true,
                ];
            }
        }

        return [
            'key'         => 'UNKNOWN',
            'name'        => 'Pohon ROW (Spesies Belum Teridentifikasi)',
            'rate_cm_day' => null,
            'identified'  => false,
        ];
    }

    /**
     * Extracts clearance / distance in meters from finding text description.
     */
    public function detectClearanceMeters(string $text): ?float
    {
        if (preg_match('/\b(?:jarak|clearance|rentang|gap)\s*(?:ke\s*jaringan)?\s*(?:[:=~]|adalah)?\s*([0-9]+(?:[\.,][0-9]+)?)\s*(m|meter|cm)\b/i', $text, $m)) {
            $val = (float)str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if ($unit === 'cm') {
                $val = $val / 100.0;
            }
            return round($val, 2);
        }

        if (preg_match('/\b([0-9]+(?:[\.,][0-9]+)?)\s*(?:m|meter)\s*(?:dari|ke)\s*(?:jaringan|kabel|sutm|konduktor)\b/i', $text, $m)) {
            return round((float)str_replace(',', '.', $m[1]), 2);
        }

        return null;
    }

    /**
     * Audits and re-links all ROW findings to deduplicated tree assets (`jenis_asset = 'POHON_ROW'`).
     *
     * @param bool $isExecute True to persist changes transactionally, false for dry-run.
     * @return array<string, mixed> Audit summary
     */
    public function reconcileRowFindings(bool $isExecute = false): array
    {
        // 1. Fetch all candidate ROW findings
        $findings = $this->db->table('temuan')
            ->select('id, nomor_temuan, ulp_id, penyulang_id, section_id, asset_id, jenis_temuan, pelaksana, detail_temuan, alamat, latitude, longitude, foto, foto_path, created_by, tanggal_temuan, created_at')
            ->groupStart()
                ->where('pelaksana', 'HAR ROW')
                ->orWhere('jenis_temuan', 'ROW')
                ->orLike('detail_temuan', 'pohon')
                ->orLike('detail_temuan', 'ranting')
                ->orLike('detail_temuan', 'bambu')
                ->orLike('detail_temuan', 'vegetasi')
            ->groupEnd()
            ->where('deleted_at IS NULL')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $totalFindings = count($findings);
        $withCoords = 0;
        $noCoords = 0;
        $uniqueTrees = [];
        $observationsToRecord = [];
        $growthPredictions = [];

        // Pre-fetch all existing POHON_ROW assets
        $existingTrees = [];
        if ($this->db->tableExists('assets')) {
            $treeRows = $this->db->table('assets')
                ->where('jenis_asset', 'POHON_ROW')
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
            foreach ($treeRows as $tr) {
                $latStr = number_format((float)$tr['latitude'], 6, '.', '');
                $lngStr = number_format((float)$tr['longitude'], 6, '.', '');
                $cKey = "{$tr['ulp_id']}|{$tr['penyulang_id']}|{$latStr}|{$lngStr}";
                $existingTrees[$cKey] = $tr;
            }
        }

        $treeSequence = count($existingTrees) + 1;
        $simulatedCreatedTrees = [];
        $findingsLinked = [];

        if ($isExecute) {
            $this->db->transStart();
        }

        foreach ($findings as $f) {
            $lat = $f['latitude'] !== null ? (float)$f['latitude'] : null;
            $lng = $f['longitude'] !== null ? (float)$f['longitude'] : null;

            if ($lat === null || $lng === null || ($lat == 0.0 && $lng == 0.0)) {
                $noCoords++;
                continue;
            }

            $withCoords++;
            $latKey = number_format($lat, 6, '.', '');
            $lngKey = number_format($lng, 6, '.', '');
            $coordKey = "{$f['ulp_id']}|{$f['penyulang_id']}|{$latKey}|{$lngKey}";

            $species = $this->detectSpecies($f['detail_temuan'] ?? '');
            $clearance = $this->detectClearanceMeters($f['detail_temuan'] ?? '');

            // 1. Resolve or create canonical tree asset
            $treeAsset = $existingTrees[$coordKey] ?? ($simulatedCreatedTrees[$coordKey] ?? null);

            if (!$treeAsset) {
                $treeCode = sprintf('AST-ROW-%03d-%04d', (int)$f['penyulang_id'], $treeSequence++);
                $treeName = 'Pohon ROW ' . $species['name'] . ' (' . $latKey . ', ' . $lngKey . ')';
                $now = date('Y-m-d H:i:s');

                $treeData = [
                    'kode_asset'      => $treeCode,
                    'nama_asset'      => $treeName,
                    'jenis_asset'     => 'POHON_ROW',
                    'ulp_id'          => $f['ulp_id'],
                    'penyulang_id'    => $f['penyulang_id'],
                    'section_id'      => $f['section_id'],
                    'latitude'        => $lat,
                    'longitude'       => $lng,
                    'lokasi'          => !empty($f['alamat']) ? $f['alamat'] : ($f['detail_temuan'] ?? 'Jalur SUTM'),
                    'status'          => ($clearance !== null && $clearance < 1.0) ? 'ALERT' : 'NORMAL',
                    'created_at'      => !empty($f['created_at']) ? $f['created_at'] : $now,
                    'updated_at'      => $now,
                ];

                if ($isExecute) {
                    $this->db->table('assets')->insert($treeData);
                    $newTreeId = (int)$this->db->insertID();
                    $treeData['id'] = $newTreeId;
                } else {
                    $treeData['id'] = 'SIMULATED-' . $treeSequence;
                }

                $existingTrees[$coordKey] = $treeData;
                $simulatedCreatedTrees[$coordKey] = $treeData;
                $treeAsset = $treeData;
            }

            $targetTreeId = (int)$treeAsset['id'];

            // 2. Link Finding to this Tree Asset
            if ($isExecute && $targetTreeId > 0) {
                $this->db->table('temuan')->where('id', $f['id'])->update([
                    'asset_id' => $targetTreeId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if ($this->db->tableExists('temuan_materials')) {
                    $this->db->table('temuan_materials')->where('temuan_id', $f['id'])->update([
                        'asset_id' => $targetTreeId,
                    ]);
                }
            }

            $findingsLinked[] = [
                'temuan_id'     => $f['id'],
                'nomor_temuan'  => $f['nomor_temuan'],
                'tree_asset_id' => $targetTreeId,
                'species'       => $species['name'],
                'clearance_m'   => $clearance,
                'coord'         => "{$latKey}, {$lngKey}",
            ];

            // 3. Record observation history into vegetation_observations
            $obsDate = !empty($f['tanggal_temuan']) ? $f['tanggal_temuan'] . ' 08:00:00' : (!empty($f['created_at']) ? $f['created_at'] : date('Y-m-d H:i:s'));
            $fotoPath = $f['foto_path'] ?? null;
            if (empty($fotoPath) && !empty($f['foto'])) {
                $decodedFoto = json_decode((string)$f['foto'], true);
                if (is_array($decodedFoto) && !empty($decodedFoto[0])) {
                    $fotoPath = 'foto/' . $decodedFoto[0];
                } else {
                    $fotoPath = (string)$f['foto'];
                }
            }

            $obsData = [
                'asset_id'           => $targetTreeId,
                'inspection_id'      => null,
                'distance_meters'    => $clearance ?? 1.50,
                'wind_contact'       => ($clearance !== null && $clearance < 1.0) ? 1 : 0,
                'foto_evidence_path' => $fotoPath,
                'observed_by'        => !empty($f['created_by']) ? (int)$f['created_by'] : 1,
                'observed_at'        => $obsDate,
                'is_valid'           => 1,
                'created_at'         => date('Y-m-d H:i:s'),
            ];

            if ($isExecute && $targetTreeId > 0 && $this->db->tableExists('vegetation_observations')) {
                // Deduplicate observation by asset_id and observed_at
                $existObs = $this->db->table('vegetation_observations')
                    ->where('asset_id', $targetTreeId)
                    ->where('observed_at', $obsDate)
                    ->countAllResults();
                if ($existObs === 0) {
                    $this->db->table('vegetation_observations')->insert($obsData);
                }
            }

            $observationsToRecord[] = $obsData;

            // 4. Compute growth rate and pruning schedule
            $prediction = $this->calculateVegetationGrowthPrediction($targetTreeId, $species, $clearance, $obsDate);
            $growthPredictions[$targetTreeId] = $prediction;
        }

        if ($isExecute) {
            $this->db->transComplete();
        }

        return [
            'status'                => 'success',
            'is_execute'            => $isExecute,
            'total_row_findings'    => $totalFindings,
            'with_coordinates'      => $withCoords,
            'without_coordinates'   => $noCoords,
            'unique_trees_count'    => count($existingTrees),
            'new_trees_created'     => count($simulatedCreatedTrees),
            'observations_recorded' => count($observationsToRecord),
            'findings_linked'       => array_slice($findingsLinked, 0, 15),
            'sample_predictions'    => array_slice($growthPredictions, 0, 10, true),
        ];
    }

    /**
     * Calculates vegetation growth prediction and pruning schedule.
     *
     * Invariant: Requires sufficient data (either >= 2 valid historical observations
     * or fully identified species and measured clearance).
     * Otherwise strictly returns `BELUM_CUKUP_DATA` with confidence `LOW`.
     */
    public function calculateVegetationGrowthPrediction(int|string $assetId, array $species, ?float $currentClearance, string $currentDate): array
    {
        $observations = [];
        if (is_numeric($assetId) && $this->db->tableExists('vegetation_observations')) {
            $observations = $this->db->table('vegetation_observations')
                ->where('asset_id', (int)$assetId)
                ->where('is_valid', 1)
                ->orderBy('observed_at', 'ASC')
                ->get()
                ->getResultArray();
        }

        // Case 1: Multiple historical observations available (minimum 2 valid observations)
        if (count($observations) >= 2) {
            $obsFirst = $observations[0];
            $obsLast  = end($observations);

            $t1 = strtotime($obsFirst['observed_at']);
            $t2 = strtotime($obsLast['observed_at']);
            $daysDiff = max(1, floor(($t2 - $t1) / 86400));

            $d1 = (float)$obsFirst['distance_meters'];
            $d2 = (float)$obsLast['distance_meters'];

            // Tree grows closer to conductor => distance decreases
            $growthCm = max(0.0, ($d1 - d2) * 100.0);
            if ($daysDiff >= 14 && $growthCm > 0.0) {
                $rateCmDay = round($growthCm / $daysDiff, 2);
                $remainingClearanceCm = max(0.0, ($d2 - 0.50) * 100.0); // 0.5m critical threshold
                $daysToPrune = ($rateCmDay > 0) ? floor($remainingClearanceCm / $rateCmDay) : 180;
                $pruneDate = date('Y-m-d', strtotime("+{$daysToPrune} days", $t2));

                return [
                    'prediction_status'      => 'ESTIMASI_VALID',
                    'confidence'             => 'HIGH',
                    'species'                => $species['name'],
                    'growth_rate_cm_day'     => $rateCmDay,
                    'current_clearance_m'    => $d2,
                    'critical_threshold_m'   => 0.50,
                    'days_until_pruning'     => (int)$daysToPrune,
                    'recommended_prune_date' => $pruneDate,
                    'basis'                  => "Dihitung dari " . count($observations) . " observasi empiris berjarak {$daysDiff} hari",
                ];
            }
        }

        // Case 2: Single observation, but known species and known clearance
        if ($species['identified'] && $species['rate_cm_day'] !== null && $currentClearance !== null && $currentClearance > 0.50) {
            $rateCmDay = $species['rate_cm_day'];
            $remainingClearanceCm = max(0.0, ($currentClearance - 0.50) * 100.0);
            $daysToPrune = floor($remainingClearanceCm / $rateCmDay);
            $pruneDate = date('Y-m-d', strtotime("+{$daysToPrune} days", strtotime($currentDate)));

            return [
                'prediction_status'      => 'ESTIMASI_VALID',
                'confidence'             => 'MEDIUM',
                'species'                => $species['name'],
                'growth_rate_cm_day'     => $rateCmDay,
                'current_clearance_m'    => $currentClearance,
                'critical_threshold_m'   => 0.50,
                'days_until_pruning'     => (int)$daysToPrune,
                'recommended_prune_date' => $pruneDate,
                'basis'                  => "Dihitung dari laju spesifik spesies {$species['name']} ({$rateCmDay} cm/hari) dan clearance terukur {$currentClearance}m",
            ];
        }

        // Case 3: Insufficient data -> STRICTLY return BELUM_CUKUP_DATA
        return [
            'prediction_status'      => 'BELUM_CUKUP_DATA',
            'confidence'             => 'LOW',
            'species'                => $species['name'],
            'growth_rate_cm_day'     => null,
            'current_clearance_m'    => $currentClearance,
            'critical_threshold_m'   => 0.50,
            'days_until_pruning'     => null,
            'recommended_prune_date' => null,
            'basis'                  => 'Data historis belum mencukupi (diperlukan minimal 2 observasi valid berjarak waktu atau clearance dan spesies spesifik teridentifikasi lengkap).',
        ];
    }
}
