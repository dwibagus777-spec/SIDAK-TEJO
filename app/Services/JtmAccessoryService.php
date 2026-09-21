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
                ['id' => 1, 'code' => 'GSW',                'name' => 'GSW',                 'category' => 'JTM', 'sort_order' => 1],
                ['id' => 2, 'code' => 'EGLA',               'name' => 'EGLA',                'category' => 'JTM', 'sort_order' => 2],
                ['id' => 3, 'code' => 'CLD',                'name' => 'CLD',                 'category' => 'JTM', 'sort_order' => 3],
                ['id' => 4, 'code' => 'MCA',                'name' => 'MCA',                 'category' => 'JTM', 'sort_order' => 4],
                ['id' => 5, 'code' => 'GROUND_GSW',         'name' => 'GROUND GSW',          'category' => 'JTM', 'sort_order' => 5],
                ['id' => 6, 'code' => 'PENGHALANG_BINATANG', 'name' => 'PENGHALANG BINATANG', 'category' => 'JTM', 'sort_order' => 6],
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

            // Check if existing record already exists for this (temuan_id, accessory_type_id)
            $existing = $this->db->table('temuan_accessories')
                ->where('temuan_id', $temuanId)
                ->where('accessory_type_id', $typeId)
                ->get()
                ->getRowArray();

            if ($existing) {
                // Update existing record
                $this->db->table('temuan_accessories')
                    ->where('id', $existing['id'])
                    ->update([
                        'asset_id'                => $assetId,
                        'accessory_name_snapshot' => $master['name'],
                        'status'                  => $status,
                        'condition'               => $condition,
                        'note'                    => $note,
                        'photo_url'               => $photoUrl ?: $existing['photo_url'],
                        'updated_at'              => $now,
                    ]);
                $savedCount++;
            } else {
                // Insert new record with Snapshot Invariant
                $this->db->table('temuan_accessories')->insert([
                    'temuan_id'               => $temuanId,
                    'asset_id'                => $assetId,
                    'accessory_type_id'       => $typeId,
                    'accessory_name_snapshot' => $master['name'],
                    'status'                  => $status,
                    'condition'               => $condition,
                    'note'                    => $note,
                    'photo_url'               => $photoUrl,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]);
                $savedCount++;
            }
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'status'  => 'ERROR',
                'message' => 'Gagal menyimpan data aksesoris JTM.',
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
     * Get all accessories for a finding
     */
    public function getAccessoriesForTemuan(int $temuanId): array
    {
        if ($temuanId <= 0 || !$this->db->tableExists('temuan_accessories')) {
            return [];
        }

        return $this->accessoryModel->getByTemuanId($temuanId);
    }

    /**
     * Get all accessories for an asset
     */
    public function getAccessoriesForAsset(int $assetId): array
    {
        if ($assetId <= 0 || !$this->db->tableExists('temuan_accessories')) {
            return [];
        }

        return $this->accessoryModel->getByAssetId($assetId);
    }
}
