<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\TemuanMaterialModel;
use CodeIgniter\Database\BaseConnection;

/**
 * MR-01 Phase 3B: Finding Material Transaction Service (Controlled Write Gate)
 *
 * Implements atomic persistence of technical material requests attached to a finding.
 *
 * Guarantees:
 * - Single Source of Authority: re-uses MaterialPickerService for all construction & BOM eligibility.
 * - Atomic Multi-Material Transactions: either ALL items persist or ZERO items persist.
 * - Immutable Truth of the Moment: server writes canonical_code, canonical_name, and unit snapshots.
 * - Strict Quantity Governance: user-provided, finite, strictly > 0, precision DECIMAL(10,2).
 * - Duplicate Prevention: unique combination on (temuan_id, asset_id, material_id).
 * - Zero Financial / Procurement / Stock Fields.
 */
class MaterialTransactionService
{
    protected BaseConnection $db;
    protected MaterialPickerService $pickerService;
    protected TemuanMaterialModel $temuanMaterialModel;
    protected AssetModel $assetModel;

    public function __construct(?BaseConnection $db = null, ?MaterialPickerService $pickerService = null)
    {
        $this->db                  = $db ?? \Config\Database::connect();
        $this->pickerService       = $pickerService ?? new MaterialPickerService($this->db);
        $this->temuanMaterialModel = new TemuanMaterialModel();
        $this->assetModel          = new AssetModel();
    }

