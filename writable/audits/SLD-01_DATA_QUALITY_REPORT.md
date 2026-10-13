# SLD-01: Network Data Quality & Geometry Forensic Report (Phase 0 — Reconciled)

> **Document Status**: AUTHORITATIVE BASELINE (PHASE 0 RECONCILED)  
> **Evaluation Timestamp**: `2026-09-13T16:30:00+07:00`  
> **Quality Audit**: GEOMETRY 100% PASS / GI GAPS QUANTIFIED / DEGREE DISCREPANCY RESOLVED

---

## 1. Asset Geometry Quality Audit

Across all 5,236 assets recorded in SIDAK TEJO:

| Quality Dimension | Evaluated Assets | Conforming Records | Defects / Outliers | Pass Rate |
| :--- | :---: | :---: | :---: | :---: |
| **Latitude Range** (`-7.55` to `-7.30`) | 5,236 | 5,236 | 0 | **100.0%** |
| **Longitude Range** (`112.45` to `112.85`)| 5,236 | 5,236 | 0 | **100.0%** |
| **Null / Zero Coordinates** (`(0, 0)`) | 5,236 | 5,236 | 0 | **100.0%** |
| **GPS Coordinate Precision** ($\ge 5$ dec) | 5,236 | 5,236 | 0 | **100.0%** |
| **Primary Key Uniqueness** | 5,236 | 5,236 | 0 | **100.0%** |
| **Feeder ID Foreign Key Integrity** | 5,236 | 5,236 | 0 | **100.0%** |

---

## 2. Identified Data Gaps & Governance Action Plan

### Gap 1: GI Relationship Gap in ULP Krian (28 Feeders)
- **Finding**: While all 5 active feeders in ULP Sidoarjo Kota have valid `gi_id` foreign keys, **all 28 active feeders in ULP Krian have `gi_id = NULL`**.
- **Impact on SLD**: SLD generation cannot begin without an authoritative GI source anchor. Feeders like Banjaran (F56, 9 TL) and By Pass (F131, 8 TL) cannot be rendered until GI relationships are governed.
- **Classification**: **`GI_RELATIONSHIP_MISSING`** $\implies$ SLD Readiness **`SLD_BLOCKED`**.

### Gap 2: Physical Transline Network Coverage (4,997 Isolated Assets)
- **Finding**: Only 4 feeders possess active translines (216 translines total). 4,997 assets remain at Degree 0.
- **Remediation**: Progressive Layer B transline promotion after Phase 0.

### Gap 3: Degree Handshaking Reconciliation (RESOLVED)
- **Finding**: Original $+18$ discrepancy caused by manual estimation is completely resolved. Dynamic calculation confirms $\sum \text{deg} = 432 = 2 \times 216$. $\Delta = 0$.

---

## 3. Temuan Domain Isolation Audit

- **Audit Query**: `SELECT COUNT(*) FROM gis_translines WHERE source_asset_id IN (SELECT id FROM temuan) OR target_asset_id IN (SELECT id FROM temuan)`
- **Result**: Exactly **0**.
- **Governance Confirmation**: Temuan records are completely decoupled from topology.
