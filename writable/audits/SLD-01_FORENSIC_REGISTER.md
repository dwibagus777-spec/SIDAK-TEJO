# SLD-01: Master Network Topology & Forensic Register (Phase 0 — Reconciled)

> **Document Status**: AUTHORITATIVE BASELINE (PHASE 0 RECONCILED)  
> **Governance Mode**: Strict Read-Only ($\Delta = 0$)  
> **Evaluation Timestamp**: `2026-09-13T16:30:00+07:00`  
> **Mathematical Verification**: $\sum \text{Degree} = 2 \times E = 432$ ($\Delta = 0$)

---

## 1. Executive Summary & Physical Graph Model

Phase 0 establishes the algorithmic network topology foundation required to power the **SIDAK TEJO Single Line Diagram (SLD) Engine**.

> [!IMPORTANT]
> **PHYSICAL GRAPH THEORY ARCHITECTURE**:
> The Single Line Diagram is **NOT** a parent-child tree (`Asset -> Transline -> Keypoint`).
> The physical distribution system is modeled strictly as an **Undirected Topology Graph** with equipment roles:
> ```
>        GARDU INDUK (GI / SOURCE ANCHOR)
>                      │
>                  PENYULANG
>                      │
>                   SECTION
>                      │
>             ASSET A (NODE in V)
>                      │
>             TRANSLINE (EDGE in E)
>                      │
>             ASSET B (NODE in V)
> ```
> - **Nodes ($V$)**: Electrical equipment assets in `assets` table.
> - **Edges ($E$)**: Physical spans connecting two assets in `gis_translines` table.
> - **Keypoint**: An operational **ROLE / CLASSIFICATION** of an Asset (e.g. Switch Candidate).
> - **GTT**: A distribution transformer **ASSET NODE** or lateral tap-off.
> - **Source Anchor**: The authoritative Substation (**Gardu Induk / GI**) injection point.

---

## 2. Global UP3 Sidoarjo Authoritative Reconciliation

| Parameter Jaringan | Baseline Otoritatif | Identitas Matematis | Status Audit |
| :--- | :---: | :---: | :---: |
| **Total Penyulang Terdaftar** | **134** | $\sum \text{Penyulang}_{\text{ULP}} = 5 + 28 + 101 = 134$ | **PASS** |
| **Penyulang Aktif Memiliki Aset** | **33** | 5 Kota + 28 Krian + 0 Porong | **PASS** |
| **Penyulang Tanpa Aset (Scope Kosong)** | **101** | 0 Kota + 0 Krian + 101 Porong | **PASS** |
| **Total Gardu Induk Otoritatif (GI)** | **9** | Master Substation Records | **PASS** |
| **Total Aset Fisik Jaringan** | **5.236** | $\sum \text{Aset}_{\text{ULP}} = 997 + 4.239 + 0$ | **PASS** |
| **Aset Koordinat GPS Valid** | **5.236 (100%)** | Zero null / zero `(0,0)` coordinate | **PASS** |
| **Total Bentang Transline Aktif** | **216** | 197 (F15) + 2 (F4) + 9 (F56) + 8 (F131) | **PASS** |
| **Total Aset Terhubung Transline** | **239** | $\sum \text{Connected} = \bigcup \text{Connected}$ (Disjoint) | **PASS** |
| **Total Aset Terisolasi (Derajat 0)** | **4.997** | $5.236 - 239 = 4.997$ | **PASS** |
| **Jumlah Derajat Simpul ($\sum \text{deg}$)** | **432** | $\sum \text{deg} = 2 \times E = 2 \times 216 = 432$ | **PASS ($\Delta=0$)** |
| **Kandidat Saklar Fisik (`PMS` / `PMT`)** | **110** | 22 Kota + 88 Krian + 0 Porong | **PASS** |
| **Gardu Distribusi / Trafo (`GTT`)** | **193** | 98 Kota + 95 Krian + 0 Porong | **PASS** |
| **Tiang Standar Distribusi JTM** | **4.933** | $5.236 - 110 - 193 = 4.933$ | **PASS** |
| **Temuan Sebagai Endpoint Transline** | **0** | Strict Isolation Rule | **PASS** |
| **Mutasi Database Operasional ($\Delta$)** | **0** | `INSERT=0, UPDATE=0, DELETE=0, DDL=0` | **PASS** |

