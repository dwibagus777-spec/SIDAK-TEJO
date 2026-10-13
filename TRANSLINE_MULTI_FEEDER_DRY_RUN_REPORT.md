# TRANSLINE MULTI-FEEDER DRY-RUN REPORT
**Program**: Network Completion Engine v2 — Phase B Controlled Dry-Run
**Timestamp**: 2026-09-12 15:24 WIB
**Mode**: `DRY_RUN` (Strictly Read-Only: `INSERT=0, UPDATE=0, DELETE=0, DDL=0`)
**Authoritative Verification**: Production MariaDB (`sidaktejo.site`)

---

## 1. Global Baseline & Invariant Metrics

| Metric | Canonical Production Value | Status |
| :--- | :---: | :---: |
| **Total Feeders Scanned** | **134** Feeders | VERIFIED ✅ |
| **Active Feeders with JTM Assets** | **33** Feeders | VERIFIED ✅ |
| **Empty Feeders (`NO_ASSET`)** | **101** Feeders | VERIFIED ✅ |
| **Total Master JTM Assets** | **5,236** Assets (100% Valid GPS) | VERIFIED ✅ |
| **Total Connected Assets ($d \ge 1$)** | **206** Assets | VERIFIED ✅ |
| **Total Isolated Assets ($d = 0$)** | **5,030** Assets | VERIFIED ✅ |
| **Total Active Translines** | **199** Translines (197 Feeder 15 + 2 Feeder 4) | **RECONCILED ✅** |
| **Total Database Rows (`gis_translines`)** | **208** Rows (199 active, 9 inactive/deleted) | **RECONCILED ✅** |
| **Total Auto-Defensible Candidates** | **4,719** Hypotheses | IDENTIFIED |
| **Total Review Required Candidates** | **2,183** Hypotheses | HELD (0 Writes) |
| **Total Ambiguous / Blocked Candidates** | **10,163** Hypotheses | LOCKED (0 Writes) |

---

## 2. Top 10 Feeders by Defensible Candidates (Priority Queue)

| Rank | Feeder ID | Feeder Name | ULP | Assets | Connected | Isolated | Active TL | Defensible Cand | Review Cand | Blocked Cand | Est. Batches ($\le 10$) |
| :---: | :---: | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **1** | **131** | **BY PASS** | ULP Krian | 406 | 0 | 406 | 0 | **381** | 202 | 955 | 39 |
| **2** | **61** | **ALAM PESONA** | ULP Krian | 340 | 0 | 340 | 0 | **352** | 167 | 928 | 36 |
| **3** | **56** | **BANJARAN** | ULP Krian | 403 | 0 | 403 | 0 | **288** | 65 | 597 | 29 |
| **4** | **60** | **DUA PERMATA** | ULP Krian | 268 | 0 | 268 | 0 | **257** | 104 | 636 | 26 |
| **5** | **4** | **GEMURUNG** | ULP Sidoarjo Kota | 229 | 3 | 226 | 2 | **235** | 119 | 528 | 24 |
| **6** | **107** | **ASIA 1** | ULP Krian | 204 | 0 | 204 | 0 | **218** | 148 | 498 | 22 |
| **7** | **19** | **GADING KIRANA** | ULP Sidoarjo Kota | 207 | 0 | 207 | 0 | **214** | 109 | 529 | 22 |
| **8** | **59** | **BARENG KRAJAN** | ULP Krian | 214 | 0 | 214 | 0 | **210** | 76 | 452 | 21 |
| **9** | **108** | **ASIA 2** | ULP Krian | 173 | 0 | 173 | 0 | **192** | 136 | 448 | 20 |
| **10** | **47** | **ADI PRIMA 2** | ULP Krian | 178 | 0 | 178 | 0 | **181** | 84 | 328 | 19 |

---

## 3. Top 10 Feeders by Isolated Assets

