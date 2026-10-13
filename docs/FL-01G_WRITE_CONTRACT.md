# FL-01G — Write Contract Specification

**Document Version**: `FL-01G-1.0`  
**Status**: 🟢 **SPECIFICATION APPROVED (FL-01G-A)**  
**Gate Position**: **FL-01G-A (Write Contract & Persistence Safety Gate)**  
**Downstream Write Execution**: 🔒 **FL-01G-B+ (Schema Migration & DB Write) STRICTLY LOCKED**

---

## 1. Executive Summary & Architectural Transition

The transition from **Phase FL-01F** to **Phase FL-01G** represents the fundamental boundary between the **Analytical Read-Only Domain** and the **Operational Write Domain**:

```
═════════════════════════════════════════════════════════════════════════════════
   FL-01A..FL-01F: ANALYTICAL READ-ONLY DOMAIN (SEALED, Δdb = 0)
   - Function: ANALYZE & RESOLVE
   - Failure Mode: In-memory UNRESOLVED / BRANCH_AMBIGUITY
   - Side Effects: Strictly 0 mutations, 0 SQL writes, 0 table creations
═════════════════════════════════════════════════════════════════════════════════
                                   │
                                   ▼
   [ FL-01G-A PERSISTENCE SAFETY GATE (CURRENT GATE) ]
   - Validates Write Contract, Fingerprint, Ambiguity Preservation, Immutability
═════════════════════════════════════════════════════════════════════════════════
                                   │ (LOCKED UNTIL FL-01G-B+)
                                   ▼
   FL-01G-B..FL-01G-H: OPERATIONAL WRITE DOMAIN
   - Function: COMMIT & AUDIT
   - Consequences: Database Persistence, GIS/SLD Visualization, Operator Dispatch
═════════════════════════════════════════════════════════════════════════════════
```

### Core Invariant of FL-01G:
FL-01G **does NOT make new engineering or analytical decisions**. It strictly consumes and commits the authoritative decisions rendered by **FL-01F**, preserving all hypotheses, evidence provenance, and topological ambiguity.

---

## 2. Prohibition of Flat Single-Record Schema

Persisting fault events into a traditional flat table (e.g. `fault_records` with single columns `asset_id`, `fault_lat`, `fault_lng`) is **strictly forbidden**:
- A flat record structurally forces the engine to pick a single "winner" location.
- Under topological branch ambiguity (`BRANCH_AMBIGUITY`), saving a single pole or coordinate fabricates certainty that physical telemetry cannot justify.
- A flat record destroys evidence provenance and prevents operators from auditing why an event was resolved to a particular branch.