---

## 3. Gardu Induk (GI) Source Anchor Three-Tier Classification

Every feeder evaluated for SLD eligibility must possess an authoritative GI relationship. Proximity-based or name-based guessing is strictly prohibited.

```
┌────────────────────────────────────────────────────────────────────────┐
│ GI SOURCE ANCHOR CLASSIFICATION (134 FEEDERS)                          │
├──────────────────────────┬────────────────────────┬────────────────────┤
│ A. GI_VALID              │ 5 Feeders (3.73%)      │ Assets > 0 + GI OK │
│ B. GI_RELATIONSHIP_MISS  │ 28 Feeders (20.90%)    │ Assets > 0, GI NULL│
│ C. NO_ASSET_DATA         │ 101 Feeders (75.37%)   │ 0 Assets in scope  │
├──────────────────────────┴────────────────────────┴────────────────────┤
│ TOTAL: 5 + 28 + 101 = 134 FEEDERS (100% RECONCILED)                    │
└────────────────────────────────────────────────────────────────────────┘
```

### A. List of GI_VALID Feeders (5 Feeders in ULP Sidoarjo Kota):
1. **Feeder 15 (`BANJAR KEMANTREN`, 205 assets, 197 TL)**: Assigned to **GI BUDURAN (PK 1)** (`gi_id = 1`).
2. **Feeder 4 (`GEMURUNG`, 229 assets, 2 TL)**: Assigned to **GI BUDURAN (PK 1)** (`gi_id = 1`).
3. **Feeder 19 (`GADING KIRANA`, 207 assets, 0 TL)**: Assigned to **GI BUDURAN (PK 1)** (`gi_id = 1`).
4. **Feeder 23 (`GEDANGAN`, 219 assets, 0 TL)**: Assigned to **GI BUDURAN (PK 1)** (`gi_id = 1`).
5. **Feeder 33 (`ECCO`, 137 assets, 0 TL)**: Assigned to **GI SIDOARJO (PK 2)** (`gi_id = 2`).

### B. List of GI_RELATIONSHIP_MISSING Feeders (28 Feeders in ULP Krian):
All 28 active feeders in ULP Krian (including Feeder 56 Banjaran with 9 TL, and Feeder 131 By Pass with 8 TL) have `gi_id = NULL` in the production database. In Phase 0, they are formally classified as **`GI_RELATIONSHIP_MISSING`** and their SLD readiness is classified as **`SLD_BLOCKED`** pending Phase 1 governance normalization.

---

## 4. Reconciled SLD Readiness Classification

| Readiness Status | Feeder Count | Percentage | Technical Criteria |
| :--- | :---: | :---: | :--- |
| **`SLD_READY`** | **1** | **0.75%** | Authoritative GI valid + $\ge 90\%$ transline coverage + endpoints verified. (**Feeder 15 Banjar Kemantren**). |
| **`SLD_PARTIALLY_CONNECTED`** | **1** | **0.75%** | Authoritative GI valid + partial transline coverage ($> 0$ TL). (**Feeder 4 Gemurung**). |
| **`SLD_BLOCKED`** | **28** | **20.90%** | Has assets, but **`GI_RELATIONSHIP_MISSING`** (`gi_id = NULL`). (Includes Feeder 56, Feeder 131, and 26 Krian feeders). |
| **`SLD_ISOLATED_TOPOLOGY`** | **3** | **2.24%** | Authoritative GI valid, full assets, but 0 translines. (**Feeders 19, 23, 33**). |
| **`NOT_ELIGIBLE_SCOPE`** | **101** | **75.37%** | 0 physical assets recorded in SIDAK TEJO. |
| **TOTAL** | **134** | **100.0%** | Exact mathematical identity: $1 + 1 + 28 + 3 + 101 = 134$. |
