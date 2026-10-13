# SLD-04 LAYOUT & COORDINATE ENGINE REPORT
**Document ID**: `SLD-04_LAYOUT_ENGINE.md`  
**Milestone**: Phase SLD-04 (Deterministic Layout & Coordinate Engine)  
**Status**: 🔒 **READY FOR LAYOUT INTEGRITY REVIEW & FINAL SEAL**  
**Audit Date**: 2026-09-15  
**Primary Pilot**: Feeder 15 (`BANJAR KEMANTREN`, PYL-015)  
**Invariant State**: Strict Read-Only ($\Delta = 0$: `INSERT=0, UPDATE=0, DELETE=0, TRUNCATE=0, ALTER=0, DROP=0`)

---

## 1. Executive Summary & Purpose

Milestone **SLD-04** creates the authoritative **Layout & Coordinate Model ("Blueprint Posisi")** for the Single Line Diagram Engine. Building directly on top of SLD-03 (Semantic Network Hierarchy), SLD-04 calculates deterministic, collision-free, integer schematic grid coordinates for every network asset and transline.

In accordance with the system architect's mandate:
> *"SLD-04 bukan membuat gambar. SLD-04 membuat 'blueprint posisi' yang nanti bisa digambar oleh SLD-05."*

SLD-04 strictly refrains from rendering any visual elements (zero SVG, zero Canvas, zero HTML, zero CSS). It operates purely as a mathematical spatial planning engine.

```
+-----------------------------------------------------------------------------------+
|  SLD-03: SEMANTIC NETWORK HIERARCHY & DEVICE CLASSIFICATION [SEALED 🔒]           |
|  - Rooted BFS hierarchy on C15-01 (Incomer Node #3231)                            |
|  - 60 Line Sections (197 spans terpetakan utuh)                                   |
|  - Two-dimensional roles: topology_role vs device_role                            |
|  - Unrooted fragments (C15-02..C15-06) preserved without fake directionality       |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼
+-----------------------------------------------------------------------------------+
|  SLD-04: LAYOUT & COORDINATE ENGINE [App\Services\SldLayoutCoordinateEngineService]|
|                                                                                   |
|  1. SCHEMATIC INTEGER GRID (grid_x, grid_y, layer, routing_slot)                  |
|     - Completely decoupled from geographic GPS coordinates                        |
|                                                                                   |
|  2. THREE DEDICATED LAYOUT ZONES:                                                 |
|     - MAIN_NETWORK           : GI Buduran + C15-01 (X: 0..95, Y: 0..17)           |
|     - UNCONNECTED_COMPONENTS : C15-02..C15-06 rack (X: 0..8, Y: 21..29)           |
|     - ISOLATED_ASSETS        : Assets #3203, #3263 rack (X: 0..2, Y: 32)          |
|                                                                                   |
|  3. DETERMINISTIC TREE LAYOUT ALGORITHM (C15-01):                                 |
|     - Depth-monotonic horizontal progression (X = depth)                          |
|     - Disjoint vertical slot allocation (subtree_size desc, asset_id asc)         |
|     - Node collisions = 0 (205 / 205 unique grid coordinates)                     |
|     - Rooted tree edge crossings = 0 (mathematically planar)                      |
|                                                                                   |
|  4. LINE SECTION GEOMETRIC MAPPING:                                               |
|     - 60 Line Sections mapped to schematic start & end points                     |
|     - Full granular pole and span records preserved                               |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼
+-----------------------------------------------------------------------------------+
|  SLD-05: VISUAL RENDERER ENGINE (FUTURE MILESTONE)                                |
|  [ABSOLUTE STOP GATE ENFORCED: ZERO RENDERING / ZERO SVG / ZERO CSS IN SLD-04]     |
+-----------------------------------------------------------------------------------+
```

---

## 2. Verification of the 10 Mandatory Amendments

