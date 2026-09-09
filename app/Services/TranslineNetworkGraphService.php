<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * TL-02 Phase 1: Read-Only JTM Network Graph Service
 *
 * Responsibilities:
 * - Build an in-memory graph representation G = (V, E) of the distribution network.
 * - V = ASSETS ONLY (Strictly 0 Temuan nodes).
 * - E = Authoritative active Translines in `gis_translines`.
 * - Expose: nodes, edges, degrees, connected_components, isolated_assets, terminal_assets.
 *
 * STRICT GOVERNANCE INVARIANTS:
 * - 100% READ-ONLY: 0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL.
 * - No cache tables, no temporary tables, no database mutations.
 */
class TranslineNetworkGraphService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Build in-memory graph G = (V, E) for a given feeder
     *
     * @param int $penyulangId Feeder primary key
     * @return array<string, mixed> Structured network graph representation
     */
    public function buildGraphForFeeder(int $penyulangId): array
    {
        if ($penyulangId <= 0) {
            return $this->getEmptyGraph($penyulangId);
        }

        // 1. Fetch Feeder info
        $feederName = "Penyulang #{$penyulangId}";
        $feederCode = "PYL-{$penyulangId}";
        $ulpId = 0;
        if ($this->db->tableExists('penyulang')) {
            $fRow = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
            if ($fRow) {
                $feederName = $fRow['nama_penyulang'] ?? $feederName;
                $feederCode = $fRow['kode_penyulang'] ?? $fRow['id_unik_penyulang'] ?? $feederCode;
                $ulpId = (int)($fRow['ulp_id'] ?? 0);
            }
        }

        // 2. Fetch all Master Assets for this feeder (V = ASSETS ONLY)
        $nodes = [];
        $assetMap = [];
        if ($this->db->tableExists('assets')) {
            $builder = $this->db->table('assets')->where('penyulang_id', $penyulangId);
            if ($this->db->fieldExists('deleted_at', 'assets')) {
                $builder->where('deleted_at IS NULL');
            }
            $assetRows = $builder->get()->getResultArray();
            foreach ($assetRows as $a) {
                $id = (int)$a['id'];
                $nodeData = [
                    'id'                   => $id,
                    'kode_asset'           => $a['kode_asset'] ?? "AST-{$id}",
                    'nama_asset'           => $a['nama_asset'] ?? '',
                    'jenis_asset'          => $a['jenis_asset'] ?? 'TIANG_BETON',
                    'latitude'             => (float)($a['latitude'] ?? 0),
                    'longitude'            => (float)($a['longitude'] ?? 0),
                    'section_id'           => !empty($a['section_id']) ? (int)$a['section_id'] : null,
                    'penyulang_id'         => (int)($a['penyulang_id'] ?? $penyulangId),
                    'construction_type_id' => !empty($a['construction_type_id']) ? (int)$a['construction_type_id'] : null,
                    'sequence_no'          => isset($a['sequence_no']) ? (int)$a['sequence_no'] : null,
                    'status'               => $a['status'] ?? 'NORMAL',
                ];
                $nodes[$id] = $nodeData;
                $assetMap[$id] = $nodeData;
            }
        }

        // 3. Fetch all active authoritative Translines (E = AUTHORITATIVE EDGES)
        $edges = [];
        $adj = [];
        $degrees = [];
        foreach (array_keys($nodes) as $aId) {
            $adj[$aId] = [];
            $degrees[$aId] = 0;
        }

        if ($this->db->tableExists('gis_translines')) {
            $builder = $this->db->table('gis_translines')->where('penyulang_id', $penyulangId);
            if ($this->db->fieldExists('is_active', 'gis_translines')) {
                $builder->where('is_active', 1);
            }
            if ($this->db->fieldExists('deleted_at', 'gis_translines')) {
                $builder->where('deleted_at IS NULL');
            }
            $tlRows = $builder->get()->getResultArray();

            foreach ($tlRows as $tl) {
                $sId = (int)$tl['source_asset_id'];
                $tId = (int)$tl['target_asset_id'];

                // Enforce Asset-only domain firewall & valid endpoints
                if (!isset($nodes[$sId]) || !isset($nodes[$tId]) || $sId === $tId) {
                    continue;
                }

                $minId = min($sId, $tId);
                $maxId = max($sId, $tId);
                $natKey = "TL-NAT:{$penyulangId}:{$minId}-{$maxId}";

                $edges[$natKey] = [
                    'id'                 => (int)$tl['id'],
                    'transline_code'     => $tl['transline_code'] ?? "TL-{$tl['id']}",
                    'natural_key'        => $natKey,
                    'source_asset_id'    => $sId,
                    'target_asset_id'    => $tId,
                    'source_asset_code'  => $nodes[$sId]['kode_asset'],
                    'target_asset_code'  => $nodes[$tId]['kode_asset'],
                    'distance_meters'    => (float)($tl['distance_meters'] ?? 0),
                    'conductor_type'     => $tl['conductor_type'] ?? 'AAAC',
                    'conductor_size'     => $tl['conductor_size'] ?? '150 mm²',
                    'conductor_material' => $tl['conductor_material'] ?? 'ALUMINUM_ALLOY',
                    'status'             => $tl['status'] ?? 'ACTIVE',
                    'geometry'           => $tl['geometry'] ?? null,
                ];

                $adj[$sId][] = $tId;
                $adj[$tId][] = $sId;
                $degrees[$sId] = ($degrees[$sId] ?? 0) + 1;
                $degrees[$tId] = ($degrees[$tId] ?? 0) + 1;
            }
        }

        // Deduplicate adjacency lists
        foreach ($adj as $k => $list) {
            $adj[$k] = array_values(array_unique($list));
        }

        // 4. Graph Partitioning: Connected Components (BFS)
        $visited = [];
        $components = [];

        foreach (array_keys($nodes) as $assetId) {
            if (isset($visited[$assetId])) continue;

            $queue = [$assetId];
            $visited[$assetId] = true;
            $compMembers = [];

            while (!empty($queue)) {
                $curr = array_shift($queue);
                $compMembers[] = $curr;

                foreach ($adj[$curr] as $neighbor) {
                    if (!isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }

            $components[] = $compMembers;
        }

        // Sort components by size descending
        usort($components, fn($a, $b) => count($b) <=> count($a));

        // 5. Categorize Assets by Topological Role
        $isolatedAssets = [];
        $terminalAssets = [];
        $intermediateAssets = [];
        $branchAssets = [];

        foreach ($nodes as $aId => $node) {
            $deg = $degrees[$aId] ?? 0;
            if ($deg === 0) {
                $isolatedAssets[$aId] = $node;
            } elseif ($deg === 1) {
                $terminalAssets[$aId] = $node;
            } elseif ($deg === 2) {
                $intermediateAssets[$aId] = $node;
            } else {
                $branchAssets[$aId] = $node;
            }
        }

        return [
            'scope' => [
                'ulp_id'        => $ulpId,
                'penyulang_id'  => $penyulangId,
                'penyulang_name'=> $feederName,
                'penyulang_code'=> $feederCode,
            ],
            'summary' => [
                'total_nodes'              => count($nodes),
                'total_authoritative_edges'=> count($edges),
                'connected_nodes_count'    => count($nodes) - count($isolatedAssets),
                'isolated_nodes_count'     => count($isolatedAssets),
                'terminal_nodes_count'     => count($terminalAssets),
                'branch_nodes_count'       => count($branchAssets),
                'component_count'          => count($components),
            ],
            'nodes'               => $nodes,
            'edges'               => array_values($edges),
            'adjacency'           => $adj,
            'degrees'             => $degrees,
            'connected_components'=> $components,
            'isolated_assets'     => $isolatedAssets,
            'terminal_assets'     => $terminalAssets,
            'branch_assets'       => $branchAssets,
        ];
    }

    /**
     * Return empty graph structure for invalid feeder
     */
    protected function getEmptyGraph(int $penyulangId): array
    {
        return [
            'scope' => [
                'ulp_id'        => 0,
                'penyulang_id'  => $penyulangId,
                'penyulang_name'=> 'INVALID_SCOPE',
                'penyulang_code'=> '',
            ],
            'summary' => [
                'total_nodes'              => 0,
                'total_authoritative_edges'=> 0,
                'connected_nodes_count'    => 0,
                'isolated_nodes_count'     => 0,
                'terminal_nodes_count'     => 0,
                'branch_nodes_count'       => 0,
                'component_count'          => 0,
            ],
            'nodes'               => [],
            'edges'               => [],
            'adjacency'           => [],
            'degrees'             => [],
            'connected_components'=> [],
            'isolated_assets'     => [],
            'terminal_assets'     => [],
            'branch_assets'       => [],
        ];
    }
}
