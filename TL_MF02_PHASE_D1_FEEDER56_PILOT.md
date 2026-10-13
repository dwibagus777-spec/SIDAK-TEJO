# SIDAK TEJO — TL-MF-02 PHASE D1 CONTROLLED LIVE PILOT REPORT
**Target Feeder**: Feeder #56 (`5130-021` — BANJARAN, ULP Krian)  
**Execution Timestamp**: 2026-09-12 17:14:40 - 17:14:43 WIB (UTC+7)  
**Run ID**: `TL04-RUN-20260912171440-86c557`  
**Governance State**: `PHASE_D1_SEALED` | `HARD_STOP_GATE_ACTIVE`  

---

## 1. Executive Summary

In accordance with operator instructions and the multi-feeder acceleration architecture, **TL-MF-02 Phase D1 Controlled Live Pilot** was executed exclusively on **Feeder #56 (BANJARAN, ULP Krian)**.

Prior to execution, Feeder #56 contained **403 JTM pole assets** with **0 translines** (100% isolated). Server-side pre-execution candidate analysis identified 10 high-confidence candidates. Applying the strict **Multi-Tier Span Firewall**:
- 1 candidate (`TL-NAT:56:5514-5517`, span 6.13m) was held for operator review because its sequence delta $\Delta = 3$ exceeded the strict $\Delta \le 2$ threshold required for spans between 5.0m and 10.0m.
- 9 candidate edges met all 24 safety gates, scoring between 88 and 93, with spans ranging from 13.41m to 54.32m.

Rather than forcing an arbitrary batch of 10, natural defensibility governed the transaction size. A single atomic MariaDB transaction committed exactly **9 defensible translines** (`PK 227` through `PK 235`).

Immediate post-execution cryptographic audits verified:
1. **Zero-Mutation Firewall**: All 7 protected domain tables (`assets`, `gis_transline_proposals`, `temuan`, `temuan_materials`, `sections`, `penyulang`, `ulps`) exhibited $\Delta = 0$ row delta and **100% identical SHA-256 cryptographic signatures**.
2. **INSERT-Only Firewall**: Table `gis_translines` increased by exactly $+9$ physical rows ($216 \to 225$) and $+9$ active rows ($207 \to 216$).
3. **Multi-Feeder Firewall**: Existing translines on Feeder #15 (197 active), Feeder #4 (2 active), and Feeder #131 (8 active) remained 100% untouched.
4. **Hard Stop Gate**: The engine **HALTED IMMEDIATELY**. No continuation to Batch 2 or subsequent queue feeders occurred.

---

## 2. Table Integrity & Cryptographic Audit (8 Domains)

The tables below contrast the pre-pilot state with the post-pilot state, verified against remote production (`https://sidaktejo.site`):

| Domain / Table | Pre Count | Post Count | Delta ($\Delta$) | Pre SHA-256 Hash | Post SHA-256 Hash | Integrity Status |
| :--- | :---: | :---: | :---: | :--- | :--- | :--- |
| `assets` | 5,549 | 5,549 | $+0$ | `d774be4f9d338fcdcdac5f6eac9f4a06f74300e1a293e10a8968c133df799ee9` | `d774be4f9d338fcdcdac5f6eac9f4a06f74300e1a293e10a8968c133df799ee9` | **IMMUTABLE** (Match) |
| `gis_transline_proposals` | 56 | 56 | $+0$ | `74974b866f483ea7fc624c5ffe5f506e85a7a66da4a1e9eb64ee401c8a6d8ca5` | `74974b866f483ea7fc624c5ffe5f506e85a7a66da4a1e9eb64ee401c8a6d8ca5` | **IMMUTABLE** (Match) |
| `temuan` | 638 | 638 | $+0$ | `48ae4d223e842a85ae509eeee49203ee2240cb4517b66a0e11bd3db05bb94a3a` | `48ae4d223e842a85ae509eeee49203ee2240cb4517b66a0e11bd3db05bb94a3a` | **IMMUTABLE** (Match) |
| `temuan_materials` | 0 | 0 | $+0$ | `55f43e2fefa3f34c5c82129161873375891626eb56f668b9da5a6414a3585aae` | `55f43e2fefa3f34c5c82129161873375891626eb56f668b9da5a6414a3585aae` | **IMMUTABLE** (Match) |
| `sections` | 510 | 510 | $+0$ | `9e36669b061f9879a82825e0cb288f5d24cbdafbabdd85cd7075feceef4b621e` | `9e36669b061f9879a82825e0cb288f5d24cbdafbabdd85cd7075feceef4b621e` | **IMMUTABLE** (Match) |
| `penyulang` | 134 | 134 | $+0$ | `cb3ae4e4a8b891cf789fb4505aad2e4d8ae22bf94c9eaacec9fe98777628bb67` | `cb3ae4e4a8b891cf789fb4505aad2e4d8ae22bf94c9eaacec9fe98777628bb67` | **IMMUTABLE** (Match) |
| `ulps` | 3 | 3 | $+0$ | `1c3a2ccc852e80040f88b0a8df52ba4dabd1b36cd56e0ed45b5c9cf6edd3a531` | `1c3a2ccc852e80040f88b0a8df52ba4dabd1b36cd56e0ed45b5c9cf6edd3a531` | **IMMUTABLE** (Match) |
| **`gis_translines`** | **216** | **225** | **$+9$** | `b5fe7d8d83533ab6056584b112b2087a96b157a4d2347aa0e8c55c3d82b4e319` | `d6f4627aa24c9e0215572b0c698166ea851f72bd30d5992530f2781ddc66b530` | **VERIFIED (+9 INSERT only)** |

