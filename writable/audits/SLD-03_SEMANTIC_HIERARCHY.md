# SLD-03 SEMANTIC NETWORK HIERARCHY & DEVICE CLASSIFICATION REPORT
**Document ID**: `SLD-03_SEMANTIC_HIERARCHY.md`  
**Milestone**: Phase SLD-03 (Semantic Hierarchy & Device Classification Layer)  
**Status**: 🔒 **READY FOR SEMANTIC INTEGRITY REVIEW & FINAL SEAL**  
**Audit Date**: 2026-09-14  
**Primary Pilot**: Feeder 15 (`BANJAR KEMANTREN`, PYL-015)  
**Invariant State**: Strict Read-Only ($\Delta = 0$: `INSERT=0, UPDATE=0, DELETE=0, TRUNCATE=0, ALTER=0, DROP=0`)

---

## 1. Executive Summary & Architectural Role

Milestone **SLD-03** establishes the authoritative **Semantic Network Hierarchy & Device Classification Layer** in SIDAK TEJO. Following the sealing of SLD-02 (Topology Graph Read Model), SLD-03 bridges raw mathematical graph theory $G=(V, E)$ and future rendering phases by transforming undirected assets and translines into an explicit electrical hierarchy.

In accordance with the system architect's mandate:
> *"Renderer nanti hanya menjadi 'gambar dari semantic graph', bukan renderer yang ikut-ikutan menebak topology."*

SLD-03 guarantees that no downstream renderer, UI layer, or simulation engine ever has to guess whether a node is a line section, a branch, a switch, or a transformer.

```
+-----------------------------------------------------------------------------------+
|  PHYSICAL DATABASE (Strict Read-Only, Delta = 0)                                  |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼
+-----------------------------------------------------------------------------------+
|  SLD-02: TOPOLOGY GRAPH READ MODEL (The "Brain" - Undirected Graph G=(V,E))       |
|  - Assets: 205 nodes (203 connected, 2 isolated)                                  |
|  - Translines: 197 edges (Undirected conductors, 0 cross-feeder leakage)          |
|  - Components: 6 Acyclic Trees (C15-01 .. C15-06)                                 |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼
+-----------------------------------------------------------------------------------+
|  SLD-03: SEMANTIC NETWORK HIERARCHY & DEVICE CLASSIFICATION LAYER                 |
|  [App\Services\SldSemanticClassificationService]                                 |
|                                                                                   |
|  1. TWO-DIMENSIONAL DECOUPLING                                                    |
|     - topology_role : SOURCE_INCOMER, BRANCH_NODE, PASS_THROUGH, TERMINAL_NODE,   |
|                       ISOLATED_NODE                                               |
|     - device_role   : SOURCE_INCOMER, SWITCH_CANDIDATE, TRANSFORMER_NODE,         |
|                       LINE_POLE                                                   |
|     - operational_state: strictly UNKNOWN_OPERATIONAL_STATE (zero guesses)        |
|                                                                                   |
|  2. ROOTED ELECTRICAL HIERARCHY (Root = Incomer #3231 in C15-01)                  |
|     - Algorithmic BFS tree for C15-01 (parent_asset_id, depth_from_source)       |
|     - Unrooted fragments (C15-02 .. C15-06) retain rooted: false (no fake dir)    |
|                                                                                   |
|  3. LINE SECTION SYNTHESIS (Preserves Granular Poles & Spans)                     |
|     - 60 Line Sections aggregating 197 spans                                      |
|     - Underlying 205 physical nodes and 197 edges remain 100% available           |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼
+-----------------------------------------------------------------------------------+
|  FUTURE PHASES (SLD-04 .. SLD-07): PRESENTATION & RENDERING ENGINE                |
|  [ABSOLUTE STOP GATE ENFORCED: ZERO RENDERING / ZERO SVG / ZERO CSS IN SLD-03]     |
+-----------------------------------------------------------------------------------+
```

---

## 2. Verification of the 8 Mandatory Amendments

