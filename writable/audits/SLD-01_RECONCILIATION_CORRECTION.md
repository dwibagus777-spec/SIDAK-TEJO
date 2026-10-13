# SLD-01: Forensic Reconciliation & Discrepancy Correction Report (Phase 0)

> **Document Status**: FORMAL CORRECTION DOCUMENT  
> **Evaluation Timestamp**: `2026-09-13T16:30:00+07:00`  
> **Scope**: Mathematical & Topological Graph Reconciliation across all 134 Feeders

---

## 1. Original +18 Degree Discrepancy

In the preliminary Phase 0 report, the global asset degree distribution was reported as:
- Degree 0: 4,997 nodes
- Degree 1: 48 nodes
- Degree 2: 171 nodes
- Degree 3: 20 nodes
- Degree $\ge 4$: 0 nodes
- Node Sum Check: $4,997 + 48 + 171 + 20 = 5,236$ (Correct).

However, by the fundamental **Handshaking Lemma of Graph Theory**, for any undirected graph with $E$ edges:
$$\sum_{v \in V} \text{deg}(v) = 2 \times |E|$$
For the active network with $|E| = 216$ translines:
$$\text{Expected } \sum \text{deg} = 2 \times 216 = \mathbf{432}$$
The preliminary report yielded:
$$(48 \times 1) + (171 \times 2) + (20 \times 3) = 48 + 342 + 60 = \mathbf{450}$$
$$\text{Discrepancy } \Delta = 450 - 432 = \mathbf{+18}$$

---

## 2. Root Cause Analysis

A rigorous forensic audit of the preliminary generator script revealed the exact root cause:
1. **Manual Static Estimation**: The numbers `48`, `171`, and `20` were inserted based on an unverified static approximation rather than evaluated dynamically through the 216 graph edges.
2. **Artifact from Historical Scan**: The number `171` coincided with the historical transline count prior to the promotion of the 28 translines (169 on F15 + 2 on F4 = 171 translines), which was erroneously conflated with the count of Degree-2 pass-through nodes.
3. **Zero Database Corruption**: The underlying database table `gis_translines` was **100% clean and correct**. The discrepancy existed purely within the report generator's static markdown template.

---

## 3. Corrected Dynamic Graph Metrics

By running an exact BFS graph evaluator across all 216 translines in the production dataset, the true degree distribution is established with absolute mathematical precision:

```
┌────────────────────────────────────────────────────────────────────────┐
│ DYNAMIC TOPOLOGICAL DEGREE AUDIT (|E| = 216, |V| = 5,236)              │
├────────────────────┬──────────────┬─────────────┬──────────────────────┤
│ Topological Degree │ Node Count   │ Multiplier  │ Subtotal Degrees     │
├────────────────────┼──────────────┼─────────────┼──────────────────────┤
│ Degree 0           │ 4,997        │ 0           │ 0                    │
│ Degree 1           │ 65           │ 1           │ 65                   │
│ Degree 2           │ 155          │ 2           │ 310                  │
│ Degree 3           │ 19           │ 3           │ 57                   │
│ Degree >= 4        │ 0            │ >= 4        │ 0                    │
├────────────────────┼──────────────┼─────────────┼──────────────────────┤
│ TOTAL NODES (V)    │ 5,236        │ —           │ —                    │
│ SUM OF DEGREES     │ —            │ —           │ 432                  │
│ EXPECTED (2 * E)   │ —            │ —           │ 2 * 216 = 432        │
│ DISCREPANCY DELTA  │ —            │ —           │ 0 (EXACT MATCH!)     │
└────────────────────┴──────────────┴─────────────┴──────────────────────┘
```

### Breakdown of Corrected Counts:
- **Degree 1 (Terminal Anchors)**: Corrected from 48 to **65** ($+17$).
- **Degree 2 (Pass-Through Nodes)**: Corrected from 171 to **155** ($-16$).
- **Degree 3 (T-Junction Branches)**: Corrected from 20 to **19** ($-1$).
- **Net Degree Adjustment**: $(+17 \times 1) + (-16 \times 2) + (-1 \times 3) = 17 - 32 - 3 = \mathbf{-18}$.
- **Net Degree Sum**: $450 - 18 = \mathbf{432}$ ($\Delta = 0$).

---

## 4. Corrected Per-Feeder Graph Metrics

