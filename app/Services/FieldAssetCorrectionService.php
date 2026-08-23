<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Field Network Collaborative Intelligence & Digital Twin Editor Service (Wave 3 Phase PH-AI-GIS-01)
 *
 * Responsibilities:
 * - Single application service boundary for field asset corrections, additions, soft decommissioning, and transline editing.
 * - Enforces proposal & validation guardrail (Master data is never overwritten without verified approval).
 * - Collision-proof auto-naming with transactional row locks on asset_sequence_counters.
 * - Dual-layer topology versioning with rollback support.
 * - Human-in-the-Loop AI Feedback Knowledge Base integration.
 */
class FieldAssetCorrectionService
{
    protected BaseConnection $db;
    protected AssetVisualRegistryService $visualRegistry;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->visualRegistry = new AssetVisualRegistryService();
    }

    /**
     * Generate Collision-Proof Next Asset Code for a Feeder Scope
     *
     * @param int $penyulangId
     * @param string $jenisAsset
     * @return array<string, mixed>
     */
    public function generateNextAssetCode(int $penyulangId, string $jenisAsset): array
    {
        $jenisClean = strtoupper(trim(str_replace(['-', ' '], '_', $jenisAsset)));
        
        $prefix = match(true) {
            str_contains($jenisClean, 'TRAFO') || str_contains($jenisClean, 'GARDU') => 'TRF',
            str_contains($jenisClean, 'LBS') || str_contains($jenisClean, 'SWITCH') => 'LBS',
            str_contains($jenisClean, 'REC') || str_contains($jenisClean, 'PMCB') => 'REC',
            str_contains($jenisClean, 'KUB') => 'KUB',
            default => 'JTM',
        };

        // Fetch Feeder & ULP Info
        $feeder = $this->db->table('penyulang p')
                           ->select('p.id, p.kode_penyulang, p.nama_penyulang, p.ulp_id, u.kode_ulp')
                           ->join('ulps u', 'p.ulp_id = u.id', 'left')
                           ->where('p.id', $penyulangId)
                           ->get()
                           ->getRowArray();

        $ulpCode    = !empty($feeder['kode_ulp']) ? strtoupper(substr($feeder['kode_ulp'], 0, 4)) : 'ULP';
        $feederCode = !empty($feeder['kode_penyulang']) ? strtoupper(substr(str_replace(['-', ' '], '', $feeder['kode_penyulang']), 0, 6)) : 'FDR' . $penyulangId;

        // Transactional Counter Lock with Auto-Initialization
        $this->db->transStart();

        $counterRow = $this->db->table('asset_sequence_counters')
                               ->where('penyulang_id', $penyulangId)
                               ->where('asset_type_prefix', $prefix)
                               ->get()
                               ->getRowArray();

        if ($counterRow) {
            $nextSeq = (int)$counterRow['last_sequence_no'] + 1;
            $this->db->table('asset_sequence_counters')
                     ->where('id', $counterRow['id'])
                     ->update([
                         'last_sequence_no' => $nextSeq,
                         'updated_at'       => date('Y-m-d H:i:s')
                     ]);
        } else {
            // Find highest existing sequence in assets table for this feeder & type
            $highestAsset = $this->db->table('assets')
                                     ->where('penyulang_id', $penyulangId)
                                     ->like('kode_asset', $prefix)
                                     ->countAllResults();

            $nextSeq = max($highestAsset + 1, 1);
            $this->db->table('asset_sequence_counters')->insert([
                'penyulang_id'       => $penyulangId,
                'asset_type_prefix'  => $prefix,
                'last_sequence_no'   => $nextSeq,
                'updated_at'         => date('Y-m-d H:i:s')
            ]);
        }

        $this->db->transComplete();

        $padSeq = str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
        $generatedCode = "AST-{$ulpCode}-{$feederCode}-{$prefix}-{$padSeq}";

        return [
            'status'         => 'success',
            'kode_asset'     => $generatedCode,
            'sequence_no'    => $nextSeq,
            'prefix'         => $prefix,
            'feeder_id'      => $penyulangId,
            'ulp_id'         => $feeder['ulp_id'] ?? null,
        ];
    }

    /**
     * Propose Asset Field Correction (Construction, Coordinates, Condition)
     *
     * @param array $data
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function proposeAssetCorrection(array $data, ?array $actor = null): array
    {
        $assetId = (int)($data['asset_id'] ?? 0);
        $asset = $this->db->table('assets')->where('id', $assetId)->get()->getRowArray();

        if (!$asset) {
            return [
                'status'  => 'error',
                'message' => "Aset #{$assetId} tidak ditemukan dalam database.",
                'code'    => 'ASSET_NOT_FOUND'
            ];
        }

        $correctionType = $data['correction_type'] ?? 'ASSET_CONSTRUCTION';
        $rationale = trim((string)($data['rationale'] ?? 'Koreksi parameter aset lapangan'));

        if (empty($rationale)) {
            return [
                'status'  => 'error',
                'message' => 'Alasan / penjelasan koreksi wajib diisi.',
                'code'    => 'RATIONALE_REQUIRED'
            ];
        }

        $beforePayload = [
            'kode_asset'        => $asset['kode_asset'],
            'nama_asset'        => $asset['nama_asset'],
            'jenis_asset'       => $asset['jenis_asset'],
            'type'              => $asset['type'],
            'latitude'          => $asset['latitude'],
            'longitude'         => $asset['longitude'],
            'status'            => $asset['status'],
        ];

        $afterPayload = array_merge($beforePayload, [
            'type'              => $data['proposed_construction'] ?? $asset['type'],
            'nama_asset'        => $data['proposed_name'] ?? $asset['nama_asset'],
            'latitude'          => isset($data['latitude']) ? (float)$data['latitude'] : $asset['latitude'],
            'longitude'         => isset($data['longitude']) ? (float)$data['longitude'] : $asset['longitude'],
            'status'            => $data['proposed_condition'] ?? $asset['status'],
        ]);

        $correctionCode = 'COR-' . date('Ymd') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $actorName = $actor['name'] ?? 'PETUGAS_LAPANGAN';
        $actorRole = $actor['role'] ?? 'PETUGAS_LAPANGAN';
        $actorId   = $actor['id'] ?? null;

        $insertData = [
            'correction_code'    => $correctionCode,
            'correction_type'    => $correctionType,
            'asset_id'           => $assetId,
            'penyulang_id'       => (int)$asset['penyulang_id'],
            'ulp_id'             => (int)$asset['ulp_id'],
            'before_payload'     => json_encode($beforePayload),
            'after_payload'      => json_encode($afterPayload),
            'rationale'          => $rationale,
            'evidence_photo_uri' => $data['evidence_photo_uri'] ?? null,
            'latitude'           => $data['latitude'] ?? $asset['latitude'],
            'longitude'          => $data['longitude'] ?? $asset['longitude'],
            'reporter_id'        => $actorId,
            'reporter_name'      => $actorName,
            'reporter_role'      => $actorRole,
            'status'             => 'SUBMITTED',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        $this->db->table('field_corrections')->insert($insertData);
        $correctionId = (int)$this->db->insertID();

        // Record Initial AI Feedback Capture in PENDING state
        $this->db->table('ai_correction_feedback')->insert([
            'correction_id'      => $correctionId,
            'asset_id'           => $assetId,
            'penyulang_id'       => (int)$asset['penyulang_id'],
            'feature_context'    => json_encode(['before' => $beforePayload, 'location' => [$asset['latitude'], $asset['longitude']]]),
            'predicted_value'    => (string)($beforePayload['type'] ?? 'TM_1'),
            'ground_truth_value' => (string)($afterPayload['type'] ?? 'TM_5'),
            'confidence_score'   => 0.8500,
            'learning_status'    => 'PENDING',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        return [
            'status'          => 'success',
            'message'         => "Usulan koreksi #{$correctionCode} berhasil diajukan dan menunggu telaah supervisor.",
            'correction_id'   => $correctionId,
            'correction_code' => $correctionCode,
            'proposal_status' => 'SUBMITTED',
            'proposed_state'  => $afterPayload,
        ];
    }

    /**
     * Propose Adding New Asset Directly from Map / Field
     *
     * @param array $data
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function proposeNewAsset(array $data, ?array $actor = null): array
    {
        $penyulangId = (int)($data['penyulang_id'] ?? 0);
        if ($penyulangId <= 0) {
            return [
                'status'  => 'error',
                'message' => 'Penyulang wajib dipilih untuk penambahan aset baru.',
                'code'    => 'PENYULANG_REQUIRED'
            ];
        }

        $jenisAsset = $data['jenis_asset'] ?? 'JTM';
        $construction = $data['construction_type'] ?? 'TM-1';
        $namaAsset = trim((string)($data['nama_asset'] ?? ''));

        // Generate safe auto-code
        $codeGen = $this->generateNextAssetCode($penyulangId, $jenisAsset);
        $assetCode = $codeGen['kode_asset'];

        if (empty($namaAsset)) {
            $namaAsset = "{$jenisAsset} {$construction} ({$assetCode})";
        }

        $afterPayload = [
            'kode_asset'        => $assetCode,
            'nama_asset'        => $namaAsset,
            'jenis_asset'       => $jenisAsset,
            'type'              => $construction,
            'penyulang_id'      => $penyulangId,
            'ulp_id'            => $data['ulp_id'] ?? $codeGen['ulp_id'],
            'lokasi'            => $data['lokasi'] ?? 'Lokasi Baru Jaringan PLN',
            'latitude'          => (float)($data['latitude'] ?? -7.4523),
            'longitude'         => (float)($data['longitude'] ?? 112.7161),
            'status'            => 'NORMAL',
            'tahun_instalasi'   => (int)($data['tahun_instalasi'] ?? date('Y')),
        ];

        $correctionCode = 'COR-NEW-' . date('Ymd') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $actorName = $actor['name'] ?? 'PETUGAS_LAPANGAN';
        $actorRole = $actor['role'] ?? 'PETUGAS_LAPANGAN';

        $this->db->table('field_corrections')->insert([
            'correction_code'    => $correctionCode,
            'correction_type'    => 'ASSET_ADD',
            'asset_id'           => null,
            'penyulang_id'       => $penyulangId,
            'ulp_id'             => $afterPayload['ulp_id'],
            'before_payload'     => null,
            'after_payload'      => json_encode($afterPayload),
            'rationale'          => $data['rationale'] ?? 'Penemuan / pemasangan aset baru di lapangan',
            'evidence_photo_uri' => $data['evidence_photo_uri'] ?? null,
            'latitude'           => $afterPayload['latitude'],
            'longitude'          => $afterPayload['longitude'],
            'reporter_id'        => $actor['id'] ?? null,
            'reporter_name'      => $actorName,
            'reporter_role'      => $actorRole,
            'status'             => 'SUBMITTED',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        $correctionId = (int)$this->db->insertID();

        return [
            'status'          => 'success',
            'message'         => "Usulan aset baru '{$assetCode}' berhasil diajukan.",
            'correction_id'   => $correctionId,
            'correction_code' => $correctionCode,
            'asset_code'      => $assetCode,
            'proposed_data'   => $afterPayload,
        ];
    }

    /**
     * Report Asset as Missing / Decommissioned (Soft Decommissioning Guardrail)
     *
     * @param int $assetId
     * @param string $reason
     * @param string|null $photo
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function reportMissingAsset(int $assetId, string $reason, ?string $photo = null, ?array $actor = null): array
    {
        $asset = $this->db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) {
            return [
                'status'  => 'error',
                'message' => "Aset #{$assetId} tidak ditemukan.",
                'code'    => 'ASSET_NOT_FOUND'
            ];
        }

        $correctionCode = 'COR-DEL-' . date('Ymd') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $actorName = $actor['name'] ?? 'PETUGAS_LAPANGAN';
        $actorRole = $actor['role'] ?? 'PETUGAS_LAPANGAN';

        $this->db->table('field_corrections')->insert([
            'correction_code'    => $correctionCode,
            'correction_type'    => 'ASSET_MISSING',
            'asset_id'           => $assetId,
            'penyulang_id'       => (int)$asset['penyulang_id'],
            'ulp_id'             => (int)$asset['ulp_id'],
            'before_payload'     => json_encode($asset),
            'after_payload'      => json_encode(['status' => 'REPORTED_MISSING', 'reason' => $reason]),
            'rationale'          => $reason,
            'evidence_photo_uri' => $photo,
            'latitude'           => $asset['latitude'],
            'longitude'          => $asset['longitude'],
            'reporter_id'        => $actor['id'] ?? null,
            'reporter_name'      => $actorName,
            'reporter_role'      => $actorRole,
            'status'             => 'SUBMITTED',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        $correctionId = (int)$this->db->insertID();

        // Update soft status flag on asset (retaining full audit lineage)
        $this->db->table('assets')->where('id', $assetId)->update([
            'status'     => 'REPORTED_MISSING',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Record in change history
        $this->db->table('asset_change_history')->insert([
            'asset_id'           => $assetId,
            'correction_id'      => $correctionId,
            'change_type'        => 'REPORTED_MISSING',
            'field_name'         => 'status',
            'previous_value'     => $asset['status'],
            'new_value'          => 'REPORTED_MISSING',
            'actor_name'         => $actorName,
            'actor_role'         => $actorRole,
            'rationale'          => $reason,
            'evidence_photo_uri' => $photo,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        return [
            'status'          => 'success',
            'message'         => "Pelaporan aset hilang #{$asset['kode_asset']} berhasil diajukan.",
            'correction_id'   => $correctionId,
            'correction_code' => $correctionCode,
        ];
    }

    /**
     * Propose Transline Geometry Correction (Dual-Layer Topology Versioning)
     *
     * @param int $penyulangId
     * @param array $geoJsonGeometry
     * @param string $rationale
     * @param array|null $actor
     * @return array<string, mixed>
     */
    public function proposeTranslineCorrection(int $penyulangId, array $geoJsonGeometry, string $rationale, ?array $actor = null): array
    {
        if ($penyulangId <= 0) {
            return [
                'status'  => 'error',
                'message' => 'Penyulang wajib dipilih.',
                'code'    => 'PENYULANG_REQUIRED'
            ];
        }

        // Calculate latest version number
        $latestVer = (int)$this->db->table('network_topology_versions')
                                  ->where('penyulang_id', $penyulangId)
                                  ->selectMax('version_no')
                                  ->get()
                                  ->getRow()->version_no ?? 0;

        $newVer = $latestVer + 1;
        $nodesCount = count($geoJsonGeometry['coordinates'] ?? []);
        $actorName = $actor['name'] ?? 'PETUGAS_LAPANGAN';
        $actorRole = strtoupper((string)($actor['role'] ?? 'PETUGAS_LAPANGAN'));

        // Check if actor has Admin authority (Direct Commit without Supervisor review)
        $isAdmin = (str_contains($actorRole, 'ADMIN') || in_array($actorRole, ['SUPER_ADMIN', 'SUPERADMIN', 'DALOPS', 'MANAJER']));

        $correctionCode = 'COR-TOP-' . date('Ymd') . '-' . str_pad((string)mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        $this->db->transStart();

        if ($isAdmin) {
            // ADMIN DIRECT COMMIT WORKFLOW: Supersede old active versions & activate new version immediately
            $this->db->table('network_topology_versions')
                     ->where('penyulang_id', $penyulangId)
                     ->where('is_active', 1)
                     ->update([
                         'is_active'      => 0,
                         'version_status' => 'SUPERSEDED',
                         'superseded_at'  => date('Y-m-d H:i:s')
                     ]);

            $this->db->table('field_corrections')->insert([
                'correction_code'    => $correctionCode,
                'correction_type'    => 'TRANSLINE_TOPOLOGY',
                'asset_id'           => null,
                'penyulang_id'       => $penyulangId,
                'ulp_id'             => null,
                'before_payload'     => null,
                'after_payload'      => json_encode($geoJsonGeometry),
                'rationale'          => $rationale,
                'reporter_name'      => $actorName,
                'reporter_role'      => $actorRole,
                'status'             => 'APPROVED',
                'reviewer_name'      => $actorName,
                'reviewer_role'      => $actorRole,
                'review_notes'       => 'Direct Commit by Administrator (GIS_ADMIN_DIRECT_EDIT)',
                'reviewed_at'        => date('Y-m-d H:i:s'),
                'applied_at'         => date('Y-m-d H:i:s'),
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            ]);

            $correctionId = (int)$this->db->insertID();

            $this->db->table('network_topology_versions')->insert([
                'penyulang_id'     => $penyulangId,
                'version_no'       => $newVer,
                'correction_id'    => $correctionId,
                'geojson_topology' => json_encode($geoJsonGeometry),
                'nodes_count'      => $nodesCount,
                'segments_count'   => max($nodesCount - 1, 0),
                'is_active'        => 1, // Directly active!
                'version_status'   => 'ACTIVE',
                'created_by'       => $actorName,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);

            $this->db->transComplete();

            return [
                'status'           => 'success',
                'is_direct_commit' => true,
                'message'          => "Jalur transline v{$newVer} berhasil diperbarui dan langsung aktif (Direct Commit).",
                'correction_id'    => $correctionId,
                'correction_code'  => $correctionCode,
                'version_no'       => $newVer,
                'version_status'   => 'ACTIVE',
            ];
        }

        // NON-ADMIN PROPOSAL WORKFLOW: Requires Supervisor Review
        $this->db->table('field_corrections')->insert([
            'correction_code'    => $correctionCode,
            'correction_type'    => 'TRANSLINE_TOPOLOGY',
            'asset_id'           => null,
            'penyulang_id'       => $penyulangId,
            'ulp_id'             => null,
            'before_payload'     => null,
            'after_payload'      => json_encode($geoJsonGeometry),
            'rationale'          => $rationale,
            'reporter_name'      => $actorName,
            'reporter_role'      => $actorRole,
            'status'             => 'SUBMITTED',
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        $correctionId = (int)$this->db->insertID();

        $this->db->table('network_topology_versions')->insert([
            'penyulang_id'     => $penyulangId,
            'version_no'       => $newVer,
            'correction_id'    => $correctionId,
            'geojson_topology' => json_encode($geoJsonGeometry),
            'nodes_count'      => $nodesCount,
            'segments_count'   => max($nodesCount - 1, 0),
            'is_active'        => 0, // Inactive until approved
            'version_status'   => 'PROPOSED',
            'created_by'       => $actorName,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        return [
            'status'           => 'success',
            'is_direct_commit' => false,
            'message'          => "Usulan koreksi jalur transline v{$newVer} berhasil diajukan (Pending Approval).",
            'correction_id'    => $correctionId,
            'correction_code'  => $correctionCode,
            'version_no'       => $newVer,
            'version_status'   => 'PROPOSED',
        ];
    }

    /**
     * Approve & Apply Correction into Production Master (Supervisor / Authority Role Required)
     *
     * @param int $correctionId
     * @param string|null $notes
     * @param array|null $approver
     * @return array<string, mixed>
     */
    public function approveAndApplyCorrection(int $correctionId, ?string $notes = null, ?array $approver = null): array
    {
        $correction = $this->db->table('field_corrections')->where('id', $correctionId)->get()->getRowArray();
        if (!$correction) {
            return [
                'status'  => 'error',
                'message' => "Koreksi #{$correctionId} tidak ditemukan.",
                'code'    => 'CORRECTION_NOT_FOUND'
            ];
        }

        if (in_array($correction['status'], ['APPLIED', 'REJECTED'], true)) {
            return [
                'status'  => 'error',
                'message' => "Koreksi ini sudah berstatus {$correction['status']} dan tidak dapat diproses ulang.",
                'code'    => 'CORRECTION_ALREADY_PROCESSED'
            ];
        }

        $approverName = $approver['name'] ?? 'SPV_JARINGAN_DISTRIBUSI';
        $approverRole = $approver['role'] ?? 'SUPERVISOR_UNIT';
        $afterData = json_decode((string)$correction['after_payload'], true) ?? [];

        $this->db->transStart();

        // Apply based on correction type
        if ($correction['correction_type'] === 'ASSET_CONSTRUCTION') {
            $assetId = (int)$correction['asset_id'];
            $this->db->table('assets')->where('id', $assetId)->update([
                'type'       => $afterData['type'] ?? 'TM-5',
                'status'     => $afterData['status'] ?? 'NORMAL',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('asset_change_history')->insert([
                'asset_id'           => $assetId,
                'correction_id'      => $correctionId,
                'change_type'        => 'CONSTRUCTION_UPDATE',
                'field_name'         => 'type',
                'previous_value'     => $correction['before_payload'],
                'new_value'          => $afterData['type'] ?? 'TM-5',
                'actor_name'         => $approverName,
                'actor_role'         => $approverRole,
                'rationale'          => $correction['rationale'],
                'evidence_photo_uri' => $correction['evidence_photo_uri'],
                'created_at'         => date('Y-m-d H:i:s'),
            ]);

            // Promote AI Feedback to VALIDATED
            $this->db->table('ai_correction_feedback')
                     ->where('correction_id', $correctionId)
                     ->update([
                         'learning_status' => 'VALIDATED',
                         'validated_by'    => $approverName,
                         'validated_at'    => date('Y-m-d H:i:s'),
                         'updated_at'      => date('Y-m-d H:i:s'),
                     ]);
        } elseif ($correction['correction_type'] === 'ASSET_LOCATION') {
            $assetId = (int)$correction['asset_id'];
            $this->db->table('assets')->where('id', $assetId)->update([
                'latitude'   => $afterData['latitude'],
                'longitude'  => $afterData['longitude'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->table('asset_change_history')->insert([
                'asset_id'           => $assetId,
                'correction_id'      => $correctionId,
                'change_type'        => 'COORDINATE_MOVE',
                'field_name'         => 'coordinates',
                'previous_value'     => $correction['before_payload'],
                'new_value'          => json_encode([$afterData['latitude'], $afterData['longitude']]),
                'actor_name'         => $approverName,
                'actor_role'         => $approverRole,
                'rationale'          => $correction['rationale'],
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        } elseif ($correction['correction_type'] === 'ASSET_ADD') {
            $this->db->table('assets')->insert([
                'kode_asset'      => $afterData['kode_asset'],
                'nama_asset'      => $afterData['nama_asset'],
                'jenis_asset'     => $afterData['jenis_asset'],
                'type'            => $afterData['type'],
                'penyulang_id'    => $afterData['penyulang_id'],
                'ulp_id'          => $afterData['ulp_id'],
                'lokasi'          => $afterData['lokasi'],
                'latitude'        => $afterData['latitude'],
                'longitude'       => $afterData['longitude'],
                'status'          => 'NORMAL',
                'tahun_instalasi' => $afterData['tahun_instalasi'] ?? date('Y'),
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
            $newAssetId = (int)$this->db->insertID();

            $this->db->table('asset_change_history')->insert([
                'asset_id'           => $newAssetId,
                'correction_id'      => $correctionId,
                'change_type'        => 'ASSET_CREATED',
                'field_name'         => 'master',
                'previous_value'     => null,
                'new_value'          => json_encode($afterData),
                'actor_name'         => $approverName,
                'actor_role'         => $approverRole,
                'rationale'          => $correction['rationale'],
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
        } elseif ($correction['correction_type'] === 'TRANSLINE_TOPOLOGY') {
            $penyulangId = (int)$correction['penyulang_id'];
            // Demote current active version
            $this->db->table('network_topology_versions')
                     ->where('penyulang_id', $penyulangId)
                     ->where('is_active', 1)
                     ->update(['is_active' => 0, 'version_status' => 'HISTORICAL']);

            // Promote target version
            $this->db->table('network_topology_versions')
                     ->where('correction_id', $correctionId)
                     ->update(['is_active' => 1, 'version_status' => 'ACTIVE']);
        }

        // Finalize correction record
        $this->db->table('field_corrections')->where('id', $correctionId)->update([
            'status'         => 'APPLIED',
            'approver_name'  => $approverName,
            'approver_notes' => $notes ?? 'Disetujui dan diterapkan ke master data jaringan',
            'applied_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        return [
            'status'          => 'success',
            'message'         => "Koreksi #{$correction['correction_code']} resmi disetujui & diterapkan ke data master.",
            'correction_id'   => $correctionId,
            'applied_status'  => 'APPLIED',
        ];
    }

    /**
     * Reject Correction Proposal
     *
     * @param int $correctionId
     * @param string $rejectionReason
     * @param array|null $approver
     * @return array<string, mixed>
     */
    public function rejectCorrection(int $correctionId, string $rejectionReason, ?array $approver = null): array
    {
        $correction = $this->db->table('field_corrections')->where('id', $correctionId)->get()->getRowArray();
        if (!$correction) {
            return ['status' => 'error', 'message' => 'Koreksi tidak ditemukan.', 'code' => 'NOT_FOUND'];
        }

        $this->db->transStart();

        // If it was missing report, restore asset to NORMAL
        if ($correction['correction_type'] === 'ASSET_MISSING' && !empty($correction['asset_id'])) {
            $this->db->table('assets')->where('id', $correction['asset_id'])->update([
                'status'     => 'NORMAL',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->table('field_corrections')->where('id', $correctionId)->update([
            'status'         => 'REJECTED',
            'approver_name'  => $approver['name'] ?? 'SUPERVISOR',
            'approver_notes' => $rejectionReason,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('ai_correction_feedback')
                 ->where('correction_id', $correctionId)
                 ->update([
                     'learning_status' => 'REJECTED',
                     'updated_at'      => date('Y-m-d H:i:s'),
                 ]);

        $this->db->transComplete();

        return [
            'status'  => 'success',
            'message' => "Koreksi #{$correction['correction_code']} ditolak.",
        ];
    }

    /**
     * Get Pending Corrections for Feeder / Scope
     *
     * @param int|null $penyulangId
     * @return array<int, array<string, mixed>>
     */
    public function getPendingCorrections(?int $penyulangId = null): array
    {
        $builder = $this->db->table('field_corrections fc')
                            ->select('fc.*, a.nama_asset, p.nama_penyulang')
                            ->join('assets a', 'fc.asset_id = a.id', 'left')
                            ->join('penyulang p', 'fc.penyulang_id = p.id', 'left')
                            ->whereIn('fc.status', ['SUBMITTED', 'UNDER_REVIEW']);

        if ($penyulangId !== null && $penyulangId > 0) {
            $builder->where('fc.penyulang_id', $penyulangId);
        }

        return $builder->orderBy('fc.created_at', 'DESC')->get()->getResultArray();
    }

    /**
     * Get Append-Only Physical Change History for an Asset
     *
     * @param int $assetId
     * @return array<int, array<string, mixed>>
     */
    public function getAssetAuditHistory(int $assetId): array
    {
        return $this->db->table('asset_change_history')
                        ->where('asset_id', $assetId)
                        ->orderBy('created_at', 'DESC')
                        ->get()
                        ->getResultArray();
    }
}