### Approved Relational Data Model (1:N:N):
```
┌─────────────────────────────────────────────────────────────┐
│                        fault_events                         │
│  (Header: Event Identity, Telemetry, Analytical Resolution) │
└──────────────┬──────────────────────────────────────────────┘
               │ 1
               │
               │ N
┌──────────────▼──────────────────────────────────────────────┐
│                      fault_hypotheses                       │
│     (Candidates: Spatial Coordinates, Topology Spans)       │
└──────────────┬──────────────────────────────────────────────┘
               │ 1
               │
               │ N
┌──────────────▼──────────────────────────────────────────────┐
│                       fault_evidence                        │
│   (Traceable Audit Catalog: Provenance, Status, Snapshots)  │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Logical Data Model & Schema Specifications

### Table 1: `fault_events` (Canonical Fault Event Header)

Captures the authoritative telemetry, canonical event fingerprint, and the analytical resolution state produced by FL-01F.

| Column Name | Data Type | Nullable | Description & Constraints |
| :--- | :--- | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto-increment primary key. |
| `event_fingerprint` | `VARCHAR(64)` | NO | Deterministic SHA-256 hash across canonical event identity fields. **UNIQUE INDEX**. |
| `penyulang_id` | `INT UNSIGNED` | NO | Foreign key reference to `penyulang.id`. |
| `ftu_id` | `VARCHAR(50)` | NO | Identifier of the originating FTU device. |
| `fault_time` | `DATETIME` | NO | Recorded timestamp of fault inception from FTU telemetry. |
| `fault_type` | `VARCHAR(20)` | NO | Classified fault type (e.g. `A-G`, `B-G`, `C-G`, `A-B`, `B-C`, `C-A`, `3P`). |
| `ia` | `DECIMAL(10,3)` | NO | Phase A fault current in Amperes. |
| `ib` | `DECIMAL(10,3)` | NO | Phase B fault current in Amperes. |
| `ic` | `DECIMAL(10,3)` | NO | Phase C fault current in Amperes. |
| `voltage` | `DECIMAL(10,3)` | YES | System voltage at inception in kV. |
| `impedance` | `DECIMAL(10,4)` | YES | Authoritative fault loop impedance ($Z$) in Ohms. |
| `source_distance` | `DECIMAL(10,2)` | NO | Distance reported by FTU measurement in meters. |
| `distance_unit` | `VARCHAR(10)` | NO | Unit of measurement (`METERS`). |
| `resolution_state` | `VARCHAR(50)` | NO | Analytical state from FL-01F: `SINGLE_CONFIDENT_HYPOTHESIS`, `MULTIPLE_COMPATIBLE_HYPOTHESES`, `EQUALLY_PROBABLE`, `BRANCH_AMBIGUITY`, `UNRESOLVED`, `NO_VALID_HYPOTHESIS`. |
| `selected_hypothesis_id` | `VARCHAR(100)` | **YES** | Identifier of the winning hypothesis. **MUST BE NULL** if `resolution_state != SINGLE_CONFIDENT_HYPOTHESIS`. |
| `operational_status` | `VARCHAR(30)` | NO | Operational lifecycle status: `RECEIVED`, `VALIDATED`, `PERSISTED`, `ACKNOWLEDGED`, `CLOSED`. Default: `PERSISTED`. |
| `parser_version` | `VARCHAR(20)` | NO | Engine version of FL-01B (`FL-01B-1.0`). |
| `validator_version` | `VARCHAR(20)` | NO | Engine version of FL-01C (`FL-01C-1.0`). |
| `traversal_version` | `VARCHAR(20)` | NO | Engine version of FL-01D (`FL-01D-1.0`). |
| `projection_version` | `VARCHAR(20)` | NO | Engine version of FL-01E (`FL-01E-1.0`). |
| `resolution_version` | `VARCHAR(20)` | NO | Engine version of FL-01F (`FL-01F-1.0`). |
| `raw_payload` | `TEXT` | NO | Normalized raw FTU message payload for complete auditability. |
| `created_at` | `DATETIME` | NO | Timestamp of database persistence commit. |

---

### Table 2: `fault_hypotheses` (Multi-Branch Candidate Hypotheses)

Stores every candidate path preserved by FL-01D and projected by FL-01E.

| Column Name | Data Type | Nullable | Description & Constraints |
| :--- | :--- | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto-increment primary key. |
| `fault_event_id` | `BIGINT UNSIGNED` | NO | Foreign key reference to `fault_events.id` (ON DELETE RESTRICT). |
| `hypothesis_id` | `VARCHAR(100)` | NO | Canonical hypothesis key (e.g. `HYP-C1-PATH1-TL10`). |
| `component_id` | `INT UNSIGNED` | NO | Connected component identifier from FL-01A topology. |
| `path_id` | `VARCHAR(100)` | NO | Deterministic path key (`PATH_C{c}_R{r}_T{t}`). |
| `transline_id` | `BIGINT UNSIGNED` | NO | Foreign key reference to authoritative `gis_translines.id`. |
| `upstream_asset_id` | `BIGINT UNSIGNED` | NO | Foreign key reference to upstream asset in span. |
| `downstream_asset_id` | `BIGINT UNSIGNED` | NO | Foreign key reference to downstream asset in span. |
| `segment_length_m` | `DECIMAL(10,2)` | NO | Authoritative topological segment length in meters. |
| `offset_m` | `DECIMAL(10,2)` | NO | Distance offset along segment from upstream asset in meters. |
| `segment_ratio` | `DECIMAL(8,6)` | NO | Authoritative bridge ratio $[0.0, 1.0]$. |
| `distance_from_root_m`| `DECIMAL(10,2)` | NO | Cumulative traversal distance from substation anchor. |
| `fault_lat` | `DECIMAL(11,8)` | YES | WGS84 Latitude of projected fault point. |
| `fault_lng` | `DECIMAL(11,8)` | YES | WGS84 Longitude of projected fault point. |
| `wkt_point` | `VARCHAR(100)` | YES | WKT representation `POINT(lon lat)`. |
| `geometry_status` | `VARCHAR(50)` | NO | Status from FL-01E (`PROJECTED`, `GEOMETRY_ORIENTATION_AMBIGUOUS`, etc.). |
| `topology_status` | `VARCHAR(50)` | NO | Status from FL-01D (`SINGLE_PATH_MATCH`, `BRANCH_AMBIGUITY`, etc.). |
| `hypothesis_state` | `VARCHAR(30)` | NO | Evaluated state from FL-01F (`ACTIVE`, `EXCLUDED`). |
| `exclusion_reason` | `TEXT` | YES | Explanation if hypothesis was excluded by authoritative contradiction. |
| `is_selected` | `TINYINT(1)` | NO | `1` if this hypothesis is the single confident winner, `0` otherwise. |
| `created_at` | `DATETIME` | NO | Persistence commit timestamp. |

> [!IMPORTANT]
> **No Mandatory `estimated_asset_id`**: The physical fault location is a continuous spatial point along an electrical span, NOT a pole. Nearest asset snapping is strictly prohibited in the write contract.

---

### Table 3: `fault_evidence` (Traceable Audit Catalog)

Maintains complete provenance for every evidence item evaluated by FL-01F for each hypothesis.

| Column Name | Data Type | Nullable | Description & Constraints |
| :--- | :--- | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto-increment primary key. |
| `fault_hypothesis_id`| `BIGINT UNSIGNED` | NO | Foreign key reference to `fault_hypotheses.id` (ON DELETE RESTRICT). |
| `evidence_type` | `VARCHAR(50)` | NO | One of 8 standard types (`TOPOLOGY_CONSISTENCY`, `GEOMETRY_CONSISTENCY`, `DISTANCE_CONSISTENCY`, `MEASUREMENT_CONSISTENCY`, `PROTECTION_STATUS`, `RECLOSER_STATUS`, `INSPECTION_FINDING`, `HISTORICAL_FAULT_INDEX`). |
| `evidence_state` | `VARCHAR(30)` | NO | Normalized state (`SUPPORTING`, `CONTRADICTING`, `NEUTRAL`, `UNAVAILABLE`, `UNKNOWN`). |
| `source_domain` | `VARCHAR(50)` | YES | System domain (`TOPOLOGY`, `GIS`, `FTU`, `PROTECTION`, `INSPECTION`). |
| `source_table` | `VARCHAR(50)` | YES | Authoritative origin table (`gis_translines`, `ftu_records`, `temuan`, etc.). |
| `source_id` | `BIGINT UNSIGNED` | YES | Identifier of origin entity. |
| `source_field` | `VARCHAR(50)` | YES | Field evaluated from source entity. |
| `relation_type` | `VARCHAR(50)` | YES | Explicit relationship type (`EXPLICIT_ASSET_LINK`, `EXPLICIT_LOCATION_LINK`, `TOPOLOGY_DERIVED`, `SPATIAL_GEOMETRY`, `MEASUREMENT_VALIDATED`). |
| `evidence_value` | `TEXT` | YES | Serialized evidence payload or measurement value. |
| `details` | `TEXT` | YES | Human-readable explanation and context. |
| `evidence_fingerprint`| `VARCHAR(64)` | NO | SHA-256 hash of evidence item attributes for audit verification. |
| `created_at` | `DATETIME` | NO | Persistence commit timestamp. |

---

### Table 4: `fault_event_audit` (Immutable Forensic Audit Trail)

Tracks all operational interactions, replays, and verifications without modifying event history.

| Column Name | Data Type | Nullable | Description & Constraints |
| :--- | :--- | :---: | :--- |
| `id` | `BIGINT UNSIGNED` | NO | Auto-increment primary key. |
| `fault_event_id` | `BIGINT UNSIGNED` | NO | Foreign key reference to `fault_events.id`. |
| `action` | `VARCHAR(50)` | NO | Action executed: `EVENT_CREATED`, `REPLAY_DETECTED`, `OPERATOR_ACKNOWLEDGED`, `OPERATOR_VERIFIED`, `EVENT_CLOSED`. |
| `actor_id` | `BIGINT UNSIGNED` | YES | User ID performing operational action (NULL for automated engine). |
| `actor_type` | `VARCHAR(30)` | NO | `SYSTEM_ENGINE` or `HUMAN_OPERATOR`. |
| `before_snapshot` | `LONGTEXT` | YES | JSON snapshot of event state prior to action. |
| `after_snapshot` | `LONGTEXT` | YES | JSON snapshot of event state post action. |
| `request_fingerprint` | `VARCHAR(64)` | NO | SHA-256 hash of incoming request context. |
| `ip_address` | `VARCHAR(45)` | YES | Origin IP address. |
| `created_at` | `DATETIME` | NO | Timestamp of audit log entry. |

---

## 4. Strict State Machine & `selected_hypothesis_id` Rules

### Analytical Resolution State vs. Operational Event Status
These two state machines are completely decoupled:

1. **Analytical Resolution State** (Fixed by FL-01F, Immutable):
   - `SINGLE_CONFIDENT_HYPOTHESIS`: Exactly 1 candidate supported by sufficient authoritative evidence.
   - `MULTIPLE_COMPATIBLE_HYPOTHESES`: Multiple surviving hypotheses with no authoritative discriminator.
   - `EQUALLY_PROBABLE`: Multiple surviving hypotheses with identical evidence support profiles.
   - `BRANCH_AMBIGUITY`: Ambiguity directly caused by topological branching on feeder.
   - `UNRESOLVED`: Precondition failed (`ROOT_UNRESOLVED`, `PATH_DISCONTINUOUS`, `DISTANCE_OUT_OF_RANGE`).
   - `NO_VALID_HYPOTHESIS`: All candidate hypotheses contradicted by authoritative evidence.

2. **Operational Event Status** (Lifecycle state managed by human workflow):
   - `RECEIVED`: Telemetry received by gateway.
   - `VALIDATED`: Evaluated through FL-01B..FL-01F pipeline.
   - `PERSISTED`: Committed to database through atomic transaction.
   - `ACKNOWLEDGED`: Operator has reviewed the event and hypotheses.
   - `CLOSED`: Event finalized after field inspection or repair.

### Mandatory Selection Rule:
```
IF resolution_state == 'SINGLE_CONFIDENT_HYPOTHESIS':
    selected_hypothesis_id IS REQUIRED (Must match exactly one active candidate)
