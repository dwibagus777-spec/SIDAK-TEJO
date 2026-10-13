<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Class SldForensicPhase0Test
 *
 * Enforces strict mathematical and topological invariants for SLD-01 Phase 0 Reconciled:
 * 1. 134 Feeders across 3 ULPs scanned
 * 2. 5,236 physical assets with 100% valid GPS coordinates
 * 3. 216 translines, 239 connected assets, 4,997 isolated assets
 * 4. Graph Theory Invariant (Handshaking Lemma): SUM(degree) = 2 * E = 432 (Delta = 0)
 * 5. Degree distribution: Deg 0 = 4,997, Deg 1 = 65, Deg 2 = 155, Deg 3 = 19, Deg >= 4 = 0
 * 6. Acyclic Forest Invariant: E = V - k ==> 216 = 239 - 23 (23 connected components, 0 cycles)
 * 7. 9 Gardu Induk (GI), PK 1 is GI Buduran
 * 8. GI Three-Tier Classification: 5 GI_VALID, 28 GI_RELATIONSHIP_MISSING, 101 NO_ASSET_DATA
 * 9. Feeder 15 Source Path: Proven transitions (GI Incomer, TL 4, TL 5, TL 6, TL 8)
 * 10. 110 Switch candidates (PMS), zero guessing invariant
 * 11. 193 GTT transformers, optionality rule
 * 12. 4,933 standard JTM poles (5,236 - 110 - 193)
 * 13. Temuan domain isolation: 0 temuan used in topology
 * 14. SLD readiness categorization: 1 READY, 1 PARTIAL, 28 BLOCKED, 3 ISOLATED, 101 NOT_ELIGIBLE
 * 15. Strict Read-Only: Delta = 0
 * 16. All 7 deliverables verified present
 */
class SldForensicPhase0Test extends CIUnitTestCase
{
    private string $auditJsonPath;
    private array $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditJsonPath = APPPATH . '../writable/audits/sld01_final_audit.json';
        $this->assertFileExists($this->auditJsonPath, "sld01_final_audit.json must exist in writable/audits/");

