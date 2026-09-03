<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * MR-01 Phase 3C: Material Recap & Reporting Service (Read-Only Proof Gate)
 *
 * Single Source of Truth for aggregating structured material demand from `temuan_materials`.
 *
 * Guarantees:
 * - Read-Only: Strictly ZERO operational data mutations.
 * - Primary Source: Exclusively `temuan_materials` (temuan.material legacy text is excluded).
 * - Identity Grouping: Grouped by `material_id` + `unit_snapshot` with historical snapshot labels.
 * - Unit Safety: Unlike units are never summed together; flags UNIT_VARIANCE when discovered.
 * - Join Multiplication Firewall: All joins are strictly many-to-one; 1 tx contributes exactly once.
 * - Multi-Level Reconciliation: SUM(detail) == SUM(section) == SUM(penyulang) == SUM(ulp) == SUM(global).
 * - Period Authority: Uses `temuan.tanggal_temuan` (CR-06 protected business date).
 * - Zero Financial / Cost / Procurement / Warehouse Pollution.
 */
class MaterialRecapService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Generate multi-level recap and reconciliation for material requirements.
     *
     * @param array $filters [
     *   'ulp_id' => int,
     *   'penyulang_id' => int,
     *   'section_id' => int,
     *   'material_id' => int,
     *   'start_date' => string (Y-m-d),
     *   'end_date' => string (Y-m-d),
     * ]
     * @param int|null $userUlpIdScope User's restricted ULP ID (null if unrestricted)
     * @return array Standardized reporting payload
     */
    public function getRecap(array $filters = [], ?int $userUlpIdScope = null): array
    {
        // 1. Enforce Role-Based Scoping
        if ($userUlpIdScope !== null) {
            $filters['ulp_id'] = $userUlpIdScope;
        }

        // 2. Validate Cross-Scope Relations (Anti-Leakage)
        $validation = $this->validateFilterScope($filters);
        if (!$validation['valid']) {
            return [
                'status'  => 'INVALID_FILTER',
                'message' => $validation['message'],
                'errors'  => $validation['errors'],
            ];
        }

        // 3. Query Bounded Base Transaction Rows (Strictly 1-to-1 Joins to Prevent Multiplication)
        $baseRows = $this->fetchBaseTransactions($filters);

        if (empty($baseRows)) {
            return [
                'status'         => 'SUCCESS',
                'message'        => 'BELUM ADA TRANSAKSI MATERIAL PADA FILTER TERPILIH',
                'filters'        => $filters,
                'kpi'            => [
                    'total_material_lines'  => 0,
                    'total_material_types'  => 0,
                    'total_findings'        => 0,
                    'total_assets'          => 0,
                    'totals_by_unit'        => [],
                ],
                'global_recap'   => [],
                'ulp_recap'      => [],
                'penyulang_recap'=> [],
                'section_recap'  => [],
                'detail_rows'    => [],
                'reconciliation' => [
                    'status' => 'BALANCED',
                    'checks' => [],
                ],
                'data_quality'   => [
                    'unit_variances'        => [],
                    'orphans'               => [],
                    'missing_snapshots'     => [],
                    'governance_anomalies'  => [],
                ],
            ];
        }

        // 4. Data Quality Auditing (Read-Only)
        $dataQuality = $this->auditDataQuality($baseRows);

        // 5. Build Aggregations
        $globalRecap    = $this->aggregateGlobal($baseRows);
        $ulpRecap       = $this->aggregateByUlp($baseRows);
        $penyulangRecap = $this->aggregateByPenyulang($baseRows);
        $sectionRecap   = $this->aggregateBySection($baseRows);
        $totalsByUnit   = $this->calculateTotalsByUnit($baseRows);

        // 6. Mathematical Reconciliation Invariant Across All Levels
        $reconciliation = $this->reconcileHierarchyTotals(
            $baseRows,
            $sectionRecap,
            $penyulangRecap,
            $ulpRecap,
            $globalRecap,
            $totalsByUnit
        );

        // 7. KPI Metrics (Strict Separation of Lines vs Distinct Types vs Totals by Unit)
        $distinctFindings = [];
        $distinctAssets   = [];
        $distinctMaterials= [];
        foreach ($baseRows as $r) {
            $distinctFindings[$r['temuan_id']] = true;
            $distinctAssets[$r['asset_id']] = true;
            $distinctMaterials[$r['material_id']] = true;
        }

        $kpi = [
            'total_material_lines' => count($baseRows),
            'total_material_types' => count($distinctMaterials),
            'total_findings'       => count($distinctFindings),
            'total_assets'         => count($distinctAssets),
            'totals_by_unit'       => $totalsByUnit,
        ];

        return [
            'status'         => 'SUCCESS',
            'message'        => 'Rekapitulasi material berhasil dihitung.',
            'filters'        => $filters,
            'kpi'            => $kpi,
            'global_recap'   => $globalRecap,
            'ulp_recap'      => $ulpRecap,
            'penyulang_recap'=> $penyulangRecap,
            'section_recap'  => $sectionRecap,
            'detail_rows'    => $baseRows,
            'reconciliation' => $reconciliation,
            'data_quality'   => $dataQuality,
        ];
    }

    /**
     * Validates filter hierarchy cross-scoping (Section -> Penyulang -> ULP).
     */
    private function validateFilterScope(array $filters): array
    {
        $ulpId       = isset($filters['ulp_id']) && is_numeric($filters['ulp_id']) ? (int)$filters['ulp_id'] : null;
        $penyulangId = isset($filters['penyulang_id']) && is_numeric($filters['penyulang_id']) ? (int)$filters['penyulang_id'] : null;
        $sectionId   = isset($filters['section_id']) && is_numeric($filters['section_id']) ? (int)$filters['section_id'] : null;

        if ($sectionId !== null && $penyulangId !== null) {
            $sec = $this->db->table('sections')->where('id', $sectionId)->get()->getRowArray();
            if ($sec && (int)$sec['penyulang_id'] !== $penyulangId) {
                return [
                    'valid'   => false,
                    'message' => 'Filter Section tidak sesuai dengan Penyulang yang dipilih.',
                    'errors'  => ['section_id' => 'Cross-scope Section to Penyulang mismatch'],
                ];
            }
        }

        if ($penyulangId !== null && $ulpId !== null) {
            $pyl = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
            if ($pyl && (int)$pyl['ulp_id'] !== $ulpId) {
                return [
                    'valid'   => false,
                    'message' => 'Filter Penyulang tidak sesuai dengan ULP yang dipilih.',
                    'errors'  => ['penyulang_id' => 'Cross-scope Penyulang to ULP mismatch'],
                ];
            }
        }

        // Date format validation
        if (!empty($filters['start_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['start_date'])) {
            return [
                'valid'   => false,
                'message' => 'Format tanggal awal tidak valid (harus YYYY-MM-DD).',
                'errors'  => ['start_date' => 'Invalid date format'],
            ];
        }
        if (!empty($filters['end_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['end_date'])) {
            return [
                'valid'   => false,
                'message' => 'Format tanggal akhir tidak valid (harus YYYY-MM-DD).',
                'errors'  => ['end_date' => 'Invalid date format'],
            ];
        }

        return ['valid' => true];
    }

    /**
     * Bounded query joining temuan_materials to dimension tables.
     * Guaranteed ZERO join multiplication (all joins are many-to-one).
     */
    private function fetchBaseTransactions(array $filters): array
    {
        $builder = $this->db->table('temuan_materials tm')
            ->select('
                tm.id AS transaction_id,
                tm.temuan_id,
                tm.asset_id,
                tm.construction_type_id,
                tm.material_id,
                tm.canonical_code_snapshot,
                tm.canonical_name_snapshot,
                tm.unit_snapshot,
                tm.quantity,
                tm.justification_note,
                tm.source_mode,
                tm.created_at AS transaction_created_at,
                t.nomor_temuan,
                t.tanggal_temuan,
                t.ulp_id,
                u.nama_ulp,
                u.kode_ulp,
                t.penyulang_id,
                p.nama_penyulang,
                p.kode_penyulang,
                t.section_id,
                s.nama_section,
                a.kode_asset,
                a.nama_asset,
                ct.construction_code,
                ct.construction_name
            ');

        $joinCondition = 'tm.temuan_id = t.id';
        if ($this->db->fieldExists('deleted_at', 'temuan')) {
            $joinCondition .= ' AND (t.deleted_at IS NULL OR t.deleted_at = "")';
        }

        $builder->join('temuan t', $joinCondition, 'inner')
            ->join('ulps u', 't.ulp_id = u.id', 'left')
            ->join('penyulang p', 't.penyulang_id = p.id', 'left')
            ->join('sections s', 't.section_id = s.id', 'left')
            ->join('assets a', 'tm.asset_id = a.id', 'left')
            ->join('construction_types ct', 'tm.construction_type_id = ct.id', 'left');

        // Apply filters
        if (!empty($filters['ulp_id'])) {
            $builder->where('t.ulp_id', (int)$filters['ulp_id']);
        }
        if (!empty($filters['penyulang_id'])) {
            $builder->where('t.penyulang_id', (int)$filters['penyulang_id']);
        }
        if (!empty($filters['section_id'])) {
            $builder->where('t.section_id', (int)$filters['section_id']);
        }
        if (!empty($filters['material_id'])) {
            $builder->where('tm.material_id', (int)$filters['material_id']);
        }

        // CR-06 Protected: Always filter by business date (tanggal_temuan), never created_at!
        if (!empty($filters['start_date'])) {
            $builder->where('t.tanggal_temuan >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('t.tanggal_temuan <=', $filters['end_date']);
        }

        // Deterministic Sorting
        $builder->orderBy('u.nama_ulp', 'ASC')
                ->orderBy('p.nama_penyulang', 'ASC')
                ->orderBy('s.nama_section', 'ASC')
                ->orderBy('tm.canonical_name_snapshot', 'ASC')
                ->orderBy('tm.id', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Aggregation Level A: Global Material Requirement
     * Grouping key: material_id + unit_snapshot
     */
    private function aggregateGlobal(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $matId = (int)$r['material_id'];
            $unit  = $r['unit_snapshot'] ?: 'UNKNOWN';
            $key   = "{$matId}|{$unit}";

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'material_id'             => $matId,
                    'canonical_code_snapshot' => $r['canonical_code_snapshot'],
                    'canonical_name_snapshot' => $r['canonical_name_snapshot'],
                    'unit_snapshot'           => $unit,
                    'total_quantity'          => 0.0,
                    'finding_count'           => 0,
                    'asset_count'             => 0,
                    '_findings'               => [],
                    '_assets'                 => [],
                ];
            }

            $groups[$key]['total_quantity'] += (float)$r['quantity'];
            $groups[$key]['_findings'][$r['temuan_id']] = true;
            $groups[$key]['_assets'][$r['asset_id']] = true;
        }

        // Check for unit variances across the same material_id
        $materialUnits = [];
        foreach ($groups as $g) {
            $materialUnits[$g['material_id']][] = $g['unit_snapshot'];
        }

        $result = [];
        foreach ($groups as $g) {
            $matId = $g['material_id'];
            $hasVariance = count($materialUnits[$matId] ?? []) > 1;

            $result[] = [
                'material_id'             => $matId,
                'canonical_code_snapshot' => $g['canonical_code_snapshot'],
                'canonical_name_snapshot' => $g['canonical_name_snapshot'],
                'unit_snapshot'           => $g['unit_snapshot'],
                'total_quantity'          => number_format($g['total_quantity'], 2, '.', ''),
                'finding_count'           => count($g['_findings']),
                'asset_count'             => count($g['_assets']),
                'has_unit_variance'       => $hasVariance,
            ];
        }

        // Sort deterministically: Name -> ID -> Unit
        usort($result, function ($a, $b) {
            $cmp = strcmp($a['canonical_name_snapshot'], $b['canonical_name_snapshot']);
            if ($cmp !== 0) return $cmp;
            $cmp2 = $a['material_id'] <=> $b['material_id'];
            if ($cmp2 !== 0) return $cmp2;
            return strcmp($a['unit_snapshot'], $b['unit_snapshot']);
        });

        return $result;
    }

    /**
     * Aggregation Level B: ULP Material Recap
     */
    private function aggregateByUlp(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $ulpId = (int)$r['ulp_id'];
            $matId = (int)$r['material_id'];
            $unit  = $r['unit_snapshot'] ?: 'UNKNOWN';
            $key   = "{$ulpId}|{$matId}|{$unit}";

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'ulp_id'                  => $ulpId,
                    'nama_ulp'                => $r['nama_ulp'] ?? 'ULP #' . $ulpId,
                    'kode_ulp'                => $r['kode_ulp'] ?? '',
                    'material_id'             => $matId,
                    'canonical_code_snapshot' => $r['canonical_code_snapshot'],
                    'canonical_name_snapshot' => $r['canonical_name_snapshot'],
                    'unit_snapshot'           => $unit,
                    'total_quantity'          => 0.0,
                    '_findings'               => [],
                ];
            }

            $groups[$key]['total_quantity'] += (float)$r['quantity'];
            $groups[$key]['_findings'][$r['temuan_id']] = true;
        }

        $result = [];
        foreach ($groups as $g) {
            $result[] = [
                'ulp_id'                  => $g['ulp_id'],
                'nama_ulp'                => $g['nama_ulp'],
                'kode_ulp'                => $g['kode_ulp'],
                'material_id'             => $g['material_id'],
                'canonical_code_snapshot' => $g['canonical_code_snapshot'],
                'canonical_name_snapshot' => $g['canonical_name_snapshot'],
                'unit_snapshot'           => $g['unit_snapshot'],
                'total_quantity'          => number_format($g['total_quantity'], 2, '.', ''),
                'finding_count'           => count($g['_findings']),
            ];
        }

        usort($result, function ($a, $b) {
            $cmp = strcmp($a['nama_ulp'], $b['nama_ulp']);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['canonical_name_snapshot'], $b['canonical_name_snapshot']);
        });

        return $result;
    }

    /**
     * Aggregation Level C: Penyulang Material Recap
     */
    private function aggregateByPenyulang(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $ulpId = (int)$r['ulp_id'];
            $pylId = (int)$r['penyulang_id'];
            $matId = (int)$r['material_id'];
            $unit  = $r['unit_snapshot'] ?: 'UNKNOWN';
            $key   = "{$pylId}|{$matId}|{$unit}";

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'ulp_id'                  => $ulpId,
                    'nama_ulp'                => $r['nama_ulp'] ?? 'ULP #' . $ulpId,
                    'penyulang_id'            => $pylId,
                    'nama_penyulang'          => $r['nama_penyulang'] ?? 'Penyulang #' . $pylId,
                    'kode_penyulang'          => $r['kode_penyulang'] ?? '',
                    'material_id'             => $matId,
                    'canonical_code_snapshot' => $r['canonical_code_snapshot'],
                    'canonical_name_snapshot' => $r['canonical_name_snapshot'],
                    'unit_snapshot'           => $unit,
                    'total_quantity'          => 0.0,
                    '_findings'               => [],
                ];
            }

            $groups[$key]['total_quantity'] += (float)$r['quantity'];
            $groups[$key]['_findings'][$r['temuan_id']] = true;
        }

        $result = [];
        foreach ($groups as $g) {
            $result[] = [
                'ulp_id'                  => $g['ulp_id'],
                'nama_ulp'                => $g['nama_ulp'],
                'penyulang_id'            => $g['penyulang_id'],
                'nama_penyulang'          => $g['nama_penyulang'],
                'kode_penyulang'          => $g['kode_penyulang'],
                'material_id'             => $g['material_id'],
                'canonical_code_snapshot' => $g['canonical_code_snapshot'],
                'canonical_name_snapshot' => $g['canonical_name_snapshot'],
                'unit_snapshot'           => $g['unit_snapshot'],
                'total_quantity'          => number_format($g['total_quantity'], 2, '.', ''),
                'finding_count'           => count($g['_findings']),
            ];
        }

        usort($result, function ($a, $b) {
            $cmp = strcmp($a['nama_penyulang'], $b['nama_penyulang']);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['canonical_name_snapshot'], $b['canonical_name_snapshot']);
        });

        return $result;
    }

    /**
     * Aggregation Level D: Section Material Recap
     */
    private function aggregateBySection(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $secId = (int)$r['section_id'];
            $matId = (int)$r['material_id'];
            $unit  = $r['unit_snapshot'] ?: 'UNKNOWN';
            $key   = "{$secId}|{$matId}|{$unit}";

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'ulp_id'                  => (int)$r['ulp_id'],
                    'nama_ulp'                => $r['nama_ulp'] ?? '',
                    'penyulang_id'            => (int)$r['penyulang_id'],
                    'nama_penyulang'          => $r['nama_penyulang'] ?? '',
                    'section_id'              => $secId,
                    'nama_section'            => $r['nama_section'] ?? 'Section #' . $secId,
                    'material_id'             => $matId,
                    'canonical_code_snapshot' => $r['canonical_code_snapshot'],
                    'canonical_name_snapshot' => $r['canonical_name_snapshot'],
                    'unit_snapshot'           => $unit,
                    'total_quantity'          => 0.0,
                    '_findings'               => [],
                ];
            }

            $groups[$key]['total_quantity'] += (float)$r['quantity'];
            $groups[$key]['_findings'][$r['temuan_id']] = true;
        }

        $result = [];
        foreach ($groups as $g) {
            $result[] = [
                'ulp_id'                  => $g['ulp_id'],
                'nama_ulp'                => $g['nama_ulp'],
                'penyulang_id'            => $g['penyulang_id'],
                'nama_penyulang'          => $g['nama_penyulang'],
                'section_id'              => $g['section_id'],
                'nama_section'            => $g['nama_section'],
                'material_id'             => $g['material_id'],
                'canonical_code_snapshot' => $g['canonical_code_snapshot'],
                'canonical_name_snapshot' => $g['canonical_name_snapshot'],
                'unit_snapshot'           => $g['unit_snapshot'],
                'total_quantity'          => number_format($g['total_quantity'], 2, '.', ''),
                'finding_count'           => count($g['_findings']),
            ];
        }

        usort($result, function ($a, $b) {
            $cmp = strcmp($a['nama_section'], $b['nama_section']);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['canonical_name_snapshot'], $b['canonical_name_snapshot']);
        });

        return $result;
    }

    /**
     * Calculates total quantities partitioned strictly per unit.
     * Never combines unlike units!
     */
    private function calculateTotalsByUnit(array $rows): array
    {
        $totals = [];
        foreach ($rows as $r) {
            $unit = $r['unit_snapshot'] ?: 'UNKNOWN';
            $totals[$unit] = ($totals[$unit] ?? 0.0) + (float)$r['quantity'];
        }

        ksort($totals);
        $formatted = [];
        foreach ($totals as $u => $q) {
            $formatted[$u] = number_format($q, 2, '.', '');
        }

        return $formatted;
    }

    /**
     * Mathematical reconciliation proving:
     * SUM(detail) == SUM(section) == SUM(penyulang) == SUM(ulp) == SUM(global) per unit.
     */
    private function reconcileHierarchyTotals(
        array $baseRows,
        array $sectionRecap,
        array $penyulangRecap,
        array $ulpRecap,
        array $globalRecap,
        array $expectedTotalsByUnit
    ): array {
        $checks = [];
        $isBalanced = true;

        foreach ($expectedTotalsByUnit as $unit => $expectedQtyStr) {
            $expected = (float)$expectedQtyStr;

            $sumDetail = 0.0;
            foreach ($baseRows as $r) {
                if (($r['unit_snapshot'] ?: 'UNKNOWN') === $unit) {
                    $sumDetail += (float)$r['quantity'];
                }
            }

            $sumSection = 0.0;
            foreach ($sectionRecap as $r) {
                if ($r['unit_snapshot'] === $unit) {
                    $sumSection += (float)$r['total_quantity'];
                }
            }

            $sumPenyulang = 0.0;
            foreach ($penyulangRecap as $r) {
                if ($r['unit_snapshot'] === $unit) {
                    $sumPenyulang += (float)$r['total_quantity'];
                }
            }

            $sumUlp = 0.0;
            foreach ($ulpRecap as $r) {
                if ($r['unit_snapshot'] === $unit) {
                    $sumUlp += (float)$r['total_quantity'];
                }
            }

            $sumGlobal = 0.0;
            foreach ($globalRecap as $r) {
                if ($r['unit_snapshot'] === $unit) {
                    $sumGlobal += (float)$r['total_quantity'];
                }
            }

            $diff1 = abs($sumDetail - $expected);
            $diff2 = abs($sumSection - $expected);
            $diff3 = abs($sumPenyulang - $expected);
            $diff4 = abs($sumUlp - $expected);
            $diff5 = abs($sumGlobal - $expected);

            $unitBalanced = ($diff1 < 0.001 && $diff2 < 0.001 && $diff3 < 0.001 && $diff4 < 0.001 && $diff5 < 0.001);
            if (!$unitBalanced) {
                $isBalanced = false;
            }

            $checks[$unit] = [
                'expected'      => number_format($expected, 2, '.', ''),
                'sum_detail'    => number_format($sumDetail, 2, '.', ''),
                'sum_section'   => number_format($sumSection, 2, '.', ''),
                'sum_penyulang' => number_format($sumPenyulang, 2, '.', ''),
                'sum_ulp'       => number_format($sumUlp, 2, '.', ''),
                'sum_global'    => number_format($sumGlobal, 2, '.', ''),
                'balanced'      => $unitBalanced,
            ];
        }

        return [
            'status' => $isBalanced ? 'BALANCED' : 'DISCREPANCY_DETECTED',
            'checks' => $checks,
        ];
    }

    /**
     * Read-Only Data Quality Audit to discover anomalies without silent repair.
     */
    private function auditDataQuality(array $rows): array
    {
        $unitVariances       = [];
        $orphans             = [];
        $missingSnapshots    = [];
        $governanceAnomalies = [];

        $matUnits = [];
        foreach ($rows as $r) {
            $matId = (int)$r['material_id'];
            $unit  = $r['unit_snapshot'] ?: 'UNKNOWN';
            $matUnits[$matId][$unit] = true;

            if (empty($r['canonical_code_snapshot']) || empty($r['canonical_name_snapshot']) || empty($r['unit_snapshot'])) {
                $missingSnapshots[] = [
                    'transaction_id' => $r['transaction_id'],
                    'temuan_id'      => $r['temuan_id'],
                    'material_id'    => $matId,
                ];
            }

            if (empty($r['nama_asset']) || empty($r['section_id'])) {
                $orphans[] = [
                    'transaction_id' => $r['transaction_id'],
                    'temuan_id'      => $r['temuan_id'],
                    'asset_id'       => $r['asset_id'],
                ];
            }
        }

        foreach ($matUnits as $matId => $units) {
            if (count($units) > 1) {
                $unitVariances[] = [
                    'material_id' => $matId,
                    'units_found' => array_keys($units),
                ];
            }
        }

        return [
            'unit_variances'       => $unitVariances,
            'orphans'              => $orphans,
            'missing_snapshots'    => $missingSnapshots,
            'governance_anomalies' => $governanceAnomalies,
        ];
    }
}
