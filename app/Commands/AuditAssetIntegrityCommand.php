<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\AssetIntegrityAuditService;

/**
 * AuditAssetIntegrityCommand
 *
 * Usage: php spark audit:asset-integrity
 *
 * STRICT READ-ONLY: 0 mutations to assets, temuan, or translines.
 */
class AuditAssetIntegrityCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:asset-integrity';
    protected $description = 'Forensic audit of Master Asset integrity, duplicates, feeder distribution, and GIS parity (Strict Read-Only).';

    public function run(array $params)
    {
        CLI::write("==================================================================", "yellow");
        CLI::write("       SIDAK TEJO — ASSET INTEGRITY & GIS FORENSIC AUDIT          ", "yellow");
        CLI::write("==================================================================", "yellow");
        CLI::newLine();

        $service = new AssetIntegrityAuditService();
        $audit   = $service->runAudit();

        // 1. OVERVIEW
        CLI::write("1. ASSET POPULATION & PK UNIQUENESS", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  • Total Raw Rows       : %d", $audit['overview']['total_raw']));
        CLI::write(sprintf("  • Total Active Rows    : %d", $audit['overview']['total_active']));
        CLI::write(sprintf("  • Total Soft-Deleted   : %d", $audit['overview']['total_soft_deleted']));
        CLI::write(sprintf("  • Distinct Primary Keys: %d", $audit['overview']['distinct_pks']));
        CLI::write(sprintf("  • PK Collisions        : %d", $audit['overview']['pk_duplicates']), $audit['overview']['is_pk_unique'] ? "green" : "red");

        CLI::newLine();
        CLI::write("  Breakdown by Jenis Asset:", "white");
        foreach ($audit['overview']['by_jenis_asset'] as $ja) {
            CLI::write(sprintf("    - %-20s : %d unit", $ja['jenis_asset'], $ja['cnt']));
        }

        // 2. DUPLICATE AUDIT
        CLI::newLine();
        CLI::write("2. ASSET CODE & COMPOSITE DUPLICATE AUDIT", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  • Distinct kode_asset  : %d / %d", $audit['uniqueness']['kode_asset']['distinct_codes'], $audit['uniqueness']['kode_asset']['total_evaluated']));
        CLI::write(sprintf("  • Duplicate kode_asset : %d instances across %d groups", $audit['uniqueness']['kode_asset']['duplicate_count'], $audit['uniqueness']['kode_asset']['duplicate_groups']));
        CLI::write(sprintf("  • Composite Dup Groups : %d groups (ulp_id + jenis + nama)", $audit['uniqueness']['composite']['duplicate_groups_count']));
        CLI::write(sprintf("  • Spatial Collision    : %d clusters of identical coordinates", $audit['uniqueness']['spatial']['spatial_collision_clusters']));

        // 3. FEEDER MAPPING BREAKDOWN
        CLI::newLine();
        CLI::write("3. FEEDER MAPPING BREAKDOWN (42,231 vs 18,854 SOLVED)", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  • Mapped to Feeder (penyulang_id > 0) : %s assets", number_format($audit['feeder_mapping']['mapped_to_feeder'])), "green");
        CLI::write(sprintf("  • Unassigned Pool (penyulang_id IS NULL): %s assets", number_format($audit['feeder_mapping']['unassigned_no_feeder'])), "yellow");
        CLI::write(sprintf("  • Orphan Feeder FKs (broken link)       : %d assets", $audit['feeder_mapping']['orphan_penyulang_fks']), $audit['feeder_mapping']['orphan_penyulang_fks'] === 0 ? "green" : "red");
        CLI::write(sprintf("  • SUM Verification: %s + %s = %s",
            number_format($audit['feeder_mapping']['mapped_to_feeder']),
            number_format($audit['feeder_mapping']['unassigned_no_feeder']),
            number_format($audit['feeder_mapping']['total_active_assets'])
        ), "cyan");

        // 4. PENYULANG COVERAGE
        CLI::newLine();
        CLI::write("4. PENYULANG COVERAGE & ZERO-ASSET FEEDERS", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  • Total Feeders in Registry : %d", $audit['penyulang_audit']['total_feeders']));
        CLI::write(sprintf("  • Feeders With Active Assets: %d", $audit['penyulang_audit']['feeders_with_assets']), "green");
        CLI::write(sprintf("  • Feeders With 0 Assets     : %d", $audit['penyulang_audit']['feeders_zero_assets']), "yellow");

        // 5. NAME MATCHING SIMULATION
        CLI::newLine();
        CLI::write("5. POTENTIAL AUTO-MAPPING BY ASSET NAME (READ-ONLY SIMULATION)", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write(sprintf("  • Unassigned Assets Matchable by Name: %s assets", number_format($audit['matching_sim']['total_unassigned_matchable_by_name'])), "green");
        CLI::write("  Top Matchable Feeders:");
        foreach (array_slice($audit['matching_sim']['top_matchable_feeders'], 0, 8) as $m) {
            CLI::write(sprintf("    - Feeder %-25s (%s) : %d unassigned assets", $m['nama_penyulang'], $m['nama_ulp'] ?? '-', $m['matchable_assets']));
        }

        // 6. RECONCILIATION SUMMARY
        CLI::newLine();
        CLI::write("6. FORENSIC RECONCILIATION SUMMARY", "cyan");
        CLI::write("------------------------------------------------------------------", "white");
        CLI::write("  [Finding 1] " . $audit['reconciliation']['finding_1_asset_total']);
        CLI::write("  [Finding 2] " . $audit['reconciliation']['finding_2_feeder_zeros']);
        CLI::write("  [Finding 3] " . $audit['reconciliation']['finding_3_gis_empty_state']);
        CLI::newLine();
        CLI::write("  [Recommendation] " . $audit['reconciliation']['recommendation'], "green");
        CLI::newLine();
        CLI::write("==================================================================", "yellow");
    }
}