| Rank | Feeder ID | Feeder Name | ULP | Assets | Isolated Assets | Active TL | Defensible Cand | Completion % |
| :---: | :---: | :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **1** | **131** | **BY PASS** | ULP Krian | 406 | **406** | 0 | 381 | 0.0% |
| **2** | **56** | **BANJARAN** | ULP Krian | 403 | **403** | 0 | 288 | 0.0% |
| **3** | **61** | **ALAM PESONA** | ULP Krian | 340 | **340** | 0 | 352 | 0.0% |
| **4** | **60** | **DUA PERMATA** | ULP Krian | 268 | **268** | 0 | 257 | 0.0% |
| **5** | **4** | **GEMURUNG** | ULP Sidoarjo Kota | 229 | **226** | 2 | 235 | 1.3% |
| **6** | **23** | **GEDANGAN** | ULP Sidoarjo Kota | 219 | **219** | 0 | 176 | 0.0% |
| **7** | **59** | **BARENG KRAJAN** | ULP Krian | 214 | **214** | 0 | 210 | 0.0% |
| **8** | **19** | **GADING KIRANA** | ULP Sidoarjo Kota | 207 | **207** | 0 | 214 | 0.0% |
| **9** | **107** | **ASIA 1** | ULP Krian | 204 | **204** | 0 | 218 | 0.0% |
| **10** | **47** | **ADI PRIMA 2** | ULP Krian | 178 | **178** | 0 | 181 | 0.0% |

---

## 4. Top Priority Feeder Deep Dive: Feeder #131 (`BY PASS`)

- **Feeder ID**: 131
- **Nama Penyulang**: BY PASS
- **Unit Layanan (ULP)**: ULP Krian
- **Total Master JTM Assets**: 406 Aset (100% Memiliki Koordinat GPS Valid)
- **Transline Eksisting**: 0 Transline
- **Aset Terisolasi Awal**: 406 Aset
- **Kandidat Defensibel Teridentifikasi**: **381 Kandidat**
- **Estimasi Batch Transaksi Atomik**: ~39 Batch (Maksimal 10 Transline per Batch)

### Audit Kualitas Human-Auditable: 20 Pasangan Kandidat Pertama