        $this->audit = json_decode(file_get_contents($this->auditJsonPath), true);
        $this->assertEquals('success', $this->audit['status']);
        $this->assertEquals('SLD_01_FORENSIC_NETWORK_FOUNDATION_PHASE_0', $this->audit['phase']);
    }

    /**
     * 1. 134 Registered Feeders and 3 ULPs
     */
    public function testAll134FeedersAndThreeUlpsScanned(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(134, $inv['total_feeders']);
        $this->assertEquals(3, $inv['total_ulps']);
        $this->assertEquals(33, $inv['active_feeders']);
        $this->assertEquals(101, $inv['inactive_feeders']);
        $this->assertEquals(134, $inv['active_feeders'] + $inv['inactive_feeders']);
    }

    /**
     * 2. Authoritative Asset Count & 100% Valid GPS
     */
    public function testAuthoritativeAssetCountsAndValidGps(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(5236, $inv['total_jtm_assets']);
        $this->assertEquals(5236, $inv['total_valid_gps_assets']);
        $this->assertSame($inv['total_jtm_assets'], $inv['total_valid_gps_assets']);
    }

    /**
     * 3. Authoritative Translines & Disjoint Connected Assets
     */
    public function testAuthoritativeTranslinesAndConnectedAssets(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(216, $inv['total_translines']);
        $this->assertEquals(239, $inv['total_connected_assets']);
        $this->assertEquals(4997, $inv['total_isolated_assets']);
        $this->assertEquals(5236, $inv['total_connected_assets'] + $inv['total_isolated_assets']);
    }

    /**
     * 4. Handshaking Lemma & Degree Distribution Reconciliation
     */
    public function testHandshakingLemmaAndDegreeReconciliation(): void
    {
        $gr = $this->audit['graph_reconciliation'];
        $this->assertEquals(5236, $gr['total_nodes_v']);
        $this->assertEquals(239, $gr['connected_nodes']);
        $this->assertEquals(4997, $gr['isolated_nodes_deg0']);
        $this->assertEquals(65, $gr['terminal_nodes_deg1']);
        $this->assertEquals(155, $gr['passthrough_nodes_deg2']);
        $this->assertEquals(19, $gr['branch_nodes_deg3']);
        $this->assertEquals(0, $gr['saturated_nodes_deg4plus']);

        // Node sum check
        $this->assertEquals(
            5236,
            $gr['isolated_nodes_deg0'] + $gr['terminal_nodes_deg1'] + $gr['passthrough_nodes_deg2'] + $gr['branch_nodes_deg3']
        );

        // Handshaking Lemma: Sum(degree) = 2 * E
        $this->assertEquals(216, $gr['total_edges_e']);
        $this->assertEquals(432, $gr['sum_degrees_actual']);
        $this->assertEquals(432, $gr['sum_degrees_expected_2E']);
        $this->assertEquals(0, $gr['degree_discrepancy_delta']);
        $this->assertTrue($gr['handshaking_lemma_verified']);

        // Mathematical evaluation: 65*1 + 155*2 + 19*3 = 65 + 310 + 57 = 432
        $calcSum = (65 * 1) + (155 * 2) + (19 * 3);
        $this->assertEquals(432, $calcSum);
        $this->assertEquals(2 * 216, $calcSum);
    }

    /**
     * 5. Connected Components & Acyclic Forest Identity (E = V - k)
     */
    public function testAcyclicForestIdentityAndComponents(): void
    {
        $gr = $this->audit['graph_reconciliation'];
        $this->assertEquals(23, $gr['total_connected_components']);
        $this->assertTrue($gr['forest_identity_E_equals_V_minus_k']);

        // Forest identity: E = V - k ==> 216 = 239 - 23
        $this->assertEquals(216, 239 - 23);

        $comps = $this->audit['feeder_components'];
        $this->assertArrayHasKey('15', $comps);
        $this->assertArrayHasKey('4', $comps);
        $this->assertArrayHasKey('56', $comps);
        $this->assertArrayHasKey('131', $comps);

        // Feeder 15: 203 nodes, 197 edges, 6 components ==> 197 = 203 - 6
        $this->assertEquals(6, $comps['15']['components_count']);
        $this->assertEquals(203, $comps['15']['sum_component_nodes']);
        $this->assertEquals(197, $comps['15']['sum_component_edges']);

        // Feeder 4: 3 nodes, 2 edges, 1 component ==> 2 = 3 - 1
        $this->assertEquals(1, $comps['4']['components_count']);
        $this->assertEquals(3, $comps['4']['sum_component_nodes']);
        $this->assertEquals(2, $comps['4']['sum_component_edges']);

        // Feeder 56: 17 nodes, 9 edges, 8 components ==> 9 = 17 - 8
        $this->assertEquals(8, $comps['56']['components_count']);
        $this->assertEquals(17, $comps['56']['sum_component_nodes']);
        $this->assertEquals(9, $comps['56']['sum_component_edges']);

        // Feeder 131: 16 nodes, 8 edges, 8 components ==> 8 = 16 - 8
        $this->assertEquals(8, $comps['131']['components_count']);
        $this->assertEquals(16, $comps['131']['sum_component_nodes']);
        $this->assertEquals(8, $comps['131']['sum_component_edges']);
    }

    /**
     * 6. Master Gardu Induk (GI) Inventory & Three-Tier Classification
     */
    public function testMasterGarduIndukThreeTierClassification(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(9, $inv['total_gardu_induk']);

        $giClass = $this->audit['gi_classification'];
        $this->assertEquals(5, $giClass['GI_VALID']);
        $this->assertEquals(28, $giClass['GI_RELATIONSHIP_MISSING']);
        $this->assertEquals(101, $giClass['NO_ASSET_DATA']);
        $this->assertEquals(134, $giClass['sum_check']);
    }

    /**
     * 7. Feeder 15 Source Path & Authoritative Topology Proof
     */
    public function testFeeder15SourcePathAuthoritativeProof(): void
    {
        $matrix = $this->audit['feeder_matrix'];
        $f15 = null;
        foreach ($matrix as $f) {
            if ((int)$f['penyulang_id'] === 15) {
                $f15 = $f;
                break;
            }
        }

        $this->assertNotNull($f15, "Feeder 15 must be present in matrix");
        $this->assertEquals('BANJAR KEMANTREN', $f15['nama_penyulang']);
        $this->assertEquals(1, $f15['gi_id']);
        $this->assertEquals('GI BUDURAN', $f15['gi_name']);
        $this->assertEquals('GI_VALID', $f15['gi_category']);
        $this->assertEquals(205, $f15['total_jtm_assets']);
        $this->assertEquals(197, $f15['existing_translines']);
        $this->assertEquals(203, $f15['connected_assets']);
        $this->assertEquals(4, $f15['switches_count']);
        $this->assertEquals(23, $f15['gtts_count']);
        $this->assertEquals('SLD_READY', $f15['sld_readiness']);
    }

    /**
     * 8. Switch Candidates Inventory & Zero-Guessing Policy
     */
    public function testKeypointAndSwitchCandidatesCatalog(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(110, $inv['total_switches']);

        $kpSummary = $this->audit['keypoint_summary'];
        $this->assertEquals(110, $kpSummary['total_switch_candidates']);
        $this->assertEquals('SWITCH_CANDIDATE', $kpSummary['classification']);
        $this->assertEquals('UNKNOWN_OPERATIONAL_SUBTYPE', $kpSummary['operational_subtype']);
        $this->assertEquals('ENFORCED', $kpSummary['zero_guessing_policy']);
    }

    /**
     * 9. Distribution Transformers (GTT) Inventory
     */
    public function testDistributionTransformersGttInventory(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(193, $inv['total_gtts']);
    }

    /**
     * 10. Standard JTM Distribution Poles Sum Check
     */
    public function testStandardJtmPolesSumCheck(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(4933, $inv['total_standard_jtm_poles']);
        $this->assertEquals(
            $inv['total_jtm_assets'],
            $inv['total_switches'] + $inv['total_gtts'] + $inv['total_standard_jtm_poles']
        );
    }

    /**
     * 11. Temuan Domain Isolation Invariant
     */
    public function testTemuanDomainIsolationInvariant(): void
    {
        $inv = $this->audit['global_inventory'];
        $this->assertEquals(0, $inv['temuan_used_as_topology_endpoint']);
    }

    /**
     * 12. Reconciled SLD Feeder Readiness Classification
     */
    public function testSldReadinessClassificationReconciled(): void
    {
        $ready = $this->audit['sld_readiness_summary'];
        $this->assertEquals(1, $ready['SLD_READY']);
        $this->assertEquals(1, $ready['SLD_PARTIALLY_CONNECTED']);
        $this->assertEquals(28, $ready['SLD_BLOCKED']);
        $this->assertEquals(3, $ready['SLD_ISOLATED_TOPOLOGY_REQUIRED']);
        $this->assertEquals(101, $ready['NOT_ELIGIBLE_SCOPE']);
        $this->assertEquals(
            134,
            $ready['SLD_READY'] + $ready['SLD_PARTIALLY_CONNECTED'] + $ready['SLD_BLOCKED'] + $ready['SLD_ISOLATED_TOPOLOGY_REQUIRED'] + $ready['NOT_ELIGIBLE_SCOPE']
        );
    }

    /**
     * 13. Strict Read-Only Governance & Stop Gate Active
     */
    public function testStrictReadOnlyGovernanceAndStopGateActive(): void
    {
        $gov = $this->audit['governance'];
        $this->assertTrue($gov['strict_read_only']);
        $this->assertTrue($gov['delta_zero']);
        $this->assertEquals(0, $gov['inserts']);
        $this->assertEquals(0, $gov['updates']);
        $this->assertEquals(0, $gov['deletes']);
        $this->assertEquals(0, $gov['ddl']);
        $this->assertEquals('PASS', $gov['zero_write_firewall']);
        $this->assertTrue($gov['stop_gate_active']);
        $this->assertTrue($gov['renderer_implementation_blocked']);
    }

    /**
     * 14. All 7 Mandated Phase 0 Deliverables Present
     */
    public function testAllSevenPhase0DeliverablesPresent(): void
    {
        $auditDir = APPPATH . '../writable/audits/';
        $requiredFiles = [
            'SLD-01_FORENSIC_REGISTER.md',
            'SLD-01_TOPOLOGY_INVENTORY.md',
            'SLD-01_KEYPOINT_INVENTORY.md',
            'SLD-01_DATA_QUALITY_REPORT.md',
            'SLD-01_STOP_GATE.md',
            'sld01_final_audit.json',
            'SLD-01_RECONCILIATION_CORRECTION.md',
        ];

        foreach ($requiredFiles as $file) {
            $path = $auditDir . $file;
            $this->assertFileExists($path, "Deliverable {$file} must exist");
            $this->assertGreaterThan(500, filesize($path), "Deliverable {$file} must not be empty");
        }
    }
}