ELSE:
    selected_hypothesis_id MUST BE NULL
```

Any write request attempting to persist a non-null `selected_hypothesis_id` when `resolution_state != SINGLE_CONFIDENT_HYPOTHESIS` is **strictly rejected with `WRITE_GATE_REJECT_INVALID_SELECTION`**.

---

## 5. Event Fingerprinting & Replay Defense Contract

### Canonical Identity Formulation:
An event's identity is defined exclusively by physical and telemetry invariants, completely independent of runtime clocks or server processing timestamps:

$$\text{Canonical Payload} = \left\{ \text{penyulang\_id}, \text{ftu\_id}, \text{fault\_time}, \text{fault\_type}, I_a, I_b, I_c, V, Z, \text{source\_distance}, \text{normalized\_raw\_payload} \right\}$$

$$\text{event\_fingerprint} = \text{SHA-256}\left( \text{canonical\_json\_encode}(\text{Canonical Payload}) \right)$$

### Replay Defense Protocol:
1. Before starting a transaction, query:
   ```sql
   SELECT id, operational_status, resolution_state FROM fault_events WHERE event_fingerprint = ?
   ```
2. If record exists:
   - **DO NOT INSERT** duplicate rows.
   - Log `REPLAY_DETECTED` to `fault_event_audit`.
   - Return explicit deterministic response:
     ```json
     {
       "result": "REPLAY_DETECTED",
       "event_id": 142,
       "event_fingerprint": "a3f8c...",
       "message": "Telemetry event has already been persisted. Zero duplicate rows created."
     }
     ```
3. If record does not exist:
   - Proceed to Atomic Transaction.

---

## 6. Atomic Transaction Boundary & Rollback Protocol

Persistence must be executed within an ACID database transaction. Partial persistence (e.g. event inserted without hypotheses) is strictly prohibited:

```
BEGIN TRANSACTION
  │
  ├── 1. Validate Write Contract (Schema, Evidence, Selection Rules)
  ├── 2. Verify Event Fingerprint Uniqueness (FOR UPDATE lock)
  ├── 3. INSERT INTO fault_events (...) RETURNING id
  ├── 4. FOR EACH hypothesis:
  │         INSERT INTO fault_hypotheses (fault_event_id, ...) RETURNING id
  ├── 5. FOR EACH evidence:
  │         INSERT INTO fault_evidence (fault_hypothesis_id, ...)
  ├── 6. Verify Foreign Key Referential Integrity across all inserted rows
  └── 7. INSERT INTO fault_event_audit (action = 'EVENT_CREATED', ...)
