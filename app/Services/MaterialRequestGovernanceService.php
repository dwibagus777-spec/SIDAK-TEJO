<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Material Request Governance & Technical Approval Service (MR-01 Phase 2)
 *
 * Tahap B: Quantity & Evidence-Based Logistics Suite
 * Alur: Draft Rekomendasi -> Technical Review (SPV) -> Management Approval (Asman/Manajer) -> SPM Voucher
 * Aturan Mutlak: Quantity First, Zero Rupiah/Price, Zero Auto-ERP/Stock Deduction.
 */
class MaterialRequestGovernanceService
{
    protected BaseConnection $db;
    protected ShutdownWorkPlanningService $workPlanningService;

    protected string $packageRegistryPath;
    protected string $techReviewRegistryPath;
    protected string $approvalRegistryPath;
    protected string $voucherRegistryPath;

    public const MODEL_VERSION = 'MATERIAL_REQUEST_GOVERNANCE_MODEL_v1.0';

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->workPlanningService = new ShutdownWorkPlanningService($this->db);

        $this->packageRegistryPath    = WRITEPATH . 'audits/mr01_material_request_package_registry.json';
        $this->techReviewRegistryPath = WRITEPATH . 'audits/mr01_technical_review_registry.json';
        $this->approvalRegistryPath   = WRITEPATH . 'audits/mr01_management_approval_registry.json';
        $this->voucherRegistryPath    = WRITEPATH . 'audits/mr01_warehouse_voucher_registry.json';

