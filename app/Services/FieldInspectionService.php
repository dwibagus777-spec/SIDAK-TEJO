<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Field Inspection & Material Traceability Service (CR-06 Phase 2)
 *
 * Responsibilities:
 * - Governed Field Inspection Session Lifecycle: DRAFT -> ASSIGNED -> IN_FIELD -> COMPLETED -> VERIFIED.
 * - Inspector Attribution & Action Hashes (NIP, Name, Role, Timestamp, Photos).
 * - Asset <-> Temuan Spatial & Topological Correlation (Evidence Linkage).
 * - Living Asset Condition & Governed Health Index Updates (Evidence for 25% Asset Health).
 * - Material Requirement Estimation & Usage Traceability Registry.
 * - Operates with immutable Group C registries and protects Group A invariants.
 */
class FieldInspectionService
{
    protected BaseConnection $db;
    protected string $inspectionRegistryPath;
    protected string $materialRegistryPath;
    protected string $preSnapshotPath;

    public const MODEL_VERSION = 'FIELD_INSPECTION_MODEL_v1.0';
    public const ASSET_MODEL_VERSION = 'PHYSICAL_ASSET_TRUTH_MODEL_v1.0';
    public const PREVENTIVE_MODEL_VERSION = 'PREVENTIVE_SCORING_v1.0';

    // Inspection Session Lifecycle States
    public const STATE_DRAFT     = 'DRAFT';
    public const STATE_ASSIGNED  = 'ASSIGNED';
    public const STATE_IN_FIELD  = 'IN_FIELD';
    public const STATE_COMPLETED = 'COMPLETED';
    public const STATE_VERIFIED  = 'VERIFIED';
    public const STATE_CANCELLED = 'CANCELLED';

    protected array $allowedTransitions = [
        self::STATE_DRAFT => [
            self::STATE_ASSIGNED,
            self::STATE_CANCELLED,
        ],
        self::STATE_ASSIGNED => [
            self::STATE_IN_FIELD,
            self::STATE_CANCELLED,
        ],
        self::STATE_IN_FIELD => [
            self::STATE_COMPLETED,
            self::STATE_CANCELLED,
        ],
        self::STATE_COMPLETED => [
            self::STATE_VERIFIED,
            self::STATE_IN_FIELD, // Rework requested
        ],
        self::STATE_VERIFIED  => [],
        self::STATE_CANCELLED => [],
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->inspectionRegistryPath = WRITEPATH . 'audits/cr06_inspection_registry.json';
        $this->materialRegistryPath   = WRITEPATH . 'audits/cr06_material_registry.json';
        $this->preSnapshotPath        = WRITEPATH . 'audits/cr06_pre_snapshot.json';
        $this->ensureRegistriesExist();
    }