| # | Mandat Arsitektur | Realisasi & Bukti Matematis | Status |
|---|---|---|:---:|
| **1** | **Grid Coordinate $\neq$ GPS** | Koordinat skematik (`grid_x`, `grid_y`, `layer`, `routing_slot`) dihitung murni secara diskrit/integer, terpisah total dari GPS (`latitude`, `longitude`). GPS dipertahankan utuh sebagai metadata referensi. | 🟢 PASS |
| **2** | **Root Tetap Node #3231** | Akar pohon `C15-01` strictly berasal dari SLD-03 (`source.takeoff.asset_id = 3231`). Terletak di origin `(grid_x: 0, grid_y: 0, depth: 0)` pada zona `MAIN_NETWORK`. Nol seleksi akar arbitrer. | 🟢 PASS |
| **3** | **Relasi Parent-Child dari SLD-03** | Layout engine tidak menjalankan BFS topologi baru. Seluruh relasi `parent_asset_id` dan `child_asset_ids` pada 205 aset 100% identik dengan SLD-03. | 🟢 PASS |
| **4** | **Edge Crossing = 0 Target Khusus Rooted Tree** | Komponen `C15-01` merupakan pohon planar terbukti ($E = V - 1$, $182 = 183 - 1$). Alokasi interval vertikal disjoint menghasilkan `rooted_tree_edge_crossings = 0`. Fragmen lepas diletakkan di zona terpisah. | 🟢 PASS |
| **5** | **Bukan Penskalaan Jarak Fisik** | Jarak bentang fisik (misal: 8m vs 500m) tidak mempengaruhi jarak skematik. Slot skematik seragam agar mudah dibaca operator. | 🟢 PASS |
| **6** | **Layout Deterministik** | Pengurutan percabangan menggunakan bobot ukuran subtree (`leaf_count` descending) dengan tie-breaker `asset_id` ascending. Repeated run menghasilkan JSON dengan SHA-256 bitwise identik. | 🟢 PASS |
| **7** | **Deteksi Tabrakan Wajib (Collision = 0)** | Evaluasi spasial terhadap ke-205 aset membuktikan tidak ada dua aset yang berbagi koordinat skematik: `node_collisions = 0` (205 koordinat unik). | 🟢 PASS |
| **8** | **LINE_SECTION Dihormati** | Ke-60 Line Section dari SLD-03 dipetakan koordinat awal (`layout.start`) dan akhirnya (`layout.end`). Seluruh tiang dan edge granular tetap utuh. | 🟢 PASS |
| **9** | **Zona Terpisah untuk Unrooted & Isolated** | 3 zona mandiri: `MAIN_NETWORK` ($Y: 0..17$), `UNCONNECTED_COMPONENTS` ($Y: 21..29$), `ISOLATED_ASSETS` ($Y: 32$). Nol garis semu menuju GI. | 🟢 PASS |
| **10** | **Strict Read-Only Invariant ($\Delta = 0$)** | Nol penulisan basis data (`INSERT=0, UPDATE=0, DELETE=0, TRUNCATE=0, ALTER=0, DROP=0`). Tidak ada coordinate caching ke DB. | 🟢 PASS |

---

## 3. Spesifikasi Bounding Box & Partisi Spasial

### Canvas Logis Skematik
* **Unit Koordinat**: `GRID` (Integer schematic slots, bukan piksel, bukan mm).
* **Dimensi Grid Global**:
  $$\text{Grid Width} = 96 \text{ slots}, \quad \text{Grid Height} = 33 \text{ slots}$$
  $$\text{Bounding Box}: X \in [0, 95], \quad Y \in [0, 32]$$

### Rincian 3 Zona Spasial
```
========================================================================================
ZONA 1: MAIN_NETWORK (Penyulang Utama Terhubung GI Buduran)
========================================================================================
Komponen Utama         : C15-01 (Berakar di Node #3231)
Jumlah Aset (Nodes)    : 183
Jumlah Transline (Edges: 182
Rentang Grid X         : 0 s/d 95 (Kedalaman Penelusuran Incomer)
Rentang Grid Y         : 0 s/d 17 (Slot Percabangan Disjoint)
Potensi Tabrakan       : 0 (NOL)
Potensi Persilangan    : 0 (NOL - Planar Crossing-Free)

========================================================================================
ZONA 2: UNCONNECTED_COMPONENTS (Fragmen Hutan Lepas dari GI)
========================================================================================
Komponen Anggota       : C15-02, C15-03, C15-04, C15-05, C15-06
Jumlah Aset (Nodes)    : 20
Jumlah Transline (Edges: 15
Rentang Grid X         : 0 s/d 8
Rentang Grid Y         : 21 s/d 29 (Pemisahan 4 slot di bawah Main Network)
Layout Per Komponen    : Rantai horisontal independen per baris genap

========================================================================================
ZONA 3: ISOLATED_ASSETS (Aset Mandiri Tanpa Konduktor)
========================================================================================
Aset Anggota           : #3203 (KEBOAN ANOM_30), #3263 (BANJARKEMANTRAN_91)
Jumlah Aset (Nodes)    : 2
Jumlah Transline (Edges: 0
Rentang Grid X         : 0 s/d 2 (Spacing 2 slot)
Rentang Grid Y         : 32 (Pemisahan 3 slot di bawah Unconnected Components)
========================================================================================
```

---

## 4. REST API Endpoint Specification