        $this->initializeGroupHRegistries();
    }

    /**
     * Initialize Group H Registries.
     */
    public function initializeGroupHRegistries(): void
    {
        $now = date('Y-m-d H:i:s T');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        // 1. Material Request Package Registry
        if (!file_exists($this->packageRegistryPath)) {
            $doc = [
                'registry_id'    => 'MR01_MATERIAL_REQUEST_PACKAGE_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'total_packages' => 0,
                'packages'       => [],
            ];
            file_put_contents($this->packageRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 2. Technical Review Registry
        if (!file_exists($this->techReviewRegistryPath)) {
            $doc = [
                'registry_id'    => 'MR01_TECHNICAL_REVIEW_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'total_reviews'  => 0,
                'reviews'        => [],
            ];
            file_put_contents($this->techReviewRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 3. Management Approval Registry
        if (!file_exists($this->approvalRegistryPath)) {
            $doc = [
                'registry_id'    => 'MR01_MANAGEMENT_APPROVAL_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'total_approvals'=> 0,
                'approvals'      => [],
            ];
            file_put_contents($this->approvalRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 4. Warehouse Voucher Registry
        if (!file_exists($this->voucherRegistryPath)) {
            $doc = [
                'registry_id'    => 'MR01_WAREHOUSE_VOUCHER_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'total_vouchers' => 0,
                'vouchers'       => [],
            ];
            file_put_contents($this->voucherRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Stage 1: Ingest Work Plan & Create Draft Official Material Request Package.
     */
    public function createMaterialRequestPackage(string $planId, array $initiator): array
    {
        $planDetail = $this->workPlanningService->getWorkPlanDetail($planId);
        if (!$planDetail['success']) {
            return ['success' => false, 'error' => "Work Plan ID {$planId} not found in Group G registry."];
        }

        $summary = $planDetail['plan_summary'];
        $requestNo = $summary['request_no'] ?? ('REQ-MAT-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
        $now = date('Y-m-d H:i:s');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        // Extract Material Line Items
        $materialItems = [];
        foreach ($summary['aggregated_materials'] ?? [] as $m) {
            $code = $m['canonical_material_code'];
            $materialItems[$code] = [
                'canonical_material_code' => $code,
                'material_name'           => $m['official_name'],
                'system_recommended_qty'  => (int)$m['total_quantity'],
                'technical_reviewed_qty'  => (int)$m['total_quantity'], // Default same
                'approved_qty'            => 0,
                'unit'                    => $m['unit'],
                'allocated_assets_count'  => (int)$m['allocated_assets_count'],
                'status'                  => 'PENDING_TECHNICAL_REVIEW',
                'review_notes'            => 'Pending supervisor field validation',
            ];
        }

        $packageRecord = [
            'request_no'              => $requestNo,
            'plan_id'                 => $planId,
            'feeder_id'               => (int)$summary['feeder_id'],
            'feeder_name'             => $summary['feeder_name'],
            'ulp_id'                  => (int)$summary['ulp_id'],
            'work_mode'               => $summary['work_mode'] ?? 'OUTAGE_ISOLATED',
            'affected_sections'       => $summary['affected_section_ids'] ?? [],
            'status'                  => 'DRAFT_RECOMMENDED',
            'stage'                   => 'STAGE_B1_DRAFT_RECOMMENDED',
            'total_material_types'    => count($materialItems),
            'material_items'          => array_values($materialItems),
            'evidence_chains_count'   => count($planDetail['evidence_chains']),
            'evidence_chains'         => $planDetail['evidence_chains'],
            'hierarchical_tree'       => $summary['hierarchical_request_tree'] ?? [],
            'created_by'              => $initiator,
            'created_at'              => $now,
            'created_at_utc'          => $utc,
            'technical_review'        => null,
            'management_approval'     => null,
            'warehouse_voucher'       => null,
            'decision_boundary'       => 'RECOMMENDATION_ONLY (Requires SPV & Management Sign-Off)',
            'action_hash'             => hash('sha256', "PACKAGE|{$requestNo}|{$planId}|{$now}"),
        ];

        // Save to package registry
        $pkgDoc = json_decode(file_get_contents($this->packageRegistryPath), true) ?? ['packages' => []];
        $pkgDoc['packages'][$requestNo] = $packageRecord;
        $pkgDoc['total_packages'] = count($pkgDoc['packages']);
        file_put_contents($this->packageRegistryPath, json_encode($pkgDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'    => true,
            'request_no' => $requestNo,
            'plan_id'    => $planId,
            'package'    => $packageRecord,
        ];
    }

    /**
     * Stage 2: Supervisor Technical Review & Quantity Confirmation / Adjustment.
     */
    public function submitTechnicalReview(string $requestNo, array $reviewedItems, string $supervisorNotes, array $supervisor): array
    {
        $pkgDoc = json_decode(file_get_contents($this->packageRegistryPath), true) ?? ['packages' => []];
        if (!isset($pkgDoc['packages'][$requestNo])) {
            return ['success' => false, 'error' => "Material Request #{$requestNo} not found."];
        }

        $package = &$pkgDoc['packages'][$requestNo];
        if ($package['status'] !== 'DRAFT_RECOMMENDED' && $package['status'] !== 'TECHNICAL_REVIEWED') {
            return ['success' => false, 'error' => "Package #{$requestNo} cannot be reviewed in status {$package['status']}."];
        }

        $now = date('Y-m-d H:i:s');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        // Apply Supervisor Adjustments per line item
        $reviewedLines = [];
        $totalAdjustedQty = 0;
        foreach ($package['material_items'] as &$item) {
            $code = $item['canonical_material_code'];
            $adj = $reviewedItems[$code] ?? null;

            $revQty = $adj !== null ? (int)($adj['reviewed_qty'] ?? $item['system_recommended_qty']) : $item['system_recommended_qty'];
            $itemNotes = $adj['notes'] ?? 'Diverifikasi sesuai kebutuhan temuan';

            $item['technical_reviewed_qty'] = $revQty;
            $item['status'] = 'TECHNICAL_REVIEWED';
            $item['review_notes'] = $itemNotes;

            $reviewedLines[$code] = [
                'canonical_material_code' => $code,
                'material_name'           => $item['material_name'],
                'system_recommended_qty'  => $item['system_recommended_qty'],
                'technical_reviewed_qty'  => $revQty,
                'delta_qty'               => $revQty - $item['system_recommended_qty'],
                'unit'                    => $item['unit'],
                'notes'                   => $itemNotes,
            ];
            $totalAdjustedQty += $revQty;
        }
        unset($item);

        $reviewId = 'REV-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $reviewHash = hash('sha256', "{$reviewId}|{$requestNo}|{$supervisor['supervisor_nip']}|{$totalAdjustedQty}|{$now}");

        $reviewRecord = [
            'review_id'            => $reviewId,
            'request_no'           => $requestNo,
            'supervisor'           => $supervisor,
            'supervisor_notes'     => $supervisorNotes,
            'reviewed_lines'       => array_values($reviewedLines),
            'reviewed_at'          => $now,
            'reviewed_at_utc'      => $utc,
            'review_hash'          => $reviewHash,
            'decision'             => 'VERIFIED_AND_SUBMITTED_FOR_MANAGEMENT_APPROVAL',
        ];

        // Update Package
        $package['status'] = 'TECHNICAL_REVIEWED';
        $package['stage']  = 'STAGE_B2_TECHNICAL_REVIEWED';
        $package['technical_review'] = $reviewRecord;

        file_put_contents($this->packageRegistryPath, json_encode($pkgDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Update Review Registry
        $revDoc = json_decode(file_get_contents($this->techReviewRegistryPath), true) ?? ['reviews' => []];
        $revDoc['reviews'][$reviewId] = $reviewRecord;
        $revDoc['total_reviews'] = count($revDoc['reviews']);
        file_put_contents($this->techReviewRegistryPath, json_encode($revDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'       => true,
            'request_no'    => $requestNo,
            'review_id'     => $reviewId,
            'review_hash'   => $reviewHash,
            'package'       => $package,
        ];
    }

    /**
     * Stage 3: Management Official Approval (Asman Jaringan / Manajer UP3).
     */
    public function approveMaterialRequest(string $requestNo, string $decision, string $managementNotes, array $approver): array
    {
        $pkgDoc = json_decode(file_get_contents($this->packageRegistryPath), true) ?? ['packages' => []];
        if (!isset($pkgDoc['packages'][$requestNo])) {
            return ['success' => false, 'error' => "Material Request #{$requestNo} not found."];
        }

        $package = &$pkgDoc['packages'][$requestNo];
        if ($package['status'] !== 'TECHNICAL_REVIEWED') {
            return ['success' => false, 'error' => "Package #{$requestNo} must be in TECHNICAL_REVIEWED status before management approval."];
        }

        $now = date('Y-m-d H:i:s');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        $approvalId = 'APP-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);

        if ($decision === 'APPROVED') {
            // Lock approved quantities
            $approvedLines = [];
            foreach ($package['material_items'] as &$item) {
                $item['approved_qty'] = $item['technical_reviewed_qty'];
                $item['status'] = 'OFFICIALLY_APPROVED';

                $approvedLines[$item['canonical_material_code']] = [
                    'canonical_material_code' => $item['canonical_material_code'],
                    'material_name'           => $item['material_name'],
                    'approved_qty'            => $item['approved_qty'],
                    'unit'                    => $item['unit'],
                ];
            }
            unset($item);

            $approvalHash = hash('sha256', "{$approvalId}|{$requestNo}|{$approver['approver_nip']}|APPROVED|{$now}");

            $approvalRecord = [
                'approval_id'       => $approvalId,
                'request_no'        => $requestNo,
                'approver'          => $approver,
                'decision'          => 'OFFICIALLY_APPROVED',
                'management_notes'  => $managementNotes,
                'approved_lines'    => array_values($approvedLines),
                'approved_at'       => $now,
                'approved_at_utc'   => $utc,
                'approval_hash'     => $approvalHash,
            ];

            $package['status'] = 'OFFICIALLY_APPROVED';
            $package['stage']  = 'STAGE_B3_OFFICIALLY_APPROVED';
            $package['management_approval'] = $approvalRecord;

            // Automatically generate Voucher / SPM ready for warehouse pickup
            $voucherRecord = $this->generateWarehouseVoucher($package, $approvalRecord);
            $package['warehouse_voucher'] = $voucherRecord;
            $package['stage'] = 'STAGE_B4_SPM_ISSUED';
        } else {
            $approvalHash = hash('sha256', "{$approvalId}|{$requestNo}|{$approver['approver_nip']}|REJECTED|{$now}");

            $approvalRecord = [
                'approval_id'       => $approvalId,
                'request_no'        => $requestNo,
                'approver'          => $approver,
                'decision'          => 'REJECTED',
                'management_notes'  => $managementNotes,
                'approved_at'       => $now,
                'approved_at_utc'   => $utc,
                'approval_hash'     => $approvalHash,
            ];

            $package['status'] = 'REJECTED_BY_MANAGEMENT';
            $package['stage']  = 'STAGE_B3_REJECTED';
            $package['management_approval'] = $approvalRecord;
        }

        file_put_contents($this->packageRegistryPath, json_encode($pkgDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Save into Approval Registry
        $appDoc = json_decode(file_get_contents($this->approvalRegistryPath), true) ?? ['approvals' => []];
        $appDoc['approvals'][$approvalId] = $approvalRecord;
        $appDoc['total_approvals'] = count($appDoc['approvals']);
        file_put_contents($this->approvalRegistryPath, json_encode($appDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'       => true,
            'request_no'    => $requestNo,
            'approval_id'   => $approvalId,
            'decision'      => $decision,
            'approval_hash' => $approvalHash,
            'package'       => $package,
        ];
    }

    /**
     * Stage 4: Generate Surat Permintaan Material (SPM) / Warehouse Voucher.
     */
    protected function generateWarehouseVoucher(array $package, array $approvalRecord): array
    {
        $spmNo = 'SPM-JTM-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $now = date('Y-m-d H:i:s');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        $spmHash = hash('sha256', "{$spmNo}|{$package['request_no']}|{$approvalRecord['approval_hash']}|{$now}");

        $voucher = [
            'spm_number'            => $spmNo,
            'request_no'            => $package['request_no'],
            'plan_id'               => $package['plan_id'],
            'feeder_name'           => $package['feeder_name'],
            'work_mode'             => $package['work_mode'],
            'status'                => 'READY_FOR_WAREHOUSE_PICKUP',
            'issued_at'             => $now,
            'issued_at_utc'         => $utc,
            'approval_hash'         => $approvalRecord['approval_hash'],
            'spm_hash'              => $spmHash,
            'authorized_by'         => $approvalRecord['approver']['approver_name'] ?? 'MANAJER_UP3',
            'authorized_nip'        => $approvalRecord['approver']['approver_nip'] ?? '198205102006041002',
            'warehouse_destination' => 'GUDANG_UP3_SIDOARJO',
            'material_items'        => $package['material_items'],
            'evidence_chains_count' => count($package['evidence_chains']),
            'hierarchical_tree'     => $package['hierarchical_tree'],
            'governance_mode'       => 'QUANTITY_FIRST_PHYSICAL_PICKUP_ONLY (Zero Auto-Deduction)',
        ];

        // Save into Voucher Registry
        $vDoc = json_decode(file_get_contents($this->voucherRegistryPath), true) ?? ['vouchers' => []];
        $vDoc['vouchers'][$spmNo] = $voucher;
        $vDoc['total_vouchers'] = count($vDoc['vouchers']);
        file_put_contents($this->voucherRegistryPath, json_encode($vDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $voucher;
    }

    /**
     * Get Package Detail.
     */
    public function getPackageDetail(string $requestNo): array
    {
        $pkgDoc = json_decode(file_get_contents($this->packageRegistryPath), true) ?? ['packages' => []];
        if (!isset($pkgDoc['packages'][$requestNo])) {
            return ['success' => false, 'error' => "Material Request #{$requestNo} not found."];
        }

        return [
            'success' => true,
            'package' => $pkgDoc['packages'][$requestNo],
        ];
    }

    /**
     * Get All Material Request Packages.
     */
    public function listAllPackages(): array
    {
        $pkgDoc = json_decode(file_get_contents($this->packageRegistryPath), true) ?? ['packages' => []];
        return [
            'success'        => true,
            'total_packages' => count($pkgDoc['packages'] ?? []),
            'packages'       => array_values($pkgDoc['packages'] ?? []),
        ];
    }

    /**
     * Get Governance Ecosystem Summary for MR-01.
     */
    public function getGovernanceSummary(): array
    {
        $pkgDoc = json_decode(file_get_contents($this->packageRegistryPath), true) ?? ['packages' => []];
        $revDoc = json_decode(file_get_contents($this->techReviewRegistryPath), true) ?? ['reviews' => []];
        $appDoc = json_decode(file_get_contents($this->approvalRegistryPath), true) ?? ['approvals' => []];
        $vDoc   = json_decode(file_get_contents($this->voucherRegistryPath), true) ?? ['vouchers' => []];

        return [
            'success'             => true,
            'model_version'       => self::MODEL_VERSION,
            'logistics_stage'     => 'STAGE_B_MATERIAL_REQUEST_GOVERNANCE',
            'strategy'            => 'QUANTITY_AND_EVIDENCE_FIRST',
            'total_packages'      => count($pkgDoc['packages'] ?? []),
            'total_reviews'       => count($revDoc['reviews'] ?? []),
            'total_approvals'     => count($appDoc['approvals'] ?? []),
            'total_spm_vouchers'  => count($vDoc['vouchers'] ?? []),
            'price_master_status' => 'FROZEN_DEFERRED_TO_STAGE_C',
            'zero_auto_deduction' => true,
            'decision_boundary'   => 'HUMAN_MANAGEMENT_APPROVAL_FINAL',
        ];
    }
}
