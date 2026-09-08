# TL-01 — CRITICAL NETWORK / TEMUAN DATA SAFETY HARD INVARIANT

**Document Version**: 1.0.0  
**Effective Date**: 2026-09-04  
**Sub-Gate**: TL-01 Hard Invariant Specification  
**Status**: 🔒 **SEALED & PERMANENT**  

---

## 1. Mandatory Business Semantics

```
                    TL-01 NETWORK TOPOLOGY
                              │
                      ┌───────▼────────┐
                      │ Candidate Engine│
                      └───────┬────────┘
                              │
                         ASSETS ONLY
                              │
                     ┌────────┴────────┐
                     ▼                 ▼
                 ASSET A             ASSET B
                     │                 │
                     └───────┬─────────┘
                             ▼
                         TRANSLINE

===============================================================
                    TEMUAN (INSPECTION FINDING)
                              │
                  ┌───────────┴───────────┐
                  ▼                       ▼
             Finding Data            Inspection Context
                  │                       │
                  │                       │
                  └──────────┬────────────┘
                             ▼
                        GIS DISPLAY
                    (Layer Terpisah)
```

1. **TRANSLINE = JTM NETWORK CONNECTIVITY ONLY**:
   `gis_translines` strictly represents physical network connectivity between JTM pole/asset nodes (`ASSET ↔ ASSET`).
2. **TEMUAN IS NOT A NETWORK NODE**:
   `temuan` represents independent field inspection findings (coordinates, photos, priority, status).
   `temuan` is NOT an asset, NOT a network node, and NOT a transline endpoint.
3. **DOMAIN SEPARATION**:
   Under no circumstances may `temuan` records be injected into the network graph topology, candidate generation engine, or `gis_translines` table.

---

## 2. Allowed vs. Forbidden Endpoints

### Allowed Endpoints:
```
SOURCE ASSET (assets.id) ───────── TARGET ASSET (assets.id)
                          TRANSLINE
```
Both endpoints must exist in `assets`, must be distinct (`source != target`), and must belong to the authorized feeder scope.

### Forbidden Endpoints:
- `ASSET → TEMUAN` ❌ **FORBIDDEN**
- `TEMUAN → ASSET` ❌ **FORBIDDEN**
- `TEMUAN → TEMUAN` ❌ **FORBIDDEN**
- `TEMUAN → TRANSLINE` ❌ **FORBIDDEN**
- `TEMUAN → TOPOLOGY NODE` ❌ **FORBIDDEN**

---

## 3. Domain-Typed Endpoint Resolution (No ID Collision Bug)

Canonical endpoint mapping:
$$\text{source\_asset\_id} \to \text{assets.id}$$
$$\text{target\_asset\_id} \to \text{assets.id}$$

**Numeric ID Collision Rule**:
If `temuan.id = 400` and `assets.id = 400` coexist, the endpoint `400` MUST NOT be rejected merely because an entry with ID 400 exists in `temuan`. Endpoint validity is evaluated exclusively within the `assets` domain.

Positive Invariants:
$$\forall t \in \text{gis\_translines}: t.\text{source\_asset\_id} \in \text{assets.id} \land t.\text{target\_asset\_id} \in \text{assets.id}$$
$$\forall p \in \text{gis\_transline\_proposals}: p.\text{source\_asset\_id} \in \text{assets.id} \land p.\text{target\_asset\_id} \in \text{assets.id}$$
$$\forall c \in \text{candidates}: c.\text{source\_asset\_id} \in \text{assets.id} \land c.\text{target\_asset\_id} \in \text{assets.id}$$

---

## 4. Candidate Generation Firewall (`TranslineCompletionService`)

Candidate generation MUST query `assets` as the sole endpoint source.
Strictly Forbidden:
- `temuan UNION`
- `temuan JOIN`
- Nearest finding fallback
- Finding spatial clustering for line generation
- Finding coordinate $\to$ topology node conversion
- Finding coordinate $\to$ transline endpoint conversion

Spatial proximity of findings to lines or assets is strictly labeled:
> **`CONTEXTUAL PROXIMITY ONLY`**  
It exerts **ZERO influence** on candidate scoring, edge classification, or topology graph construction.

---

## 5. D4A Workbench Inspection Context Semantics

The Exception Review Workbench may expose nearby findings for situational operator awareness.
Every exposed finding MUST include explicit non-node metadata:
```json
{
  "finding_id": 175,
  "context_type": "INSPECTION_CONTEXT",
  "proximity_label": "CONTEXTUAL PROXIMITY ONLY",
  "is_topology_node": false,
  "is_transline_endpoint": false,
  "affects_topology": false
}
```

The Focus Map only highlights `ASSET A ───────── ASSET B`. It never renders polylines passing through temuan coordinates as intermediate or terminal vertices.

---

## 6. Critical Historical Data Safety & Anti-Destructive Doctrine

### Incident Memory:
> **Historical Incident**: Previously, saved finding records (`temuan`) and pole points were lost due to destructive GIS rebuild / topology reset processes. **This pattern is permanently outlawed.**

### Absolute Prohibitions:
1. **NO TRUNCATE**: `TRUNCATE temuan`, `TRUNCATE assets`, `TRUNCATE sections`, `TRUNCATE penyulang` are forbidden.
2. **NO GLOBAL DELETE**: `DELETE FROM temuan`, `DELETE FROM assets`, `DELETE FROM gis_translines` as global rebuilds are forbidden.
3. **ADDITIVE ONLY**: All TL-01 topology operations are strictly additive, scoped, reversible, and auditable.
4. **PROTECTED ASSET FIELDS**: `assets.section_id` and `assets.construction_type_id` mutations = 0.

### Strong Temuan Preservation Invariant:
Before and after any topology read, review, scan, or candidate generation:
- `COUNT(temuan)`: Identical ($\Delta = 0$)
- `PRIMARY KEY SET(temuan)`: Exactly identical set
- `IDENTIFIER FINGERPRINT(temuan)`: Identical SHA-256 hash

---

## 7. Operational Gate Status

| Sub-Gate / Component | Operational Status | Enforcement |
|---|:---:|:---:|
| **D4A UI & Read Model** | 🟢 **SEALED** | Pure read-only workbench, drawer, and focus maps |
| **D4B Operational Mutations** | 🔒 **STRICTLY LOCKED** | Zero confirmation, zero rejection, zero DB writes |
| **Production Write Gate** | 🔒 **LOCKED** | `INSERT=0, UPDATE=0, DELETE=0, DDL=0, MIGRATION=0, SEEDER=0` |
| **Temuan Domain Protection** | 🛡️ **HARD FIREWALL** | Independent inspection record, immutable, zero delete |
| **Master Asset Domain** | 🔒 **READ-ONLY** | Master assets unmodified by topology completion |