COMMIT
```

**Exception Handling**:
If any step throws an error, fails validation, or encounters a constraint violation, the system executes **`ROLLBACK` immediately**. Zero rows are retained in any table.

---

## 7. Immutability Policy & Append-Only Corrections

1. **Strict Immutability of Committed Events**:
   - Standard application users and API endpoints have `SELECT` permission only.
   - `UPDATE` and `DELETE` on `fault_events`, `fault_hypotheses`, and `fault_evidence` are blocked at the database engine level via permission grants.
2. **Append-Only Corrections**:
   - If field crews later verify that a fault on a `BRANCH_AMBIGUITY` event occurred on Hypothesis H2:
     - The original `fault_events` row is **NEVER UPDATED** in-place.
     - A new operational audit record `OPERATOR_VERIFIED` is written to `fault_event_audit` with `verified_hypothesis_id: H2`.
     - The original analytical resolution state (`BRANCH_AMBIGUITY`) remains intact as forensic proof of what the telemetry actually indicated at runtime.

---

## 8. Visualization & Operational Safety Firewalls

1. **GIS Fault Overlay Firewall**:
   - The GIS Fault layer renders candidate points directly from `fault_hypotheses` as an ephemeral GeoJSON `FeatureCollection`.
   - The GIS layer **MUST NEVER** alter or create records in `gis_translines` or `assets`.
2. **SLD Fault Highlight Firewall**:
   - The SLD displays candidate branch markers and span highlights.
   - The SLD layer **MUST NEVER** mutate topological connections, switch statuses, or line geometries.
3. **Work Order (WO) Dispatch Firewall**:
   - Automated creation or dispatch of Work Orders is **strictly prohibited** in FL-01G.
   - Field dispatch requires explicit human review and approval through the operational console.
