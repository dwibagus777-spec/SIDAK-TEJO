# TL-01 Visual Realization: Authoritative Transline GIS Layer Architecture

**Document Version:** 1.0.0  
**Phase:** TL-01 Phase 5 — Visual Transline Realization (Read-Only Implementation Gate)  
**Governance Gate Status:** READ-ONLY REALIZATION ACTIVE (Sub-Gate D4B LOCKED)  
**Operational Mutation Permitted:** ZERO (INSERT = 0, UPDATE = 0, DELETE = 0, DDL = 0)

---

## 1. Executive Summary & Objective

The goal of this phase is to establish a high-fidelity visual window into authoritative 20kV Medium Voltage (JTM) connectivity lines (`gis_translines`) on the SIDAK TEJO GIS Map (`/gis`) in pure read-only mode.

Prior to this phase, translines were stored and confirmed at the database level, but lacked explicit authoritative visual presentation, distinct styling from proposals, domain-typed validation, and user-facing read-only inspectability.

This architecture enforces four user-ratified safety invariants:
1. **Zero Production Mutation:** Sub-Gate D4B remains strictly locked (no confirmation, rollback, reject, or creation).
2. **Hard Network Invariant:** Translines connect strictly `Asset ↔ Asset`. Findings (`temuan`) are inspection context points and can **never** become topology nodes or line endpoints.
3. **No Fallback in Production:** If `gis_translines` has 0 rows for a scope, return an honest empty state (`data: []`), never injecting test/canonical fixtures into production queries.
4. **Authoritative Endpoint Derivation:** Geometry coordinates are derived strictly from authoritative master asset coordinates (`sa.latitude/sa.longitude` and `ta.latitude/ta.longitude`).

---

## 2. End-to-End Architectural Pipeline

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           DATABASE LAYER (Read-Only)                            │
│                                                                                 │
│   gis_translines (Authoritative)      assets (Authoritative Nodes)              │
│   ┌────────────────────────────┐      ┌───────────────────────────────┐         │
│   │ id, transline_code,        │ ───> │ id, kode_asset, nama_asset,   │         │
│   │ source_asset_id,           │      │ latitude, longitude,          │         │
│   │ target_asset_id,           │      │ section_id, penyulang_id      │         │
│   │ conductor_type/size, dist  │      └───────────────────────────────┘         │
│   └────────────────────────────┘                                                │
│                 │                                                               │
│                 │ (LEFT JOIN sa, ta - Zero N+1 Queries)                         │
└─────────────────┼───────────────────────────────────────────────────────────────┘
                  ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                    SERVICE FIREWALL: GisTranslineService                        │
│                                                                                 │
│   1. Domain-Typed Temuan Firewall:                                              │
│      Rejects requests with source_type=TEMUAN or target_type=TEMUAN             │
│   2. Scope Authorization Firewall:                                              │
│      Validates userUlpId against feeder ULP boundary                            │
│   3. Scope & Cross-Feeder Consistency Check:                                    │
│      Validates section_id belongs to penyulang_id                               │
│   4. Anomaly Diagnostics (Non-mutating):                                        │
│      Detects ORPHAN_ENDPOINT, IDENTICAL_ENDPOINTS, MISSING_COORDINATE,          │
│      CROSS_SCOPE_ENDPOINT without deleting or mutating operational rows         │
└─────────────────┼───────────────────────────────────────────────────────────────┘
                  ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                    CONTROLLER LAYER: GisController::apiGetTranslines            │
│                                                                                 │
│   - Route: GET /gis/api-translines?penyulang_id=X&section_id=Y&ulp_id=Z        │
│   - Session-backed ULP permission verification                                  │
│   - HTTP 200 (Success), 403 (Forbidden ULP), 422 (Scope/Temuan Violation)      │
│   - Returns: { success, status, scope, total, data, translines, diagnostics }   │
└─────────────────┼───────────────────────────────────────────────────────────────┘
                  ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                       GIS LEAFLET PRESENTATION WORKSPACE                        │
│                                                                                 │
│   1. translinePolylineLayer (Authoritative):                                    │
│      - Solid #0284c7 (PLN Blue), 3.5px width, round join                        │
│      - 24px invisible hit-layer for precise touch/mouse selection               │
│      - Coordinates derived strictly from Source Asset & Target Asset            │
│   2. Read-Only Transline Inspection Popup:                                      │
│      - Transline Code, Feeder, Section, Source Asset, Target Asset              │
│      - Conductor Type & Size, Length (m), Status: ACTIVE                        │
│      - Badge: 🔒 MODE BACA OTORITATIF (D4B Terkunci)                            │
│      - ZERO mutation buttons (no Confirm, Rollback, Reject, Delete)             │
│   3. Dedicated GIS Topology Legend:                                             │
│      - ━━ Transline JTM (Otoritatif) [#0284c7 solid]                            │
│      - -- Proposal AI (Dashed) [#8b5cf6 dashed]                                 │
│      - ● Master Asset (Node JTM) [#2563eb dot]                                  │
│      - ⚠ Temuan (Konteks Inspeksi) [#eab308 icon]                               │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Strict Domain & Separation Boundaries

| Dimension | Transline Layer (`gis_translines`) | Proposal Layer (`gis_transline_proposals`) | Temuan Layer (`temuan`) |
| :--- | :--- | :--- | :--- |
| **Purpose** | Authoritative active electrical topology | Candidate recommendation queue (D4A) | Field inspection evidence & anomaly records |
| **Connectivity** | Strictly `Asset ↔ Asset` | Strictly `Asset ↔ Asset` (candidates) | NONE (Point entity, zero graph connectivity) |
| **Source Table** | `gis_translines` | `gis_transline_proposals` | `temuan` |
| **Visual Line** | Solid `#0284c7` (3.5px) | Dashed `#8b5cf6` (3px) | NO lines (Only yellow/red warning markers) |
| **Is Topology Node** | No (Edges between nodes) | No (Candidate edges) | **FALSE** |
| **Is Endpoint** | Endpoints are `assets` | Endpoints are `assets` | **FALSE** |
| **Permitted Mutations** | **ZERO (Locked)** | **ZERO (Locked)** | **ZERO (Locked)** |

---

## 4. Resilience & Schema Adaptability

To ensure seamless execution across heterogeneous environments (MariaDB local, Hostinger LiteSpeed remote, SQLite3 in-memory tests):
1. **Dynamic Column Resolution:** The service checks `fieldExists` for optional attributes (`section_id`, `conductor_material`, etc.) before querying.
2. **Schema Independence:** Section scope filtering dynamically pivots between `t.section_id` (if column exists) and `sa.section_id / ta.section_id` on the joined `assets` table.
3. **Graceful Empty State:** Feeder scopes with 0 rows or non-existent feeder IDs return clean empty arrays (`data: []`, `translines: []`, `total: 0`) with HTTP 200, completely eliminating phantom fallbacks.