### Amendment 1: Elimination of `NORMAL_CLOSED_CANDIDATE`
* **Principle**: Because SIDAK TEJO does not possess real-time SCADA switch telemetry, no switch status may be assumed or guessed.
* **Verification**:
  * All 4 PMS switches (#3188, #3217, #3266, #3295) are classified strictly with:
    $$\text{device\_role} = \text{"SWITCH\_CANDIDATE"}, \quad \text{operational\_state} = \text{"UNKNOWN\_OPERATIONAL\_STATE"}$$
  * All 201 non-switch assets are assigned `"operational_state": "NOT_APPLICABLE"`.
  * The term `NORMAL_CLOSED_CANDIDATE` is completely purged from code, tests, and API output.

### Amendment 2: `LINE_SECTION` Aggregation Preserves Full Granularity
* **Principle**: High-level electrical segmentation must never delete, mutate, or hide underlying physical poles and conductor spans.
* **Verification**:
  * Feeder 15 synthesizes exactly **60 Line Sections** accounting for all **197 physical spans** ($100\%$).
  * Each section encapsulates `member_node_ids`, `member_edge_ids`, `source_node_id`, `target_node_id`, and `span_count`.
  * The underlying physical `nodes` (205) and `edges` (197) remain fully populated in the output contract.

### Amendment 3: Two-Dimensional Decoupling (`topology_role` vs `device_role`)
* **Principle**: Graph connectivity and physical equipment functionality are orthogonal dimensions. A branch node may also be a switch.
* **Verification**:
  * Node #3188 (BANJARKEMANTRAN_28):
    $$\text{degree} = 3 \implies \mathbf{topology\_role = \text{"BRANCH\_NODE"}}, \quad \mathbf{device\_role = \text{"SWITCH\_CANDIDATE"}}$$
  * Node #3217, #3266, #3295:
    $$\text{degree} = 2 \implies \mathbf{topology\_role = \text{"PASS\_THROUGH"}}, \quad \mathbf{device\_role = \text{"SWITCH\_CANDIDATE"}}$$
  * Node #3231:
    $$\mathbf{topology\_role = \text{"SOURCE\_INCOMER"}}, \quad \mathbf{device\_role = \text{"SOURCE\_INCOMER"}}$$

### Amendment 4: `TERMINAL_NODE` is Topology Semantic, Not Equipment Type
* **Principle**: A dead-end node is a topologic characteristic ($\text{deg} = 1$), not an asset material or equipment model.
* **Verification**:
  * Feeder 15 possesses exactly 31 degree-1 nodes. Incomer Node #3231 is uniquely classified as `SOURCE_INCOMER`, leaving exactly **30 dead-end assets** categorized as `topology_role: "TERMINAL_NODE"`.
  * Physical equipment types are retained: `equipment_type: "TIANG_JTM"`, `"GTT..."`, `"TM1"`, etc.
  * The topology roles form an exact, mutually exclusive partition totaling 205:
    $$1 \text{ (Incomer)} + 30 \text{ (Terminal)} + 153 \text{ (Pass-thru)} + 19 \text{ (Branch)} + 2 \text{ (Isolated)} = \mathbf{205}$$

### Amendment 5: Authoritative GTT Metadata Resolution
* **Principle**: Distribution transformers derive strictly from authoritative construction codes, never from proximity or degree guessing.
* **Verification across all 23 GTT assets on Feeder 15**:
  * **4 GTT1 Cantol Transformers** (1 Tiang): Assets #3362, #3363, #3169, #3189 $\to$ `equipment_type: "GTT1_CANTOL"`.
  * **15 GTT2 Portal Transformers** (2 Tiang): Assets #3339, #3346, #3359, #3167, #3168, #3173, #3175, #3183, #3187, #3204, #3236, #3258, #3283, #3284, #3297 $\to$ `equipment_type: "GTT2_PORTAL"`.
  * **4 Generic GTT Transformers**: Assets #3361, #3193, #3213, #3277 $\to$ `equipment_type: "UNKNOWN_GTT_SUBTYPE"` (strictly no guessing).

### Amendment 6: Unrooted Components Have NO Fake Directionality
* **Principle**: Sub-clusters disconnected from the Substation Incomer must not be assigned arbitrary geographic roots or false upstream flows.
* **Verification**:
  * Component `C15-01` (183 nodes, 182 edges): `rooted: true`, `source_reachable: true`, `root_asset_id: 3231`.
  * Components `C15-02` through `C15-06`:
    $$\mathbf{rooted = \text{false}}, \quad \mathbf{source\_reachable = \text{false}}, \quad \mathbf{root\_asset\_id = \text{null}}$$
  * All nodes belonging to `C15-02` .. `C15-06` have `parent_asset_id: null`, `child_asset_ids: []`, and `depth_from_source: null`.

### Amendment 7: Strong Graph Preservation & Invariants
* **Principle**: The semantic model must not invent edges or drop nodes.
* **Verification**:
  * Node preservation: Exactly 205 nodes ($205_{\text{SLD-03}} = 205_{\text{SLD-02}}$).
  * Edge preservation: Exactly 197 edges ($197_{\text{SLD-03}} = 197_{\text{SLD-02}}$).
  * Edge containment: $\text{semantic\_edge\_ids} \subseteq \text{topology\_edge\_ids}$ ($100\%$ containment, zero invented edges).
  * GTT presentation invariance: `include_gtt = false` vs `true` yields bitwise identical physical nodes and edges.

### Amendment 8: Clean Controller Layering
* **Principle**: Controllers remain thin adapters for HTTP validation and serialization.
* **Verification**:
  $$\text{SldApiController} \longrightarrow \text{SldSemanticClassificationService} \longrightarrow \text{SldTopologyReadModelService}$$
  Zero database queries or graph business logic reside in `SldApiController`.

---

## 3. Semantic Distribution Matrix (Feeder 15)

```
========================================================================================
SEMANTIC TOPOLOGY & DEVICE METRICS: FEEDER 15 (BANJAR KEMANTREN)
========================================================================================
Total Physical Assets (Nodes)       : 205
Total Physical Translines (Edges)   : 197
Total Connected Components          : 6 (Acyclic Forest, Gamma = 0)
  - Rooted Components               : 1 (C15-01, Source Reachable)
  - Unrooted Components             : 5 (C15-02..C15-06, Forest Fragments)
----------------------------------------------------------------------------------------
TOPOLOGY ROLES (Exact Partition, Sum = 205):
  - SOURCE_INCOMER (Takeoff #3231, Deg 1): 1
  - BRANCH_NODE (Degree >= 3)           : 19
  - PASS_THROUGH (Degree 2)             : 153
  - TERMINAL_NODE (Degree 1 Dead-Ends)  : 30
  - ISOLATED_NODE (Degree 0)            : 2 (#3203, #3263)
----------------------------------------------------------------------------------------
DEVICE ROLES (Exact Partition, Sum = 205):
  - SOURCE_INCOMER                      : 1
  - SWITCH_CANDIDATE (PMS)              : 4 (All UNKNOWN_OPERATIONAL_STATE)
  - TRANSFORMER_NODE (GTT)              : 23 (4 Cantol, 15 Portal, 4 Unknown Subtype)
  - LINE_POLE (Standard Pole)           : 177
----------------------------------------------------------------------------------------
LINE SECTION SYNTHESIS              :
  - Total Line Sections             : 60
  - Total Spans Aggregated          : 197 (100% of Physical Edges)
  - Edge Coverage Check             : EXACT IDENTITY (197 / 197)
========================================================================================
```

---

## 4. REST API Endpoint Specification

### `GET /api/sld/feeder/{id}/semantic`

#### Query Parameters:
* `include_gtt` (boolean, optional, default: `false`): Toggles presentation metadata `gtt.visible` without mutating underlying graph.

#### Canonical Response Sample:
```json
{
  "status": "success",
  "scope": {
    "type": "FEEDER",
    "feeder_id": 15,
    "feeder_code": "PYL-015",
    "feeder_name": "BANJAR KEMANTREN"
  },
  "source": {
    "gi_id": 1,
    "code": "GI-BDR",
    "name": "GI BUDURAN",
    "status": "GI_VALID",
    "takeoff": {
      "status": "AUTHORITATIVE",
      "asset_id": 3231,
      "asset_name": "BANJARKEMANTRAN_99",
      "construction_type": "TM11",
      "distance_meters": 71.78
    }
  },
  "graph": {
    "nodes_count": 205,
    "edges_count": 197,
    "direction": "UNDIRECTED",
    "is_acyclic_forest": true,
    "handshaking_verified": true,
    "sum_degrees": 394
  },
  "semantic": {
    "total_nodes": 205,
    "total_edges": 197,
    "rooted_components": 1,
    "unrooted_components": 5,
    "isolated_nodes": 2,
    "switch_candidates": 4,
    "transformer_nodes": 23,
    "branch_nodes": 19,
    "terminal_nodes": 30,
    "pass_through_nodes": 153,
    "line_sections_count": 60,
    "line_sections_span_count": 197
  },
  "components": [
    {
      "component_id": "C15-01",
      "nodes_count": 183,
      "edges_count": 182,
      "rooted": true,
      "source_reachable": true,
      "root_asset_id": 3231
    },
    {
      "component_id": "C15-02",
      "nodes_count": 9,
      "edges_count": 8,
      "rooted": false,
      "source_reachable": false,
      "root_asset_id": null
    }
  ],
  "line_sections": [
    {
      "id": "SEC-15-001",
      "semantic_type": "LINE_SECTION",
      "component_id": "C15-01",
      "source_node_id": 3231,
      "target_node_id": 3188,
      "member_node_ids": [3231, 3300, 3188],
      "member_edge_ids": [4, 5],
      "span_count": 2
    }
  ],
  "nodes": [
    {
      "asset_id": 3231,
      "code": "AST-KOTA-BNJRKMNTRN-JTM-070",
      "name": "BANJARKEMANTRAN_99",
      "topology_role": "SOURCE_INCOMER",
      "device_role": "SOURCE_INCOMER",
      "equipment_type": "TM11",
      "operational_state": "NOT_APPLICABLE",
      "degree": 1,
      "component_id": "C15-01",
      "source_reachable": true,
      "parent_asset_id": null,
      "child_asset_ids": [3300],
      "depth_from_source": 0
    },
    {
      "asset_id": 3188,
      "code": "AST-KOTA-BNJRKMNTRN-JTM-028",
      "name": "BANJARKEMANTRAN_28",
      "topology_role": "BRANCH_NODE",
      "device_role": "SWITCH_CANDIDATE",
      "equipment_type": "PMS",
      "operational_state": "UNKNOWN_OPERATIONAL_STATE",
      "degree": 3,
      "component_id": "C15-01",
      "source_reachable": true,
      "parent_asset_id": 3300,
      "child_asset_ids": [3187, 3238],
      "depth_from_source": 2
    }
  ],
  "edges": [ ... ],
  "gtt": { ... },
  "firewall": { ... }
}
```

---

## 5. Automated Regression & Test Results

Executed complete test suite across all 4 layers via PHPUnit:

```bash
# 1. SLD-03 Semantic Classification Service Test
vendor/bin/phpunit tests/unit/SldSemanticClassificationServiceTest.php --no-coverage
# Output: OK (14 tests, 2015 assertions)

# 2. SLD-02 Topology Read Model Service Test
vendor/bin/phpunit tests/unit/SldTopologyReadModelServiceTest.php --no-coverage
# Output: OK (19 tests, 1518 assertions)

# 3. SLD-01 Phase 0 Forensic Reconciled Test
vendor/bin/phpunit tests/unit/SldForensicPhase0Test.php --no-coverage
# Output: OK (14 tests, 142 assertions)

# 4. TL-01 All-Network Coverage Forensic Test
vendor/bin/phpunit tests/unit/AllNetworkCoverageForensicTest.php --no-coverage
# Output: OK (13 tests, 502 assertions)
```

**Grand Total Verification**: **60 tests, 4,177 assertions, 0 errors, 0 failures (100% GREEN)**.

---

## 6. Absolute Stop Gate Enacted

In strict accordance with Mandate SLD-03:
* 🔒 **Zero visual rendering code introduced**: No SVG, canvas, layouting algorithms, pixel coordinates, or CSS.
* 🔒 **Zero operational state guessing**: No OPEN/CLOSE guessing, no switching simulation, no SCADA emulation.
* 🔒 **Zero database writes**: Invariant $\Delta = 0$ strictly preserved (`INSERT=0, UPDATE=0, DELETE=0, TRUNCATE=0, ALTER=0, DROP=0`).

The SLD engine now possesses a mathematically verified and semantically classified network hierarchy, ready for human review before proceeding to layout planning (SLD-04).
