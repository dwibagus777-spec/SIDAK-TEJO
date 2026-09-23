<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * CR-HOTFIX-04 Block E: MaterialIntelligenceService Read-Model Layer
 *
 * Deterministic read-only aggregation engine for structured material requirements.
 *
 * Architectural Invariants:
 * - Read-Only: Strictly ZERO data mutations (no INSERT, UPDATE, DELETE).
 * - Single Source: Aggregates actual physical damage requirement from `temuan_materials.quantity`
 *   (NEVER `construction_bom_items.quantity` which is engineering design norm).
 * - Identity Grouping: Grouped strictly by `canonical_code_snapshot`.
 * - Multi-Level Drill-Down:
 *     Level 1: Summary grouped by canonical code
 *     Level 2: Network hierarchy breakdown (ULP -> Penyulang -> Section)
 *     Level 3: Individual findings drill-down with GIS coordinates
 * - Cascading Network Filter Isolation: ULP -> Penyulang -> Section.
 */
class MaterialIntelligenceService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Level 1: Material Requirement Summary
     *
     * Aggregates total demand grouped by canonical code snapshot.
     *
     * @param array $filters [
     *   'ulp_id' => int,
     *   'penyulang_id' => int,
     *   'section_id' => int,
     *   'status' => string,
     *   'start_date' => string (Y-m-d),
     *   'end_date' => string (Y-m-d),
     *   'query' => string,
     * ]
     * @return array List of material summaries
     */
    public function getMaterialSummary(array $filters = []): array
    {
        $builder = $this->db->table('temuan_materials tm')
            ->select('
                COALESCE(tm.canonical_code_snapshot, "UNMAPPED") AS canonical_code,
                COALESCE(tm.canonical_name_snapshot, "Material Tanpa Nama") AS material_name,
                COALESCE(tm.unit_snapshot, "buah") AS unit,
                SUM(tm.quantity) AS total_qty,
                COUNT(DISTINCT tm.temuan_id) AS temuan_count,
                COUNT(DISTINCT tm.asset_id) AS affected_assets_count
            ');

        $this->applyBaseJoinsAndFilters($builder, $filters);

        $builder->groupBy('COALESCE(tm.canonical_code_snapshot, "UNMAPPED"), COALESCE(tm.canonical_name_snapshot, "Material Tanpa Nama"), COALESCE(tm.unit_snapshot, "buah")')
                ->orderBy('total_qty', 'DESC')
                ->orderBy('material_name', 'ASC');

        $rows = $builder->get()->getResultArray();

        return array_map(function ($row) {
            return [
                'canonical_code'        => (string)$row['canonical_code'],
                'material_name'         => (string)$row['material_name'],
                'unit'                  => (string)$row['unit'],
                'total_qty'             => (float)$row['total_qty'],
                'temuan_count'          => (int)$row['temuan_count'],
                'affected_assets_count' => (int)$row['affected_assets_count'],
            ];
        }, $rows);
    }

    /**
     * Level 2: Network Hierarchy Breakdown for a Specific Canonical Code
     *
     * Breaks down demand across ULP -> Penyulang -> Section.
     *
     * @param string $canonicalCode
     * @param array $filters
     * @return array Network breakdown rows
     */
    public function getMaterialNetworkBreakdown(string $canonicalCode, array $filters = []): array
    {
        $builder = $this->db->table('temuan_materials tm')
            ->select('
                t.ulp_id,
                COALESCE(u.nama_ulp, "ULP Tidak Terpetakan") AS ulp_name,
                t.penyulang_id,
                COALESCE(p.nama_penyulang, "Penyulang Tidak Terpetakan") AS penyulang_name,
                t.section_id,
                COALESCE(s.nama_section, "Section Tidak Terpetakan") AS section_name,
                COALESCE(tm.unit_snapshot, "buah") AS unit,
                SUM(tm.quantity) AS qty,
                COUNT(DISTINCT tm.temuan_id) AS temuan_count
            ');

        $this->applyBaseJoinsAndFilters($builder, $filters);

        $builder->where('COALESCE(tm.canonical_code_snapshot, "UNMAPPED")', $canonicalCode);

        $builder->groupBy('t.ulp_id, u.nama_ulp, t.penyulang_id, p.nama_penyulang, t.section_id, s.nama_section, COALESCE(tm.unit_snapshot, "buah")')
                ->orderBy('u.nama_ulp', 'ASC')
                ->orderBy('p.nama_penyulang', 'ASC')
                ->orderBy('s.nama_section', 'ASC')
                ->orderBy('qty', 'DESC');

        $rows = $builder->get()->getResultArray();

        return array_map(function ($row) {
            return [
                'ulp_id'         => $row['ulp_id'] !== null ? (int)$row['ulp_id'] : null,
                'ulp_name'       => (string)$row['ulp_name'],
                'penyulang_id'   => $row['penyulang_id'] !== null ? (int)$row['penyulang_id'] : null,
                'penyulang_name' => (string)$row['penyulang_name'],
                'section_id'     => $row['section_id'] !== null ? (int)$row['section_id'] : null,
                'section_name'   => (string)$row['section_name'],
                'unit'           => (string)$row['unit'],
                'qty'            => (float)$row['qty'],
                'temuan_count'   => (int)$row['temuan_count'],
            ];
        }, $rows);
    }

    /**
     * Level 3: Individual Findings Drill-Down with GIS Coordinates
     *
     * Returns individual temuan rows that required this material.
     *
     * @param string $canonicalCode
     * @param array $filters
     * @return array Finding detail rows
     */
    public function getMaterialFindings(string $canonicalCode, array $filters = []): array
    {
        $builder = $this->db->table('temuan_materials tm')
            ->select('
                tm.id AS transaction_id,
                tm.temuan_id,
                t.nomor_temuan,
                t.tanggal_temuan,
                t.status,
                t.prioritas,
                t.jenis_temuan,
                t.detail_temuan,
                t.foto,
                t.foto_path,
                tm.quantity,
                COALESCE(tm.unit_snapshot, "buah") AS unit,
                tm.justification_note,
                tm.source_mode,
                tm.asset_id,
                COALESCE(a.kode_asset, "-") AS kode_asset,
                COALESCE(a.nama_asset, "-") AS nama_asset,
                COALESCE(t.latitude, a.latitude) AS latitude,
                COALESCE(t.longitude, a.longitude) AS longitude,
                u.nama_ulp,
                p.nama_penyulang,
                s.nama_section
            ');

        $this->applyBaseJoinsAndFilters($builder, $filters);

        $builder->where('COALESCE(tm.canonical_code_snapshot, "UNMAPPED")', $canonicalCode)
                ->orderBy('t.tanggal_temuan', 'DESC')
                ->orderBy('tm.id', 'DESC');

        $rows = $builder->get()->getResultArray();

        return array_map(function ($row) {
            return [
                'transaction_id'    => (int)$row['transaction_id'],
                'temuan_id'         => (int)$row['temuan_id'],
                'nomor_temuan'      => (string)$row['nomor_temuan'],
                'tanggal_temuan'    => (string)$row['tanggal_temuan'],
                'status'            => (string)($row['status'] ?? 'OPEN'),
                'prioritas'         => (string)($row['prioritas'] ?? 'SEDANG'),
                'jenis_temuan'      => (string)($row['jenis_temuan'] ?? '-'),
                'detail_temuan'     => (string)($row['detail_temuan'] ?? '-'),
                'foto'              => (string)($row['foto_path'] ?? $row['foto'] ?? ''),
                'quantity'          => (float)$row['quantity'],
                'unit'              => (string)$row['unit'],
                'justification_note'=> (string)($row['justification_note'] ?? ''),
                'source_mode'       => (string)($row['source_mode'] ?? 'CATALOG'),
                'asset_id'          => $row['asset_id'] !== null ? (int)$row['asset_id'] : null,
                'kode_asset'        => (string)$row['kode_asset'],
                'nama_asset'        => (string)$row['nama_asset'],
                'latitude'          => $row['latitude'] !== null ? (float)$row['latitude'] : null,
                'longitude'         => $row['longitude'] !== null ? (float)$row['longitude'] : null,
                'ulp_name'          => (string)($row['nama_ulp'] ?? '-'),
                'penyulang_name'    => (string)($row['nama_penyulang'] ?? '-'),
                'section_name'      => (string)($row['nama_section'] ?? '-'),
            ];
        }, $rows);
    }

    /**
     * Apply standard inner join on temuan (with soft delete check) and left joins on hierarchy.
     */
    protected function applyBaseJoinsAndFilters($builder, array $filters): void
    {
        $joinCondition = 'tm.temuan_id = t.id';
        if ($this->db->fieldExists('deleted_at', 'temuan')) {
            $joinCondition .= ' AND (t.deleted_at IS NULL OR t.deleted_at = "")';
        }

        $builder->join('temuan t', $joinCondition, 'inner')
            ->join('ulps u', 't.ulp_id = u.id', 'left')
            ->join('penyulang p', 't.penyulang_id = p.id', 'left')
            ->join('sections s', 't.section_id = s.id', 'left')
            ->join('assets a', 'tm.asset_id = a.id', 'left');

        if (!empty($filters['ulp_id'])) {
            $builder->where('t.ulp_id', (int)$filters['ulp_id']);
        }
        if (!empty($filters['penyulang_id'])) {
            $builder->where('t.penyulang_id', (int)$filters['penyulang_id']);
        }
        if (!empty($filters['section_id'])) {
            $builder->where('t.section_id', (int)$filters['section_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('t.status', $filters['status']);
        }
        if (!empty($filters['start_date'])) {
            $builder->where('t.tanggal_temuan >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('t.tanggal_temuan <=', $filters['end_date']);
        }
        if (!empty($filters['query'])) {
            $q = trim($filters['query']);
            $builder->groupStart()
                ->like('tm.canonical_code_snapshot', $q)
                ->orLike('tm.canonical_name_snapshot', $q)
                ->groupEnd();
        }
    }
}