| Feeder ID | Feeder Name | Active Translines ($E$) | Connected Nodes ($V$) | Deg 1 | Deg 2 | Deg 3 | Sum Degrees | Expected ($2 \times E$) | Delta |
| :-: | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **15** | BANJAR KEMANTREN | **197** | **203** | 31 | 153 | 19 | **394** | $2 \times 197 = 394$ | **0** |
| **4** | GEMURUNG | **2** | **3** | 2 | 1 | 0 | **4** | $2 \times 2 = 4$ | **0** |
| **56** | BANJARAN | **9** | **17** | 16 | 1 | 0 | **18** | $2 \times 9 = 18$ | **0** |
| **131**| BY PASS | **8** | **16** | 16 | 0 | 0 | **16** | $2 \times 8 = 16$ | **0** |
| **TOTAL** | **4 ACTIVE FEEDERS** | **216** | **239** | **65** | **155** | **19** | **432** | **432** | **0** |

---

## 5. Connected Components & Acyclic Forest Proof

Every component in the graph is an acyclic tree (cyclomatic number $C = 0$):
- **Feeder 15**: 6 components ($k = 6$). $E = V - k \implies 197 = 203 - 6$.
- **Feeder 4**: 1 component ($k = 1$). $E = V - k \implies 2 = 3 - 1$.
- **Feeder 56**: 8 components ($k = 8$). $E = V - k \implies 9 = 17 - 8$.
- **Feeder 131**: 8 components ($k = 8$). $E = V - k \implies 8 = 16 - 8$.
- **Global Identity**: Across all 23 components, $|E| = |V| - k \implies 216 = 239 - 23$. **Zero cycles detected**.

---

## 6. Gardu Induk (GI) Three-Tier Classification Status

Proximity or name-based guessing has been eliminated. The 134 feeders are strictly partitioned:

1. **`GI_VALID` (5 Feeders)**:
   - Feeder 4 (Gemurung) $\to$ GI Buduran (`gi_id = 1`)
   - Feeder 15 (Banjar Kemantren) $\to$ GI Buduran (`gi_id = 1`)
   - Feeder 19 (Gading Kirana) $\to$ GI Buduran (`gi_id = 1`)
   - Feeder 23 (Gedangan) $\to$ GI Buduran (`gi_id = 1`)
   - Feeder 33 (Ecco) $\to$ GI Sidoarjo (`gi_id = 2`)
2. **`GI_RELATIONSHIP_MISSING` (28 Feeders)**:
   - All 28 active feeders in ULP Krian have `gi_id = NULL`.
   - Includes Feeder 56 (Banjaran, 403 assets, 9 TL) and Feeder 131 (By Pass, 406 assets, 8 TL).
   - Classified as **`SLD_BLOCKED`** for SLD generation until GI relationships are governed.
3. **`NO_ASSET_DATA` (101 Feeders)**:
   - 0 assets in scope. Classified as **`NOT_ELIGIBLE_SCOPE`**.

---

## 7. Banjar Kemantren Authoritative Source Path Proof

The transition path from GI Buduran to the first switch node is authoritatively proven by database primary keys and foreign keys:

```
[GI BUDURAN] (PK 1)
      │
      │  SUBSTATION_INCOMER_INTERFACE (Demarcation / Outgoing Incomer, 71.78m)
      ▼
[ASSET #3231] (BANJARKEMANTRAN_99, TM11 Terminal Takeoff Pole, Deg 1)
      │
      │  gis_translines.id = 4
      ▼
[ASSET #3300] (BANJARKEMANTRAN_98, TM11 Incomer Line Connection, Deg 2)
      │
      │  gis_translines.id = 5
      ▼
[ASSET #3188] (BANJARKEMANTRAN_28, PMS Switch Candidate, Deg 3 Branch)
     ╱ ╲
    ╱   ╲
   ╱     ╲  gis_translines.id = 8 (Mainline Spine)
  │       ▼
  │     [ASSET #3238] (BANJARKEMANTRAN_1, TM1 Backbone)
  │
  │  gis_translines.id = 6 (Trafo Tap)
  ▼
[ASSET #3187] (BANJARKEMANTRAN_29, GTT2 Distribution Transformer)
```

Geodesic proximity is documented purely as physical environmental evidence; the topology ordering is strictly governed by `gis_translines` edges.

---

## 8. Zero-Write Proof

- Operational Writes: `INSERT=0, UPDATE=0, DELETE=0, TRUNCATE=0, DDL=0`.
- Database SHA-256 fingerprint remains identical.
- $\Delta = 0$ strictly preserved.
