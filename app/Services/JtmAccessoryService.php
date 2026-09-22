<?php

namespace App\Services;

use App\Models\MasterJtmAccessoryModel;
use App\Models\TemuanAccessoryModel;
use CodeIgniter\Database\BaseConnection;

/**
 * CR-HOTFIX-02: JTM & Conductor Accessories Service
 *
 * Implements:
 * - Master JTM Accessories catalog retrieval
 * - Authoritative Finding-to-Asset Accessory persistence
 * - Strict Snapshot Invariant (accessory_name_snapshot)
 * - Strict Duplicate Protection (unique per temuan + accessory_type_id)
 * - Strict Asset Relation Verification (temuan.asset_id == accessory.asset_id)
 * - Zero mutation to MR-01 material tables, translines, or topology
 */
class JtmAccessoryService
{
    protected BaseConnection $db;
    protected MasterJtmAccessoryModel $masterModel;
    protected TemuanAccessoryModel $accessoryModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->masterModel = new MasterJtmAccessoryModel();
        $this->accessoryModel = new TemuanAccessoryModel();
    }

    /**
     * Retrieve all active master accessories.
     */
    public function getActiveAccessories(): array
    {
        if (!$this->db->tableExists('master_jtm_accessories')) {
            // Fallback canonical catalog if table is not yet created
            return [
                ['id' => 1,  'code' => 'GSW',                'name' => 'GSW',                   'category' => 'CONDUCTOR_GROUNDING', 'sub_category' => 'CONDUCTOR_GROUNDING', 'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => null,                 'sort_order' => 1],
                ['id' => 2,  'code' => 'GROUND_GSW',         'name' => 'GROUND GSW',            'category' => 'CONDUCTOR_GROUNDING', 'sub_category' => 'CONDUCTOR_GROUNDING', 'phase_applicable' => 0, 'phase_mode' => 'NONE',        'default_qty' => 1, 'unit' => 'buah', 'canonical_material_code' => null,                 'sort_order' => 2],
                ['id' => 3,  'code' => 'PENGHALANG_BINATANG', 'name' => 'PENGHALANG BINATANG',   'category' => 'CONDUCTOR_GROUNDING', 'sub_category' => 'CONDUCTOR_GROUNDING', 'phase_applicable' => 0, 'phase_mode' => 'NONE',        'default_qty' => 1, 'unit' => 'buah', 'canonical_material_code' => null,                 'sort_order' => 3],
                ['id' => 4,  'code' => 'EGLA',               'name' => 'EGLA',                  'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => null,                 'sort_order' => 4],
                ['id' => 5,  'code' => 'CLD',                'name' => 'CLD',                   'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => null,                 'sort_order' => 5],
                ['id' => 6,  'code' => 'MCA',                'name' => 'MCA',                   'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => null,                 'sort_order' => 6],
                ['id' => 7,  'code' => 'ARRESTER',           'name' => 'Arrester Jaringan',     'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-PROT-LA-24KV',  'sort_order' => 7],
                ['id' => 8,  'code' => 'FIOHL',              'name' => 'FIOHL',                 'category' => 'MONITORING',          'sub_category' => 'MONITORING',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-IND-FIOHL',     'sort_order' => 8],
                ['id' => 9,  'code' => 'FCO',                'name' => 'FCO',                   'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-PROT-FCO-24KV', 'sort_order' => 9],
                ['id' => 10, 'code' => 'FCO_BRANCH',         'name' => 'FCO Branch / Lateral',  'category' => 'PROTECTION',          'sub_category' => 'PROTECTION',          'phase_applicable' => 1, 'phase_mode' => 'MULTI_PHASE', 'default_qty' => 3, 'unit' => 'buah', 'canonical_material_code' => 'MAT-PROT-FCO-LAT',  'sort_order' => 10],
            ];
        }

        return $this->masterModel->getActive();
    }

    /**
     * Persist accessory observations for a specific temuan and asset.
     *
     * @param int $temuanId
     * @param int $assetId
     * @param array $accessoriesPayload Array of accessory items
     * @param int|null $userId
     * @return array
     */
    public function persistAccessories(int $temuanId, int $assetId, array $accessoriesPayload, ?int $userId = null): array
    {
        if ($temuanId <= 0 || $assetId <= 0) {
            return [
                'status'  => 'INVALID_INPUT',
                'message' => 'Temuan ID dan Asset ID harus valid.',
                'saved'   => 0,
            ];
        }

        if (empty($accessoriesPayload)) {
            return [
                'status'  => 'SUCCESS',
                'message' => 'Tidak ada aksesoris yang perlu disimpan.',
                'saved'   => 0,
            ];
        }

        // 1. Verify that temuan exists and its asset_id matches the provided assetId
        if ($this->db->tableExists('temuan')) {
            $temuanRow = $this->db->table('temuan')->select('id, asset_id')->where('id', $temuanId)->get()->getRowArray();
            if (!$temuanRow) {
                return [
                    'status'  => 'NOT_FOUND',
                    'message' => 'Temuan tidak ditemukan.',
                    'saved'   => 0,
                ];
            }
            if (!empty($temuanRow['asset_id']) && (int)$temuanRow['asset_id'] !== $assetId) {
                return [
                    'status'  => 'RELATION_MISMATCH',
                    'message' => 'Asset ID aksesoris tidak sesuai dengan Asset ID pada Temuan.',
                    'saved'   => 0,
                ];
            }
        }

        // 2. Fetch active master accessories for snapshot verification
        $masterList = $this->getActiveAccessories();
        $masterById = [];
        foreach ($masterList as $m) {
            $masterById[(int)$m['id']] = $m;
        }

        $savedCount = 0;
        $processedTypeIds = [];
        $now = date('Y-m-d H:i:s');
        $tableFields = null;

        $this->db->transStart();

        foreach ($accessoriesPayload as $item) {
            $typeId = (int)($item['accessory_type_id'] ?? 0);
            if ($typeId <= 0 || !isset($masterById[$typeId])) {
                continue; // Ignore unknown or invalid accessory types
            }

            // DUPLICATE PROTECTION: Only process each accessory_type_id once per temuan
            if (in_array($typeId, $processedTypeIds, true)) {
                continue;
            }
            $processedTypeIds[] = $typeId;

            $master = $masterById[$typeId];
            $status = strtoupper(trim((string)($item['status'] ?? 'ADA')));
            if (!in_array($status, ['ADA', 'TIDAK_ADA', 'TIDAK ADA'])) {
                $status = 'ADA';
            }
            if ($status === 'TIDAK ADA') {
                $status = 'TIDAK_ADA';
            }

            $condition = strtoupper(trim((string)($item['condition'] ?? 'BAIK')));
            if (!in_array($condition, ['BAIK', 'RUSAK', 'PERLU_PENGGANTIAN', 'PERLU PENGGANTIAN'])) {
                $condition = 'BAIK';
            }
            if ($condition === 'PERLU PENGGANTIAN') {
                $condition = 'PERLU_PENGGANTIAN';
            }

            $note = isset($item['note']) ? trim((string)$item['note']) : null;
            $photoUrl = isset($item['photo_url']) ? trim((string)$item['photo_url']) : null;

            // Phase and Quantity Context
            $phaseApplicable = !empty($master['phase_applicable']) ? 1 : 0;
            $qty = isset($item['qty']) && is_numeric($item['qty']) ? (int)$item['qty'] : ($status === 'ADA' ? (int)($master['default_qty'] ?? 1) : 0);
            $unit = (string)($item['unit'] ?? ($master['unit'] ?? 'buah'));

            $phaseConfig = 'NON_PHASE';
            $phasePositionsStr = null;
            if ($phaseApplicable && $status === 'ADA') {
                $rawConfig = strtoupper(trim((string)($item['phase_configuration'] ?? '3_PHASE')));
                if (in_array($rawConfig, ['3_PHASE', '2_PHASE', '1_PHASE', 'NON_PHASE'])) {
                    $phaseConfig = $rawConfig;
                } else {
                    $phaseConfig = '3_PHASE';
                }

                $rawPositions = $item['phase_positions'] ?? null;
                if (is_array($rawPositions)) {
                    $cleanPos = array_values(array_intersect(array_map('strtoupper', array_map('trim', $rawPositions)), ['R', 'S', 'T']));
                    $phasePositionsStr = !empty($cleanPos) ? implode(',', $cleanPos) : null;
                } elseif (is_string($rawPositions) && trim($rawPositions) !== '') {
                    $parts = array_map('trim', explode(',', strtoupper($rawPositions)));
                    $cleanPos = array_values(array_intersect($parts, ['R', 'S', 'T']));
                    $phasePositionsStr = !empty($cleanPos) ? implode(',', $cleanPos) : null;
                }
            }

            // Snapshot fields
            $snapshotData = [
                'asset_id'                => $assetId,
                'accessory_code'          => $master['code'],
                'accessory_name_snapshot' => $master['name'],
                'category_snapshot'       => $master['category'] ?? 'CONDUCTOR_GROUNDING',
                'status'                  => $status,
                'qty'                     => $qty,
                'unit'                    => $unit,
                'phase_applicable'        => $phaseApplicable,
                'phase_configuration'     => $phaseConfig,
                'phase_positions'         => $phasePositionsStr,
                'condition'               => $condition,
                'note'                    => $note,
                'updated_at'              => $now,
            ];

            // Resilience check: filter to columns present in table to tolerate partial migrations
            if ($tableFields === null && $this->db->tableExists('temuan_accessories')) {
                $tableFields = array_flip($this->db->getFieldNames('temuan_accessories'));
            }
            if ($tableFields !== null) {
                $snapshotData = array_intersect_key($snapshotData, $tableFields);
            }

            // Check if existing record already exists for this (temuan_id, accessory_type_id)
            $existing = $this->db->table('temuan_accessories')
                ->where('temuan_id', $temuanId)
                ->where('accessory_type_id', $typeId)
                ->get()
                ->getRowArray();

            if ($existing) {
                // Update existing record with full snapshot
                $snapshotData['photo_url'] = $photoUrl ?: $existing['photo_url'];
                if ($tableFields !== null) {
                    $snapshotData = array_intersect_key($snapshotData, $tableFields);
                }
                $this->db->table('temuan_accessories')
                    ->where('id', $existing['id'])
                    ->update($snapshotData);
                $savedCount++;
            } else {
                // Insert new record with full snapshot
                $snapshotData['temuan_id']         = $temuanId;
                $snapshotData['accessory_type_id'] = $typeId;
                $snapshotData['photo_url']         = $photoUrl;
                $snapshotData['created_at']        = $now;
                if ($tableFields !== null) {
                    $snapshotData = array_intersect_key($snapshotData, $tableFields);
                }
                $this->db->table('temuan_accessories')->insert($snapshotData);
                $savedCount++;
            }
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            $dbErr = $this->db->error();
            $errMsg = !empty($dbErr['message']) ? $dbErr['message'] : 'Gagal menyimpan data aksesoris JTM.';
            return [
                'status'  => 'ERROR',
                'message' => $errMsg,
                'saved'   => 0,
            ];
        }

        return [
            'status'  => 'SUCCESS',
            'message' => "Berhasil menyimpan {$savedCount} aksesoris JTM.",
            'saved'   => $savedCount,
        ];
    }

    /**
     * Helper to normalize CSV positions to array: "R,S,T" -> ["R", "S", "T"]
     */
    public function normalizePositions($positions): array
    {
        if (is_array($positions)) {
            return array_values(array_intersect(array_map('strtoupper', array_map('trim', $positions)), ['R', 'S', 'T']));
        }
        if (is_string($positions) && trim($positions) !== '') {
            $parts = array_map('trim', explode(',', strtoupper($positions)));
            return array_values(array_intersect($parts, ['R', 'S', 'T']));
        }
        return [];
    }

    /**
     * Format a single row into a structured semantic object for AI, GIS, and UI
     */
    public function formatSemanticObject(array $row): array
    {
        $positions = $this->normalizePositions($row['phase_positions'] ?? null);
        $phaseConfig = $row['phase_configuration'] ?? null;
        $phaseApplicable = !empty($row['phase_applicable']);

        return [
            'id'             => (int)($row['id'] ?? 0),
            'temuan_id'      => (int)($row['temuan_id'] ?? 0),
            'asset_id'       => (int)($row['asset_id'] ?? 0),
            'accessory_id'   => (int)($row['accessory_type_id'] ?? 0),
            'accessory_code' => (string)($row['accessory_code'] ?? ''),
            'accessory_name' => (string)($row['accessory_name_snapshot'] ?? ''),
            'category'       => (string)($row['category_snapshot'] ?? ($row['accessory_category'] ?? 'CONDUCTOR_GROUNDING')),
            'sub_category'   => (string)($row['accessory_sub_category'] ?? ($row['category_snapshot'] ?? 'CONDUCTOR_GROUNDING')),
            'configuration'  => [
                'qty'                 => (int)($row['qty'] ?? 1),
                'unit'                => (string)($row['unit'] ?? 'buah'),
                'phase_applicable'    => $phaseApplicable,
                'phase_configuration' => $phaseConfig,
                'positions'           => $positions,
                'display_phase'       => $phaseApplicable && $phaseConfig ? str_replace('_', ' ', $phaseConfig) . (!empty($positions) ? ' (' . implode('-', $positions) . ')' : '') : null,
            ],
            'observation'    => [
                'status'    => (string)($row['status'] ?? 'ADA'),
                'condition' => (string)($row['condition'] ?? 'BAIK'),
                'note'      => (string)($row['note'] ?? ''),
                'photo_url' => $row['photo_url'] ?? null,
            ],
            'created_at'     => $row['created_at'] ?? null,
            'updated_at'     => $row['updated_at'] ?? null,
        ];
    }

    /**
     * Get all accessories for a finding (with normalized semantic objects)
     */
    public function getAccessoriesForTemuan(int $temuanId): array
    {
        if ($temuanId <= 0 || !$this->db->tableExists('temuan_accessories')) {
            return [];
        }

        $rawRows = $this->accessoryModel->getByTemuanId($temuanId);
        $result = [];
        foreach ($rawRows as $row) {
            $formatted = $this->formatSemanticObject($row);
            // Also merge raw keys for backward compatibility in existing views
            $merged = array_merge($row, $formatted);
            $merged['positions'] = $formatted['configuration']['positions'];
            $merged['display_phase'] = $formatted['configuration']['display_phase'];
            $result[] = $merged;
        }

        return $result;
    }

    /**
     * Get all accessories for an asset across findings
     */
    public function getAccessoriesForAsset(int $assetId): array
    {
        if ($assetId <= 0 || !$this->db->tableExists('temuan_accessories')) {
            return [];
        }

        $rawRows = $this->accessoryModel->getByAssetId($assetId);
        $result = [];
        foreach ($rawRows as $row) {
            $formatted = $this->formatSemanticObject($row);
            $merged = array_merge($row, $formatted);
            $merged['positions'] = $formatted['configuration']['positions'];
            $merged['display_phase'] = $formatted['configuration']['display_phase'];
            $result[] = $merged;
        }

        return $result;
    }
}