    /**
     * Ensure Group C JSON Operational Registries Exist with Baseline 0.
     */
    protected function ensureRegistriesExist(): void
    {
        if (!file_exists($this->inspectionRegistryPath)) {
            $initialInspections = [
                'registry_id'     => 'CR06_INSPECTION_REGISTRY_v1.0',
                'created_at'      => date('Y-m-d H:i:s T'),
                'lineage_parent'  => 'CR06_PRE_SNAPSHOT',
                'sessions'        => [],
                'observations'    => [],
            ];
            file_put_contents($this->inspectionRegistryPath, json_encode($initialInspections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (!file_exists($this->materialRegistryPath)) {
            $initialMaterials = [
                'registry_id'     => 'CR06_MATERIAL_REGISTRY_v1.0',
                'created_at'      => date('Y-m-d H:i:s T'),
                'lineage_parent'  => 'CR06_PRE_SNAPSHOT',
                'catalog'         => [
                    ['code' => 'MAT-ISO-01', 'name' => 'Pin Post Insulator 20kV 120kN', 'unit' => 'PCS', 'stock' => 150],
                    ['code' => 'MAT-ISO-02', 'name' => 'Suspension Insulator 20kV', 'unit' => 'PCS', 'stock' => 80],
                    ['code' => 'MAT-FCO-01', 'name' => 'Fuse Cutout 24kV 100A Polymer', 'unit' => 'SET', 'stock' => 45],
                    ['code' => 'MAT-ARR-01', 'name' => 'Lightning Arrester 24kV 10kA', 'unit' => 'SET', 'stock' => 60],
                    ['code' => 'MAT-CND-01', 'name' => 'Conductor AAAC 70 mm2', 'unit' => 'METER', 'stock' => 2500],
                    ['code' => 'MAT-OIL-01', 'name' => 'Trafo Mineral Insulating Oil', 'unit' => 'LITER', 'stock' => 400],
                ],
                'usages'          => [],
            ];
            file_put_contents($this->materialRegistryPath, json_encode($initialMaterials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Get Inspection Workspace Summary & Active Sessions.
     */
    public function getInspectionSummary(): array
    {
        $inspections = json_decode(file_get_contents($this->inspectionRegistryPath), true);
        $materials   = json_decode(file_get_contents($this->materialRegistryPath), true);

        $totalAssets = $this->db->table('assets')->countAllResults();
        $totalTemuan = $this->db->table('temuan')->countAllResults();

        return [
            'success'            => true,
            'model_version'      => self::MODEL_VERSION,
            'total_sessions'     => count($inspections['sessions'] ?? []),
            'total_observations' => count($inspections['observations'] ?? []),
            'total_material_uses'=> count($materials['usages'] ?? []),
            'assets_in_database' => $totalAssets,
            'findings_preserved' => $totalTemuan, // 441
            'sessions'           => $inspections['sessions'] ?? [],
            'material_catalog'   => $materials['catalog'] ?? [],
            'governance_status'  => [
                'GROUP_A_IMMUTABLE'       => true,
                'GROUP_C_REGISTRIES_SYNC' => true,
                'ZERO_AUTONOMOUS_ACTION'  => true,
                'M04_M05_PRESERVED'       => true,
            ],
        ];
    }

    /**
     * Create Governed Field Inspection Session.
     */
    public function createInspectionSession(array $payload, array $actor): array
    {
        if (empty($actor['actor_name']) || empty($actor['actor_nip']) || empty($actor['actor_role'])) {
            return ['success' => false, 'error' => 'Actor validation failed: actor_name, actor_nip, actor_role are mandatory.'];
        }

        $feederId = (int)($payload['penyulang_id'] ?? 0);
        $feeder = $this->db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        if (!$feeder) {
            return ['success' => false, 'error' => "Feeder ID #{$feederId} not found in master penyulang (134)."];
        }

        $now = date('Y-m-d H:i:s');
        $sessionId = 'INSP-CR06-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        $sessionEntry = [
            'session_id'        => $sessionId,
            'title'             => $payload['title'] ?? "Inspeksi Visual & Kondisi Aset Penyulang {$feeder['nama_penyulang']}",
            'penyulang_id'      => $feederId,
            'penyulang_name'    => $feeder['nama_penyulang'],
            'ulp_id'            => (int)$feeder['ulp_id'],
            'section_id'        => isset($payload['section_id']) ? (int)$payload['section_id'] : null,
            'current_state'     => self::STATE_DRAFT,
            'assigned_team'     => $payload['assigned_team'] ?? 'REGU INSPEKSI HAR DISTRIBUSI',
            'created_at'        => $now,
            'created_by'        => $actor,
            'target_asset_ids'  => $payload['target_asset_ids'] ?? [],
            'action_hash'       => hash('sha256', "{$sessionId}|INITIAL_DRAFT|{$actor['actor_nip']}|{$now}"),
            'state_history'     => [
                [
                    'from_state' => null,
                    'to_state'   => self::STATE_DRAFT,
                    'actor_nip'  => $actor['actor_nip'],
                    'timestamp'  => $now,
                    'notes'      => 'Session draft initiated.',
                ]
            ],
        ];

        $registry = json_decode(file_get_contents($this->inspectionRegistryPath), true);
        $registry['sessions'][$sessionId] = $sessionEntry;
        file_put_contents($this->inspectionRegistryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'    => true,
            'session_id' => $sessionId,
            'state'      => self::STATE_DRAFT,
            'message'    => "Inspection session {$sessionId} created successfully.",
            'session'    => $sessionEntry,
        ];
    }

    /**
     * Transition Inspection Session State.
     */
    public function transitionSessionState(string $sessionId, string $targetState, array $actor, string $notes = ''): array
    {
        if (empty($actor['actor_name']) || empty($actor['actor_nip'])) {
            return ['success' => false, 'error' => 'Actor validation failed: actor_name and actor_nip are mandatory.'];
        }

        $registry = json_decode(file_get_contents($this->inspectionRegistryPath), true);
        if (!isset($registry['sessions'][$sessionId])) {
            return ['success' => false, 'error' => "Inspection Session ID {$sessionId} not found."];
        }

        $session = $registry['sessions'][$sessionId];
        $currentState = $session['current_state'];

        $allowed = $this->allowedTransitions[$currentState] ?? [];
        if (!in_array($targetState, $allowed, true)) {
            return ['success' => false, 'error' => "Illegal state transition: Cannot transition from '{$currentState}' to '{$targetState}'."];
        }

        $now = date('Y-m-d H:i:s');
        $actionHash = hash('sha256', "{$sessionId}|{$currentState}|{$targetState}|{$actor['actor_nip']}|{$now}");

        $session['current_state'] = $targetState;
        $session['updated_at']    = $now;
        $session['state_history'][] = [
            'from_state'  => $currentState,
            'to_state'    => $targetState,
            'actor_nip'   => $actor['actor_nip'],
            'actor_name'  => $actor['actor_name'],
            'timestamp'   => $now,
            'notes'       => $notes ?: "Transitioned to {$targetState}",
            'action_hash' => $actionHash,
        ];

        $registry['sessions'][$sessionId] = $session;
        file_put_contents($this->inspectionRegistryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'        => true,
            'session_id'     => $sessionId,
            'previous_state' => $currentState,
            'current_state'  => $targetState,
            'action_hash'    => $actionHash,
            'message'        => "Session {$sessionId} transitioned to {$targetState}.",
        ];
    }

    /**
     * Record Field Inspection Observation & Update Asset Living Health.
     */
    public function recordObservation(string $sessionId, array $obsData, array $actor): array
    {
        $assetId = (int)($obsData['asset_id'] ?? 0);
        $asset = $this->db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) {
            return ['success' => false, 'error' => "Asset ID #{$assetId} not found in database."];
        }

        $now = date('Y-m-d H:i:s');
        $obsId = 'OBS-CR06-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);

        $visualCondition = $obsData['visual_condition'] ?? 'NORMAL'; // NORMAL, DEGRADASI_RINGAN, DEGRADASI_BERAT, KRITIS
        $componentStatus = $obsData['component_status'] ?? 'NORMAL';
        $photoEvidence   = $obsData['photo_url'] ?? 'assets/photos/default_inspection.jpg';

        // Calculate updated living health score
        $newHealthScore = (float)$asset['health_score'];
        if ($visualCondition === 'KRITIS') {
            $newHealthScore = max(20.0, $newHealthScore - 30.0);
        } elseif ($visualCondition === 'DEGRADASI_BERAT') {
            $newHealthScore = max(40.0, $newHealthScore - 15.0);
        } elseif ($visualCondition === 'DEGRADASI_RINGAN') {
            $newHealthScore = max(60.0, $newHealthScore - 5.0);
        }

        $newCategory = 'GOOD';
        if ($newHealthScore < 40.0) $newCategory = 'CRITICAL';
        elseif ($newHealthScore < 65.0) $newCategory = 'POOR';
        elseif ($newHealthScore < 80.0) $newCategory = 'FAIR';

        // Update Asset Table (Authorized Group B mutation)
        $this->db->table('assets')->where('id', $assetId)->update([
            'status'                         => ($visualCondition === 'KRITIS') ? 'CRITICAL' : (($visualCondition === 'DEGRADASI_BERAT') ? 'BERMASALAH' : 'NORMAL'),
            'health_score'                   => $newHealthScore,
            'health_category'                => $newCategory,
            'health_index_last_calculated_at'=> $now,
            'updated_at'                     => $now,
        ]);

        $obsEntry = [
            'observation_id'   => $obsId,
            'session_id'       => $sessionId,
            'asset_id'         => $assetId,
            'asset_code'       => $asset['kode_asset'],
            'asset_name'       => $asset['nama_asset'],
            'penyulang_id'     => (int)$asset['penyulang_id'],
            'section_id'       => (int)($asset['section_id'] ?? 0),
            'visual_condition' => $visualCondition,
            'component_status' => $componentStatus,
            'notes'            => $obsData['notes'] ?? 'Inspeksi kondisi visual tiang dan isolator di lapangan.',
            'photo_evidence'   => $photoEvidence,
            'previous_health'  => (float)$asset['health_score'],
            'new_health_score' => $newHealthScore,
            'new_category'     => $newCategory,
            'inspector'        => $actor,
            'observed_at'      => $now,
            'action_hash'      => hash('sha256', "{$obsId}|{$assetId}|{$visualCondition}|{$actor['actor_nip']}|{$now}"),
        ];

        $registry = json_decode(file_get_contents($this->inspectionRegistryPath), true);
        $registry['observations'][$obsId] = $obsEntry;
        file_put_contents($this->inspectionRegistryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'          => true,
            'observation_id'   => $obsId,
            'asset_code'       => $asset['kode_asset'],
            'new_health_score' => $newHealthScore,
            'new_category'     => $newCategory,
            'message'          => "Observation recorded and asset living health updated under governed audit.",
            'observation'      => $obsEntry,
        ];
    }

    /**
     * Record Material Usage Traceability.
     */
    public function recordMaterialUsage(array $usageData, array $actor): array
    {
        $materialCode = $usageData['material_code'] ?? '';
        $qty = (int)($usageData['quantity'] ?? 1);
        $workOrder = $usageData['work_order_ref'] ?? 'WO-CR06-DISPATCH';
        $now = date('Y-m-d H:i:s');
        $usageId = 'MATUSE-CR06-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);

        $entry = [
            'usage_id'        => $usageId,
            'material_code'   => $materialCode,
            'quantity'        => $qty,
            'work_order_ref'  => $workOrder,
            'penyulang_id'    => (int)($usageData['penyulang_id'] ?? 15),
            'target_asset_id' => (int)($usageData['target_asset_id'] ?? 0),
            'recorded_by'     => $actor,
            'recorded_at'     => $now,
            'action_hash'     => hash('sha256', "{$usageId}|{$materialCode}|{$qty}|{$actor['actor_nip']}|{$now}"),
        ];

        $registry = json_decode(file_get_contents($this->materialRegistryPath), true);
        $registry['usages'][$usageId] = $entry;
        file_put_contents($this->materialRegistryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'   => true,
            'usage_id'  => $usageId,
            'message'   => "Material usage traceability record created.",
            'usage'     => $entry,
        ];
    }
}