### `GET /api/sld/feeder/{id}/layout`

#### Query Parameters:
* `include_gtt` (boolean, optional, default: `false`): Menyesuaikan metadata presentasi tanpa mengubah graf fisik.

#### Struktur Respons Kanonikal:
```json
{
  "status": "success",
  "scope": {
    "type": "FEEDER",
    "feeder_id": 15
  },
  "coordinate_system": {
    "type": "SCHEMATIC_GRID",
    "unit": "GRID",
    "description": "Pure integer orthogonal layout slots decoupled from geographic coordinates"
  },
  "source": {
    "gi_id": 1,
    "name": "GI BUDURAN",
    "asset_id": 3231,
    "status": "AUTHORITATIVE",
    "interface_type": "SUBSTATION_INCOMER_INTERFACE"
  },
  "canvas": {
    "grid_width": 96,
    "grid_height": 33,
    "bounding_box": { "min_x": 0, "max_x": 95, "min_y": 0, "max_y": 32 }
  },
  "zones": { ... },
  "nodes": [
    {
      "asset_id": 3231,
      "code": "AST-KOTA-BNJRKMNTRN-JTM-070",
      "name": "BANJARKEMANTRAN_99",
      "topology_role": "SOURCE_INCOMER",
      "device_role": "SOURCE_INCOMER",
      "operational_state": "NOT_APPLICABLE",
      "zone": "MAIN_NETWORK",
      "schematic": {
        "grid_x": 0,
        "grid_y": 0,
        "layer": 0,
        "routing_slot": 0
      },
      "geo": {
        "latitude": -7.4240755,
        "longitude": 112.7206915
      }
    }
  ],
  "edges": [
    {
      "transline_id": 4,
      "source_asset_id": 3231,
      "target_asset_id": 3300,
      "route_type": "STRAIGHT_HORIZONTAL",
      "source_grid": { "grid_x": 0, "grid_y": 0 },
      "target_grid": { "grid_x": 1, "grid_y": 0 }
    }
  ],
  "line_sections": [
    {
      "id": "SEC-15-001",
      "source_node_id": 3231,
      "target_node_id": 3188,
      "layout": {
        "start": { "grid_x": 0, "grid_y": 0 },
        "end": { "grid_x": 2, "grid_y": 0 }
      }
    }
  ],
  "diagnostics": {
    "total_nodes": 205,
    "total_edges": 197,
    "node_collisions": 0,
    "rooted_tree_edge_crossings": 0,
    "deterministic_verified": true,
    "zero_writes_verified": true
  }
}
```

---

## 5. Hasil Pengujian Otomatis (31 Acceptance Scenarios)

Eksekusi seluruh test suite via PHPUnit:

```bash
# 1. SLD-04 Layout Coordinate Engine Service Test (31 Scenarios)
vendor/bin/phpunit tests/unit/SldLayoutCoordinateEngineServiceTest.php --no-coverage
# Output: OK (31 tests, 2659 assertions)

# 2. SLD-03 Semantic Classification Service Test
vendor/bin/phpunit tests/unit/SldSemanticClassificationServiceTest.php --no-coverage
# Output: OK (15 tests, 2024 assertions)

# 3. SLD-02 Topology Read Model Service Test
vendor/bin/phpunit tests/unit/SldTopologyReadModelServiceTest.php --no-coverage
# Output: OK (19 tests, 1518 assertions)

# 4. SLD-01 Phase 0 Reconciled Forensic Test
vendor/bin/phpunit tests/unit/SldForensicPhase0Test.php --no-coverage
# Output: OK (14 tests, 142 assertions)

# 5. TL-01 All-Network Coverage Forensic Test
vendor/bin/phpunit tests/unit/AllNetworkCoverageForensicTest.php --no-coverage
# Output: OK (13 tests, 502 assertions)
```

**Grand Total Kumulatif**: **92 tests, 6.845 assertions, 0 errors, 0 failures (100% GREEN)**.

---

## 6. Penegakan Absolute Stop Gate

🔒 **ABSOLUTE STOP GATE ENFORCED**:  
Sesuai instruksi arsitektural mutlak:
* ❌ Tidak ada kode rendering visual: Nol SVG, nol Canvas, nol styling CSS, nol elemen diagram HTML.
* ❌ Tidak ada simulasi kelistrikan: Nol OPEN/CLOSE, nol manuver, nol penentuan arah arus.
* ❌ Tidak ada mutasi atau caching database: $\Delta = 0$.

Engine tata letak sekarang telah siap dan diverifikasi penuh, menunggu review integritas tata letak dari human architect sebelum membuka milestone perancangan visual (**SLD-05 Renderer Engine**).