---

## 3. Feeder-Level Isolation & Global Baseline Tracking

| Feeder ID | Code | Name | ULP | Baseline Active (Pre) | Post-Pilot Active | Delta ($\Delta$) | Status |
| :---: | :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| **#15** | `5130-015` | SUMPUT | ULP Sidoarjo Kota | 197 | 197 | $+0$ | **PRESERVED** (Frozen / Near-Complete) |
| **#4** | `5130-004` | SEPANJANG | ULP Sidoarjo Kota | 2 | 2 | $+0$ | **PRESERVED** (Untouched) |
| **#131** | `5130-099` | BY PASS | ULP Krian | 8 | 8 | $+0$ | **PRESERVED** (Phase C Baseline Intact) |
| **#56** | `5130-021` | BANJARAN | ULP Krian | **0** | **9** | **$+9$** | **COMMITTED** (Phase D1 Live Pilot) |
| *Other 130 Feeders* | - | *Various* | *All ULPs* | 0 | 0 | $+0$ | **PRESERVED** (Zero writes) |
| **GLOBAL ACTIVE** | - | - | - | **207** | **216** | **$+9$** | **RECONCILED** (225 physical, 9 soft-deleted) |

---

## 4. Exact-PK Provenance Matrix (Feeder #56 Committed Batch)

All 9 inserted rows were captured directly from MariaDB `insert_id` and verified against database primary keys:

| MariaDB PK | Transline Code | Natural Edge Key | Source Asset Code (ID) | Target Asset Code (ID) | Distance | Score | Type | Actor / Audit Provenance |
| :---: | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :--- |
| **227** | `TL-56-5512-5513` | `TL-NAT:56:5512-5513` | `AST-KRN-BANJARAN-JTM-005` (5512) | `AST-KRN-BANJARAN-JTM-004` (5513) | 52.59 m | 93 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **228** | `TL-56-5513-5514` | `TL-NAT:56:5513-5514` | `AST-KRN-BANJARAN-JTM-006` (5513) | `AST-KRN-BANJARAN-JTM-005` (5514) | 37.07 m | 93 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **229** | `TL-56-5903-5904` | `TL-NAT:56:5903-5904` | `AST-KRN-BANJARAN-JTM-396` (5903) | `AST-KRN-BANJARAN-JTM-395` (5904) | 52.98 m | 93 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **230** | `TL-56-5611-5613` | `TL-NAT:56:5611-5613` | `AST-KRN-BANJARAN-JTM-105` (5611) | `AST-KRN-BANJARAN-JTM-103` (5613) | 13.41 m | 92 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **231** | `TL-56-5541-5543` | `TL-NAT:56:5541-5543` | `AST-KRN-BANJARAN-JTM-035` (5541) | `AST-KRN-BANJARAN-JTM-033` (5543) | 54.32 m | 90 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **232** | `TL-56-5509-5744` | `TL-NAT:56:5509-5744` | `AST-KRN-BANJARAN-JTM-236` (5509) | `AST-KRN-BANJARAN-JTM-001` (5744) | 16.09 m | 88 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **233** | `TL-56-5511-5516` | `TL-NAT:56:5511-5516` | `AST-KRN-BANJARAN-JTM-008` (5511) | `AST-KRN-BANJARAN-JTM-003` (5516) | 27.96 m | 88 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **234** | `TL-56-5528-5717` | `TL-NAT:56:5528-5717` | `AST-KRN-BANJARAN-JTM-209` (5528) | `AST-KRN-BANJARAN-JTM-020` (5717) | 24.27 m | 88 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |
| **235** | `TL-56-5529-5610` | `TL-NAT:56:5529-5610` | `AST-KRN-BANJARAN-JTM-102` (5529) | `AST-KRN-BANJARAN-JTM-021` (5610) | 21.29 m | 88 | ISOLATED_CHAIN_SEGMENT | `ENGINEER_TRANSLINE_AI\|RUN:TL04-RUN-20260912171440-86c557` |

---

## 5. Multi-Tier Span Firewall & Semantic Review Log

Under the governance rules established in TL-MF-02, spans in the $5.0\text{m} - 10.0\text{m}$ range require sequential evidence $\Delta \le 2$ and mainline coherence to be committed automatically; otherwise, they must be held for explicit operator review.

| Natural Key | Source Asset | Target Asset | Span (m) | Seq Delta | Firewall Verdict | Rationale |
| :--- | :--- | :--- | :---: | :---: | :---: | :--- |
| `TL-NAT:56:5514-5517` | `AST-KRN-BANJARAN-JTM-009` (BANJARAN BARU_01) | `AST-KRN-BANJARAN-JTM-006` (BANJARAN BARU_09) | 6.13 m | 3 | **HELD FOR REVIEW** | Distance is within $5.0\text{m} - 10.0\text{m}$ but sequence delta is $3 > 2$. Excluded from automatic commit to prevent inadvertent substation/riser false-positives. |

---

## 6. Hard Stop Gate Enforcement

```
======================================================================
🛑 HARD STOP GATE STATUS: ACTIVE & ENFORCED
- Feeder #56 Phase D1 live batch execution: COMPLETE
- Total translines inserted in batch: 9
- Remaining queue feeders (e.g. Feeder #60 WONOAYU, Feeder #132, etc.): LOCKED
- Orchestrator state: HALTED
- Next Action: Awaiting explicit operator authorization
======================================================================
```

No further writes to any table or any feeder will occur without explicit operator instruction.