| No | Source Asset (Kode & Nama) | Target Asset (Kode & Nama) | Jarak Rentang | Delta Bearing | Skor Fisika | Kategori Topologi | Bukti Koherensi Jaringan | Gate Valid |
| :---: | :--- | :--- | :---: | :---: | :---: | :---: | :--- | :---: |
| 01 | `AST-KRN-BYPASS-JTM-253 (PSNN248)` | `AST-KRN-BYPASS-JTM-254 (PSNN247)` | 27.82 m | 0° | **94/100** | ISOLATED_PAIR | ISOLATED_LINEAR_CHAIN_MEMBER, CONSECUTIVE_POLE_SEQUENCE, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 02 | `AST-KRN-BYPASS-JTM-262 (PSNN191)` | `AST-KRN-BYPASS-JTM-263 (PSNN193)` | 48.97 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, CONSECUTIVE_POLE_SEQUENCE, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 03 | `AST-KRN-BYPASS-JTM-085 (BYPASS_180)` | `AST-KRN-BYPASS-JTM-087 (BYPASS_181)` | 6.58 m | 0° | **93/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 04 | `AST-KRN-BYPASS-JTM-001 (BYPASS_107)` | `AST-KRN-BYPASS-JTM-096 (BYPASS_106)` | 23.96 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 05 | `AST-KRN-BYPASS-JTM-003 (BYPASS_178)` | `AST-KRN-BYPASS-JTM-285 (PSNN209)` | 1.89 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 06 | `AST-KRN-BYPASS-JTM-004 (PSNN226)` | `AST-KRN-BYPASS-JTM-136 (PSNN225)` | 3.49 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 07 | `AST-KRN-BYPASS-JTM-004 (PSNN226)` | `AST-KRN-BYPASS-JTM-289 (BYPASS_165)` | 24.38 m | 0° | **92/100** | ISOLATED_PAIR | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 08 | `AST-KRN-BYPASS-JTM-007 (PSNN186)` | `AST-KRN-BYPASS-JTM-021 (PSNN185)` | 19.55 m | 0° | **92/100** | ISOLATED_PAIR | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 09 | `AST-KRN-BYPASS-JTM-007 (PSNN186)` | `AST-KRN-BYPASS-JTM-154 (PSNN184)` | 14.38 m | 0° | **92/100** | ISOLATED_PAIR | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 10 | `AST-KRN-BYPASS-JTM-007 (PSNN186)` | `AST-KRN-BYPASS-JTM-328 (PSNN187)` | 3.49 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 11 | `AST-KRN-BYPASS-JTM-010 (PSNN222)` | `AST-KRN-BYPASS-JTM-033 (PSNN221)` | 1.1 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 12 | `AST-KRN-BYPASS-JTM-010 (PSNN222)` | `AST-KRN-BYPASS-JTM-173 (PSNN220)` | 23.83 m | 0° | **92/100** | ISOLATED_PAIR | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 13 | `AST-KRN-BYPASS-JTM-012 (BYPASS_89)` | `AST-KRN-BYPASS-JTM-206 (BYPASS_90)` | 9.64 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 14 | `AST-KRN-BYPASS-JTM-012 (BYPASS_89)` | `AST-KRN-BYPASS-JTM-363 (BYPASS_88)` | 11.76 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 15 | `AST-KRN-BYPASS-JTM-014 (PSNN218)` | `AST-KRN-BYPASS-JTM-305 (PSNN217)` | 13.39 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 16 | `AST-KRN-BYPASS-JTM-016 (BYPASS_185)` | `AST-KRN-BYPASS-JTM-022 (BYPASS_186)` | 11.08 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 17 | `AST-KRN-BYPASS-JTM-016 (BYPASS_185)` | `AST-KRN-BYPASS-JTM-099 (BYPASS_196)` | 9.85 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 18 | `AST-KRN-BYPASS-JTM-021 (PSNN185)` | `AST-KRN-BYPASS-JTM-154 (PSNN184)` | 6.26 m | 0° | **92/100** | ISOLATED_CHAIN_SEGMENT | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 19 | `AST-KRN-BYPASS-JTM-021 (PSNN185)` | `AST-KRN-BYPASS-JTM-328 (PSNN187)` | 22.5 m | 0° | **92/100** | ISOLATED_PAIR | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |
| 20 | `AST-KRN-BYPASS-JTM-022 (BYPASS_186)` | `AST-KRN-BYPASS-JTM-099 (BYPASS_196)` | 19.77 m | 0° | **92/100** | ISOLATED_PAIR | ISOLATED_LINEAR_CHAIN_MEMBER, COHERENT_SECTION_ENVIRONMENT | PASS ✅ |

---

## 5. Evaluasi Kualitas & Kesiapan Eksekusi Pilot

1. **Kualitas Rentang Fisik**: Seluruh 20 kandidat pertama memiliki jarak span antara **1.1 meter hingga 49.0 meter** (jauh di bawah batas plafon 85 meter).
2. **Kontinuitas Seksi & Feeder**: 100% kandidat berada strictly pada Feeder #131 (ULP Krian) dalam seksi yang koheren (0 cross-feeder, 0 cross-ULP).
3. **Kepatuhan Penomoran Sekunder**: Sebagian besar kandidat memperlihatkan nomor urut berurutan (misal: PSNN248 $\to$ PSNN247, PSNN191 $\to$ PSNN193, BYPASS_180 $\to$ BYPASS_181) yang mengonfirmasi fisik lapangan surveyor.
4. **Mekanika Anti-Saturasi**: Tiang-tiang yang memiliki beberapa opsi tetangga (seperti PSNN186) akan terkunci secara alami setelah batch pertama menghubungkannya, mencegah percabangan liar melebihi kapasitas derajat simpul ($d \le 4$).

---

## 6. STATUS GATE: MENUNGGU PERSETUJUAN OPERATOR

- **Phase A (Inventory & Reconcile)**: **SELESAI (PASS)**
- **Phase B (Dry Run & Quality Audit)**: **SELESAI (PASS)**
- **Phase C (Controlled Pilot - Max 10 Translines on ONE Feeder)**: **TERKUNCI (Menunggu Otorisasi Operator)**
- **Zero Database Writes**: Terjaga 100% (`INSERT=0, UPDATE=0, DELETE=0, DDL=0`).