    /**
     * Persist material transactions for a finding and asset.
     *
     * @param array $payload Must contain:
     *                       - temuan_id (int)
     *                       - asset_id (int)
     *                       - materials (array of [material_id, quantity, justification_note])
     * @param int|null $userId Authenticated user ID
     * @return array Standardized response payload
     */
    public function persistTransaction(array $payload, ?int $userId = null): array
    {
        // 1. Validate top-level payload structure
        $temuanId = isset($payload['temuan_id']) && is_numeric($payload['temuan_id']) ? (int)$payload['temuan_id'] : 0;
        $assetId  = isset($payload['asset_id']) && is_numeric($payload['asset_id']) ? (int)$payload['asset_id'] : 0;
        $items    = $payload['materials'] ?? [];

        if ($temuanId <= 0) {
            return [
                'status'  => 'VALIDATION_ERROR',
                'message' => 'Temuan ID wajib diisi dan harus valid.',
                'errors'  => ['temuan_id' => 'Temuan ID tidak valid.'],
            ];
        }

        if ($assetId <= 0) {
            return [
                'status'  => 'VALIDATION_ERROR',
                'message' => 'Asset ID wajib diisi dan harus valid.',
                'errors'  => ['asset_id' => 'Asset ID tidak valid.'],
            ];
        }

        if (!is_array($items) || empty($items)) {
            return [
                'status'  => 'VALIDATION_ERROR',
                'message' => 'Daftar material tidak boleh kosong.',
                'errors'  => ['materials' => 'Minimal pilih 1 material.'],
            ];
        }

        // 2. Validate Temuan exists
        $temuan = $this->db->table('temuan')
            ->where('id', $temuanId)
            ->get()
            ->getRowArray();

        if (!$temuan) {
            return [
                'status'  => 'VALIDATION_ERROR',
                'message' => 'Data Temuan tidak ditemukan.',
                'errors'  => ['temuan_id' => 'Temuan tidak ditemukan di database.'],
            ];
        }

        $temuanSectionId = (int)($temuan['section_id'] ?? 0);

        // 3. Validate Asset exists and enforce Section Scoping
        $asset = $this->assetModel->find($assetId);
        if (!$asset) {
            return [
                'status'  => 'INVALID_ASSET',
                'message' => 'Asset tidak ditemukan di database.',
                'errors'  => ['asset_id' => 'Asset tidak ditemukan.'],
            ];
        }

        if ((int)($asset['section_id'] ?? 0) !== $temuanSectionId) {
            return [
                'status'  => 'INVALID_ASSET',
                'message' => 'Asset tidak sesuai Section yang dipilih pada Temuan ini.',
                'errors'  => ['asset_id' => 'Cross-section asset violation detected.'],
            ];
        }

        // 4. Authoritative Eligibility Resolution via MaterialPickerService
        $pickerResult = $this->pickerService->resolvePicker($assetId, $temuanSectionId);
        if ($pickerResult['status'] !== 'READY') {
            return [
                'status'  => $pickerResult['status'],
                'message' => $pickerResult['message'],
                'errors'  => ['eligibility' => $pickerResult['message']],
            ];
        }

        $constructionId = (int)$pickerResult['construction']['id'];
        $eligibleMaterials = [];
        foreach ($pickerResult['materials'] as $m) {
            $eligibleMaterials[(int)$m['id']] = $m;
        }

        // 5. Item-by-item validation and batch preparation
        $rowsToInsert = [];
        $seenInBatch  = [];

        foreach ($items as $idx => $item) {
            $matId = isset($item['material_id']) && is_numeric($item['material_id']) ? (int)$item['material_id'] : 0;
            $qtyRaw = $item['quantity'] ?? null;
            $note   = isset($item['justification_note']) ? trim((string)$item['justification_note']) : null;

            // 5a. Material Eligibility Check
            if ($matId <= 0 || !isset($eligibleMaterials[$matId])) {
                return [
                    'status'  => 'VALIDATION_ERROR',
                    'message' => "Material #{$matId} tidak sah untuk konstruksi aset ini (bukan approved BOM item atau berstatus HELD/PROVISIONAL).",
                    'errors'  => ["materials.{$idx}.material_id" => "Material tidak terdaftar pada BOM konstruksi yang disetujui."],
                ];
            }

            // 5b. Quantity Governance
            if ($qtyRaw === null || !is_numeric($qtyRaw)) {
                return [
                    'status'  => 'VALIDATION_ERROR',
                    'message' => "Kuantitas material pada baris ke-" . ($idx + 1) . " wajib diisi dengan angka.",
                    'errors'  => ["materials.{$idx}.quantity" => "Kuantitas harus berupa angka valid."],
                ];
            }

            $qtyFloat = (float)$qtyRaw;
            if (is_nan($qtyFloat) || is_infinite($qtyFloat) || $qtyFloat <= 0) {
                return [
                    'status'  => 'VALIDATION_ERROR',
                    'message' => "Kuantitas material pada baris ke-" . ($idx + 1) . " harus bernilai positif (> 0).",
                    'errors'  => ["materials.{$idx}.quantity" => "Kuantitas tidak boleh 0 atau negatif."],
                ];
            }

            $qtyDecimal = number_format($qtyFloat, 2, '.', '');

            // 5c. Duplicate check within incoming batch
            if (isset($seenInBatch[$matId])) {
                return [
                    'status'  => 'CONFLICT',
                    'message' => "Material '{$eligibleMaterials[$matId]['name']}' duplikat dalam permintaan yang sama.",
                    'errors'  => ["materials.{$idx}.material_id" => "Duplikasi material dalam batch permintaan."],
                ];
            }
            $seenInBatch[$matId] = true;

            // 5d. Duplicate check against existing database transactions
            $existingTx = $this->temuanMaterialModel
                ->where('temuan_id', $temuanId)
                ->where('asset_id', $assetId)
                ->where('material_id', $matId)
                ->first();

            if ($existingTx) {
                return [
                    'status'  => 'CONFLICT',
                    'message' => "Material '{$eligibleMaterials[$matId]['name']}' sudah tercatat sebelumnya pada temuan dan aset ini.",
                    'errors'  => ["materials.{$idx}.material_id" => "Kombinasi temuan, aset, dan material sudah ada di database."],
                ];
            }

            // 5e. Build snapshot row (TRUTH OF THE MOMENT)
            $canonicalMat = $eligibleMaterials[$matId];
            $rowsToInsert[] = [
                'temuan_id'               => $temuanId,
                'asset_id'                => $assetId,
                'construction_type_id'    => $constructionId,
                'material_id'             => $matId,
                'canonical_code_snapshot' => $canonicalMat['code'],
                'canonical_name_snapshot' => $canonicalMat['name'],
                'unit_snapshot'           => $canonicalMat['unit'],
                'quantity'                => $qtyDecimal,
                'justification_note'      => $note ?: null,
                'source_mode'             => 'BOM_PICKER',
                'created_by'              => $userId,
                'created_at'              => date('Y-m-d H:i:s'),
                'updated_at'              => date('Y-m-d H:i:s'),
            ];
        }

        // 6. ATOMIC TRANSACTION PERSISTENCE
        $this->db->transBegin();

        try {
            foreach ($rowsToInsert as $row) {
                $inserted = $this->temuanMaterialModel->insert($row);
                if (!$inserted) {
                    $this->db->transRollback();
                    return [
                        'status'  => 'ERROR',
                        'message' => 'Gagal menyimpan transaksi baris material ke database.',
                        'errors'  => ['db' => 'Insert error on row'],
                    ];
                }
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return [
                    'status'  => 'ERROR',
                    'message' => 'Gagal mengunci transaksi material (Database status failed).',
                    'errors'  => ['trans' => 'Transaction status false'],
                ];
            }

            $this->db->transCommit();

            // Optional audit trail
            if (function_exists('log_activity')) {
                log_activity(
                    'CREATE_MATERIAL_TRANSACTION',
                    "Temuan ID #{$temuanId}, Asset #{$assetId}: " . count($rowsToInsert) . " material(s) tersimpan."
                );
            }

            return [
                'status'  => 'SUCCESS',
                'message' => 'Transaksi material berhasil disimpan ke database.',
                'data'    => [
                    'temuan_id'          => $temuanId,
                    'asset_id'           => $assetId,
                    'transaction_count'  => count($rowsToInsert),
                    'items'              => $rowsToInsert,
                ],
            ];

        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'ERROR',
                'message' => 'Transaksi dibatalkan karena kendala sistem: ' . $e->getMessage(),
                'errors'  => ['exception' => $e->getMessage()],
            ];
        }
    }
}
