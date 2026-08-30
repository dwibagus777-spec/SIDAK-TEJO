<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Service for AR-01 Phase 5G: Field Section Resolution & Topology Traceability Engine
 * 
 * Governance Invariants:
 * - Invariant 5G-A: Section assignment must belong strictly to the asset's parent feeder.
 * - Invariant 5G-B: All changes record an immutable audit history in asset_section_history.
 * - Invariant 5G-C: Explainable status transition (UNRESOLVED -> FIELD_REVIEW -> FIELD_VERIFIED -> CANONICAL).
 * - Invariant 5G-D: Zero blind heuristic assignment (human engineering field verification required).
 */
class FieldSectionResolutionService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Find asset by ID, code, or name.
     */
    public function findAsset($identifier, ?int $feederId = null): ?array
    {
        $builder = $this->db->table('assets');

        if (is_numeric($identifier) && (int)$identifier > 0) {
            $builder->groupStart()
                ->where('id', (int)$identifier);
            if ($this->db->fieldExists('kode_asset', 'assets')) {
                $builder->orWhere('kode_asset', (string)$identifier);
            }
            $builder->groupEnd();
        } else {
            $hasKodeAsset = $this->db->fieldExists('kode_asset', 'assets');
            $hasKodeAset  = $this->db->fieldExists('kode_aset', 'assets');
            $hasNamaAsset = $this->db->fieldExists('nama_asset', 'assets');
            $hasNamaAset  = $this->db->fieldExists('nama_aset', 'assets');

            $builder->groupStart();
            $added = false;
            if ($hasKodeAsset) { $builder->orWhere('kode_asset', (string)$identifier); $added = true; }
            if ($hasKodeAset)  { $builder->orWhere('kode_aset', (string)$identifier); $added = true; }
            if ($hasNamaAsset) { $builder->orWhere('nama_asset', (string)$identifier); $added = true; }
            if ($hasNamaAset)  { $builder->orWhere('nama_aset', (string)$identifier); $added = true; }
            if (!$added) {
                $builder->where('id', 0);
            }
            $builder->groupEnd();
        }

        if ($feederId !== null && $this->db->fieldExists('penyulang_id', 'assets')) {
            $builder->where('penyulang_id', $feederId);
        }
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }

        $res = $builder->get();
        return $res ? $res->getRowArray() : null;
    }

    /**
     * Get list of assets for a feeder with section info.
     */
    public function getFeederAssetsList(int $penyulangId, int $limit = 50, ?string $statusFilter = null): array
    {
        $builder = $this->db->table('assets')->where('penyulang_id', $penyulangId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }

        if ($statusFilter === 'UNRESOLVED') {
            $builder->groupStart()
                ->where('section_id IS NULL')
                ->orWhere('section_id', 0);
            if ($this->db->fieldExists('section_resolution_method', 'assets')) {
                $builder->orWhere('section_resolution_method', 'UNRESOLVED');
            }
            $builder->groupEnd();
        } elseif ($statusFilter === 'VERIFIED') {
            $builder->where('section_id IS NOT NULL')->where('section_id >', 0);
            if ($this->db->fieldExists('section_resolution_method', 'assets')) {
                $builder->whereIn('section_resolution_method', ['FIELD_VERIFIED', 'CANONICAL', 'IMPORT_VERIFIED']);
            }
        }

        $orderCol = $this->db->fieldExists('field_sequence', 'assets') ? 'field_sequence' : 'id';
        $assets = $builder->orderBy($orderCol, 'ASC')->limit($limit)->get()->getResultArray();

        // Fetch section names map
        $sections = $this->db->table('sections')->where('penyulang_id', $penyulangId)->get()->getResultArray();
        $secMap = [];
        foreach ($sections as $s) {
            $secMap[$s['id']] = $s['nama_seksi'] ?? $s['nama_section'] ?? "Seksi #{$s['id']}";
        }

        $result = [];
        foreach ($assets as $a) {
            $sId = !empty($a['section_id']) ? (int)$a['section_id'] : null;
            $result[] = [
                'id'                => (int)$a['id'],
                'kode_asset'        => $a['kode_asset'] ?? $a['kode_aset'] ?? 'N/A',
                'nama_asset'        => $a['nama_asset'] ?? $a['nama_aset'] ?? 'N/A',
                'penyulang_id'      => (int)$a['penyulang_id'],
                'section_id'        => $sId,
                'section_name'      => $sId && isset($secMap[$sId]) ? $secMap[$sId] : 'BELUM DITENTUKAN (UNRESOLVED)',
                'field_sequence'    => !empty($a['field_sequence']) ? (int)$a['field_sequence'] : (!empty($a['sequence_no']) ? (int)$a['sequence_no'] : null),
                'resolution_method' => $a['section_resolution_method'] ?? ($sId ? 'ASSIGNED' : 'UNRESOLVED'),
                'verified_by'       => $a['section_verified_by'] ?? null,
                'verified_at'       => $a['section_verified_at'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Get comprehensive section resolution summary for a feeder.
     */
    public function getFeederSectionResolutionSummary(int $penyulangId): array
    {
        $feeder = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
        if (!$feeder) {
            return ['success' => false, 'error' => "Penyulang ID #{$penyulangId} tidak ditemukan."];
        }

        // 1. Fetch configured sections from CR-06F physical network truth
        $seqCol = $this->db->fieldExists('sequence_order', 'sections') ? 'sequence_order' : ($this->db->fieldExists('urutan', 'sections') ? 'urutan' : 'id');
        $builder = $this->db->table('sections')->where('penyulang_id', $penyulangId);
        if ($this->db->fieldExists('status', 'sections')) {
            $builder->where('status', 'ACTIVE');
        }
        $getSec = $builder->orderBy($seqCol, 'ASC')->get();
        $sections = $getSec ? $getSec->getResultArray() : [];

        // 2. Fetch all active assets in feeder
        $builder = $this->db->table('assets')->where('penyulang_id', $penyulangId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }
        $getAssets = $builder->get();
        $assets = $getAssets ? $getAssets->getResultArray() : [];

        $totalAssets = count($assets);
        $verifiedCount = 0;
        $unresolvedCount = 0;
        $sectionDistribution = [];

        // Initialize section distribution
        foreach ($sections as $s) {
            $seqVal = isset($s['sequence_order']) ? (int)$s['sequence_order'] : (isset($s['urutan']) ? (int)$s['urutan'] : (int)$s['id']);
            $sectionDistribution[$s['id']] = [
                'section_id'     => (int)$s['id'],
                'nama_seksi'     => $s['nama_seksi'] ?? $s['nama_section'] ?? 'Seksi #' . $s['id'],
                'sequence_order' => $seqVal,
                'asset_count'    => 0,
                'sample_assets'  => [],
            ];
        }

        $unresolvedSample = [];

        foreach ($assets as $a) {
            $secId = !empty($a['section_id']) ? (int)$a['section_id'] : null;
            $resMethod = $a['section_resolution_method'] ?? 'UNRESOLVED';
            $assetSummary = sprintf("#%d [%s] %s", $a['id'], $a['kode_asset'] ?? $a['nama_asset'], $a['nama_asset'] ?? '');

            if ($secId !== null && isset($sectionDistribution[$secId])) {
                $sectionDistribution[$secId]['asset_count']++;
                if (count($sectionDistribution[$secId]['sample_assets']) < 3) {
                    $sectionDistribution[$secId]['sample_assets'][] = $assetSummary;
                }
            }

            if ($secId !== null && in_array($resMethod, ['FIELD_VERIFIED', 'CANONICAL', 'IMPORT_VERIFIED'], true)) {
                $verifiedCount++;
            } else {
                $unresolvedCount++;
                if (count($unresolvedSample) < 5) {
                    $unresolvedSample[] = $assetSummary;
                }
            }
        }

        $completenessRatio = $totalAssets > 0 ? round(($verifiedCount / $totalAssets) * 100.0, 2) : 0.0;

        return [
            'success'               => true,
            'feeder_id'             => $penyulangId,
            'kode_penyulang'        => $feeder['kode_penyulang'] ?? 'N/A',
            'nama_penyulang'        => $feeder['nama_penyulang'] ?? 'N/A',
            'total_assets'          => $totalAssets,
            'verified_assets'       => $verifiedCount,
            'unresolved_assets'     => $unresolvedCount,
            'completeness_ratio'    => $completenessRatio,
            'configured_sections'   => $sections,
            'section_distribution'  => array_values($sectionDistribution),
            'unresolved_samples'    => $unresolvedSample,
        ];
    }

    /**
     * Verify or update an asset's section from field engineering.
     */
    public function verifyAssetSection(
        $assetIdentifier,
        int $newSectionId,
        string $verifiedBy,
        string $reason,
        ?int $sequence = null,
        ?float $lat = null,
        ?float $lon = null
    ): array {
        if (empty(trim($verifiedBy))) {
            return ['success' => false, 'error' => 'Petugas verifikasi (verified_by / NIP) wajib diisi.'];
        }
        if (empty(trim($reason))) {
            return ['success' => false, 'error' => 'Alasan verifikasi (reason) wajib diisi.'];
        }

        $asset = $this->findAsset($assetIdentifier);
        if (!$asset) {
            return ['success' => false, 'error' => "Aset '{$assetIdentifier}' tidak ditemukan di tabel assets."];
        }

        $assetId = (int)$asset['id'];
        $feederId = (int)$asset['penyulang_id'];

        // Invariant 5G-A: Section MUST belong to the same feeder
        $section = $this->db->table('sections')->where('id', $newSectionId)->get()->getRowArray();
        if (!$section) {
            return ['success' => false, 'error' => "Section ID #{$newSectionId} tidak ditemukan."];
        }
        if ((int)$section['penyulang_id'] !== $feederId) {
            $secLabel = $section['nama_seksi'] ?? $section['nama_section'] ?? ('Seksi #' . $newSectionId);
            return [
                'success' => false,
                'error'   => "Invariant 5G-A Violated: Section '{$secLabel}' (Feeder #{$section['penyulang_id']}) tidak cocok dengan feeder aset #{$feederId}.",
            ];
        }

        $now = date('Y-m-d H:i:s');
        $oldSectionId = !empty($asset['section_id']) ? (int)$asset['section_id'] : null;
        $oldSequence  = !empty($asset['field_sequence']) ? (int)$asset['field_sequence'] : (!empty($asset['sequence_no']) ? (int)$asset['sequence_no'] : null);

        // 1. Record Immutable Audit History
        if ($this->db->tableExists('asset_section_history')) {
            $this->db->table('asset_section_history')->insert([
                'asset_id'                  => $assetId,
                'penyulang_id'              => $feederId,
                'old_section_id'            => $oldSectionId,
                'new_section_id'            => $newSectionId,
                'old_sequence'              => $oldSequence,
                'new_sequence'              => $sequence,
                'resolution_method'         => 'FIELD_VERIFIED',
                'verified_by'               => $verifiedBy,
                'reason'                    => $reason,
                'latitude_at_verification'  => $lat,
                'longitude_at_verification' => $lon,
                'created_at'                => $now,
            ]);
        }

        // 2. Update Master Asset Record
        $updateData = [
            'section_id' => $newSectionId,
        ];
        
        $existingCols = [];
        try {
            $table = $this->db->prefixTable('assets');
            $query = $this->db->query("PRAGMA table_info({$table})");
            if ($query) {
                foreach ($query->getResultArray() as $row) {
                    $existingCols[] = strtolower($row['name']);
                }
            }
        } catch (\Throwable $e) {
            $existingCols = array_map('strtolower', $this->db->getFieldNames('assets'));
        }

        if (empty($existingCols)) {
            $existingCols = array_map('strtolower', $this->db->getFieldNames('assets'));
        }

        if (in_array('section_resolution_method', $existingCols, true)) {
            $updateData['section_resolution_method'] = 'FIELD_VERIFIED';
        }
        if (in_array('section_verified_by', $existingCols, true)) {
            $updateData['section_verified_by'] = $verifiedBy;
        }
        if (in_array('section_verified_at', $existingCols, true)) {
            $updateData['section_verified_at'] = $now;
        }
        if (in_array('field_sequence', $existingCols, true)) {
            $updateData['field_sequence'] = $sequence;
        }
        if (in_array('sequence_no', $existingCols, true) && $sequence !== null) {
            $updateData['sequence_no'] = $sequence;
        }
        if (in_array('updated_at', $existingCols, true)) {
            $updateData['updated_at'] = $now;
        }
        if (in_array('intelligence_resolution_status', $existingCols, true)) {
            $updateData['intelligence_resolution_status'] = 'FIELD_VERIFIED';
        }

        $this->db->table('assets')->where('id', $assetId)->update($updateData);

        // 3. Log to general audit log
        $this->logAuditEvent('AR01_FIELD_SECTION_VERIFIED', $verifiedBy, [
            'asset_id'       => $assetId,
            'asset_code'     => $asset['kode_asset'] ?? $asset['nama_asset'],
            'penyulang_id'   => $feederId,
            'old_section_id' => $oldSectionId,
            'new_section_id' => $newSectionId,
            'sequence'       => $sequence,
            'reason'         => $reason,
        ]);

        $secName = $section['nama_seksi'] ?? $section['nama_section'] ?? ('Seksi #' . $newSectionId);

        return [
            'success'            => true,
            'asset_id'           => $assetId,
            'asset_name'         => $asset['nama_asset'] ?? $asset['kode_asset'],
            'penyulang_id'       => $feederId,
            'old_section_id'     => $oldSectionId,
            'new_section_id'     => $newSectionId,
            'section_name'       => $secName,
            'field_sequence'     => $sequence,
            'verified_by'        => $verifiedBy,
            'verified_at'        => $now,
            'resolution_method'  => 'FIELD_VERIFIED',
        ];
    }

    /**
     * Bulk verify multiple assets for a specific section.
     */
    public function bulkVerifyAssetSection(array $assetIdentifiers, int $newSectionId, string $verifiedBy, string $reason): array
    {
        $hasTrans = false;
        try {
            $this->db->transBegin();
            $hasTrans = true;
        } catch (\Throwable $e) {
            $hasTrans = false;
        }

        $successCount = 0;
        $failedErrors = [];

        foreach ($assetIdentifiers as $ident) {
            $res = $this->verifyAssetSection($ident, $newSectionId, $verifiedBy, $reason);
            if ($res['success']) {
                $successCount++;
            } else {
                $failedErrors[] = "Asset '{$ident}': " . $res['error'];
            }
        }

        if (!empty($failedErrors)) {
            if ($hasTrans) $this->db->transRollback();
            return [
                'success'       => false,
                'error'         => 'Bulk verification failed. Rolling back all changes.',
                'error_details' => $failedErrors,
            ];
        }

        if ($hasTrans) {
            try {
                if ($this->db->transStatus() === false) {
                    $this->db->transRollback();
                    return ['success' => false, 'error' => 'Database transaction failed.'];
                }
                $this->db->transCommit();
            } catch (\Throwable $e) {
                // pass
            }
        }

        return [
            'success'        => true,
            'verified_count' => $successCount,
            'new_section_id' => $newSectionId,
            'verified_by'    => $verifiedBy,
        ];
    }

    /**
     * Get complete section history for an asset.
     */
    public function getAssetSectionHistory(int $assetId): array
    {
        if (!$this->db->tableExists('asset_section_history')) {
            return [];
        }

        return $this->db->table('asset_section_history')
            ->where('asset_id', $assetId)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Log audit event into ar01_audit_log.
     */
    protected function logAuditEvent(string $eventType, string $actor, array $data): void
    {
        if ($this->db->tableExists('ar01_audit_log')) {
            $this->db->table('ar01_audit_log')->insert([
                'batch_id'   => null,
                'event_type' => $eventType,
                'actor'      => $actor,
                'event_data' => json_encode($data),
                'status'     => 'SUCCESS',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
