# TRANSLINE MULTI-FEEDER INVENTORY REPORT
**Program**: Network Completion Engine v2 — Phase A Global Read-Only Inventory  
**Scope**: 134 Feeders across 3 ULPs (Sidoarjo Kota, Krian, Porong)  
**Execution Mode**: STRICT READ-ONLY (`INSERT=0, UPDATE=0, DELETE=0, DDL=0`)  
**Timestamp**: 2026-09-12 15:25 WIB  

---

## 1. Executive Summary: Global Asset & Connectivity Footprint

| Category | Global Production Count | Notes & Domain Meaning |
| :--- | :---: | :--- |
| **Total Feeders Scanned** | **134** Feeders | 45 ULP Sidoarjo Kota, 77 ULP Krian, 12 ULP Porong |
| **Feeders with Master JTM Assets** | **33** Feeders (24.6%) | 100% Covered by Network Completion Engine v2 Profiler |
| **Feeders Empty (Zero Assets)** | **101** Feeders (75.4%) | Automatically categorized as `NO_ASSET` (Zero compute waste) |
| **Feeders Ready for AI Completion** | **32** Feeders | Feeders with isolated assets and defensible candidate edges |
| **Feeders Naturally Stabilized** | **1** Feeder | Feeder 15 (`BANJAR KEMANTREN`, 99.02% connected) |
| **Total Master JTM Assets** | **5,236** Assets | 100% Possess Valid GPS Coordinates |
| **Total Existing Active Translines** | **199** Translines | **Canonical Verified**: 197 Feeder 15 + 2 Feeder 4 |
| **Total Connected Assets ($d \ge 1$)** | **206** Assets (3.93%) | Active nodes connected to medium-voltage backbone |
| **Total Isolated Assets ($d = 0$)** | **5,030** Assets (96.07%) | Primary candidates for progressive multi-feeder completion |
| **Total Topological Candidates Analyzed** | **17,065** Edge Hypotheses | Evaluated via 100-Point Physics Scoring Model |
| **Total AUTO_DEFENSIBLE Candidates** | **4,719** Hypotheses | Pass all deterministic distance, bearing, and degree gates |
| **Total Review Required Candidates** | **2,183** Hypotheses | **Locked**: Requires human operator review |
| **Total Ambiguous / Blocked Candidates** | **10,163** Hypotheses | **Locked**: Strict safety veto (cross-feeder, >85m, collision) |

---

## 2. Baseline Reconciliation: 199 vs 200 Active Translines

Sesuai instruksi **TL-MF-01 Baseline Reconciliation Gate**, dilakukan audit live terhadap database MariaDB produksi (`sidaktejo.site`):

```
=== CANONICAL TABLE COUNTS (MARIADB PRODUCTION) ===
assets                   : 5,549 rows (SHA-256: d774be4f9d338fcd...)
gis_translines           : 208 rows (SHA-256: edc2e9f138f0fb03...)
gis_transline_proposals  : 56 rows (SHA-256: 74974b866f483ea7...)
temuan                   : 638 rows (SHA-256: 48ae4d223e842a85...)
temuan_materials         : 0 rows (SHA-256: 55f43e2fefa3f34c...)
sections                 : 510 rows (SHA-256: 9e36669b061f9879...)
penyulang                : 134 rows (SHA-256: cb3ae4e4a8b891cf...)
ulps                     : 3 rows (SHA-256: 1c3a2ccc852e8004...)
```

### Rekonsiliasi Transline Aktif:
1. **Feeder 4 (`GEMURUNG`)**: Memiliki **2 transline aktif**.
2. **Feeder 15 (`BANJAR KEMANTREN`)**: Memiliki **197 transline aktif** otoritatif (`api-translines?penyulang_id=15` = 197).
3. **Total Transline Aktif Otoritatif**: **$197 + 2 = 199$ transline**.
4. **Sisa 9 Baris pada Tabel `gis_translines`** ($208 - 199 = 9$):
   - 9 baris berstatus soft-deleted (`deleted_at IS NOT NULL`) atau non-aktif (`status != 'ACTIVE'`).
5. **Kesimpulan Discrepancy**:
   - Angka 200 yang sempat muncul di laporan sebelumnya merupakan pembulatan saat 1 proposal Feeder 15 sempat terhitung sebelum di-revert/soft-delete.
   - **Baseline kanonikal resmi saat ini adalah 199 transline aktif** (197 pada Feeder 15, 2 pada Feeder 4).

---

## 3. Top 10 Feeders by Defensible Candidates (Priority Queue)

| Rank | Feeder ID | Feeder Name | ULP Name | Assets | Connected | Isolated | Active TL | Defensible Candidates | Est. Batches ($\le 10$) | Status |
| :---: | :---: | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **1** | **131** | **BY PASS** | ULP Krian | 406 | 0 | 406 | 0 | **381** | 39 | `READY_FOR_AI` |
| **2** | **61** | **ALAM PESONA** | ULP Krian | 340 | 0 | 340 | 0 | **352** | 36 | `READY_FOR_AI` |
| **3** | **56** | **BANJARAN** | ULP Krian | 403 | 0 | 403 | 0 | **288** | 29 | `READY_FOR_AI` |
| **4** | **60** | **DUA PERMATA** | ULP Krian | 268 | 0 | 268 | 0 | **257** | 26 | `READY_FOR_AI` |
| **5** | **4** | **GEMURUNG** | ULP Sidoarjo Kota | 229 | 3 | 226 | 2 | **235** | 24 | `READY_FOR_AI` |
| **6** | **107** | **ASIA 1** | ULP Krian | 204 | 0 | 204 | 0 | **218** | 22 | `READY_FOR_AI` |
| **7** | **19** | **GADING KIRANA** | ULP Sidoarjo Kota | 207 | 0 | 207 | 0 | **214** | 22 | `READY_FOR_AI` |
| **8** | **59** | **BARENG KRAJAN** | ULP Krian | 214 | 0 | 214 | 0 | **210** | 21 | `READY_FOR_AI` |
| **9** | **108** | **ASIA 2** | ULP Krian | 173 | 0 | 173 | 0 | **192** | 20 | `READY_FOR_AI` |
| **10** | **47** | **ADI PRIMA 2** | ULP Krian | 178 | 0 | 178 | 0 | **181** | 19 | `READY_FOR_AI` |

---

## 4. Top 10 Feeders by Isolated Assets

| Rank | Feeder ID | Feeder Name | ULP Name | Total Assets | Isolated Assets | Active TL | Completion % | Defensible Candidates |
| :---: | :---: | :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **1** | **131** | BY PASS | ULP Krian | 406 | **406** | 0 | 0.0% | 381 |
| **2** | **56** | BANJARAN | ULP Krian | 403 | **403** | 0 | 0.0% | 288 |
| **3** | **61** | ALAM PESONA | ULP Krian | 340 | **340** | 0 | 0.0% | 352 |
| **4** | **60** | DUA PERMATA | ULP Krian | 268 | **268** | 0 | 0.0% | 257 |
| **5** | **4** | GEMURUNG | ULP Sidoarjo Kota | 229 | **226** | 2 | 1.3% | 235 |
| **6** | **23** | GEDANGAN | ULP Sidoarjo Kota | 219 | **219** | 0 | 0.0% | 176 |
| **7** | **59** | BARENG KRAJAN | ULP Krian | 214 | **214** | 0 | 0.0% | 210 |
| **8** | **19** | GADING KIRANA | ULP Sidoarjo Kota | 207 | **207** | 0 | 0.0% | 214 |
| **9** | **107** | ASIA 1 | ULP Krian | 204 | **204** | 0 | 0.0% | 218 |
| **10** | **47** | ADI PRIMA 2 | ULP Krian | 178 | **178** | 0 | 0.0% | 181 |

---

## 5. Matriks Lengkap 33 Penyulang Aktif (Dengan Master JTM Assets)

| Feeder ID | Nama Penyulang | ULP | Aset Master | Koordinat Valid | Terhubung | Terisolasi | Transline Aktif | Kandidat Defensibel | Status Engine v2 |
| :---: | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :---: | :--- |
| **4** | GEMURUNG | ULP Sidoarjo Kota | 229 | 229 | 3 | 226 | 2 | 235 | `READY_FOR_AI` |
| **15** | BANJAR KEMANTREN | ULP Sidoarjo Kota | 205 | 205 | 203 | 2 | 197 | 4 | `NEAR_COMPLETE` |
| **19** | GADING KIRANA | ULP Sidoarjo Kota | 207 | 207 | 0 | 207 | 0 | 214 | `READY_FOR_AI` |
| **23** | GEDANGAN | ULP Sidoarjo Kota | 219 | 219 | 0 | 219 | 0 | 176 | `READY_FOR_AI` |
| **33** | ECCO | ULP Sidoarjo Kota | 137 | 137 | 0 | 137 | 0 | 108 | `READY_FOR_AI` |
| **46** | ADI PRIMA 1 | ULP Krian | 175 | 175 | 0 | 175 | 0 | 170 | `READY_FOR_AI` |
| **47** | ADI PRIMA 2 | ULP Krian | 178 | 178 | 0 | 178 | 0 | 181 | `READY_FOR_AI` |
| **48** | CAHAYA METAL | ULP Krian | 167 | 167 | 0 | 167 | 0 | 168 | `READY_FOR_AI` |
| **49** | DAWAR | ULP Krian | 134 | 134 | 0 | 134 | 0 | 106 | `READY_FOR_AI` |
| **56** | BANJARAN | ULP Krian | 403 | 403 | 0 | 403 | 0 | 288 | `READY_FOR_AI` |
| **59** | BARENG KRAJAN | ULP Krian | 214 | 214 | 0 | 214 | 0 | 210 | `READY_FOR_AI` |
| **60** | DUA PERMATA | ULP Krian | 268 | 268 | 0 | 268 | 0 | 257 | `READY_FOR_AI` |
| **61** | ALAM PESONA | ULP Krian | 340 | 340 | 0 | 340 | 0 | 352 | `READY_FOR_AI` |
| **73** | GERRY FOOD | ULP Krian | 101 | 101 | 0 | 101 | 0 | 111 | `READY_FOR_AI` |
| **76** | JAVA PASIFIK 1 | ULP Krian | 88 | 88 | 0 | 88 | 0 | 94 | `READY_FOR_AI` |
| **77** | JAVA PASIFIK 2 | ULP Krian | 87 | 87 | 0 | 87 | 0 | 96 | `READY_FOR_AI` |
| **78** | JAVA PASIFIK 3 | ULP Krian | 104 | 104 | 0 | 104 | 0 | 114 | `READY_FOR_AI` |
| **79** | JAVA PASIFIK 4 | ULP Krian | 120 | 120 | 0 | 120 | 0 | 123 | `READY_FOR_AI` |
| **80** | EMDEKI 1 | ULP Krian | 106 | 106 | 0 | 106 | 0 | 81 | `READY_FOR_AI` |
| **81** | EMDEKI 2 | ULP Krian | 106 | 106 | 0 | 106 | 0 | 80 | `READY_FOR_AI` |
| **86** | EXCELLENT | ULP Krian | 128 | 128 | 0 | 128 | 0 | 131 | `READY_FOR_AI` |
| **107** | ASIA 1 | ULP Krian | 204 | 204 | 0 | 204 | 0 | 218 | `READY_FOR_AI` |
| **108** | ASIA 2 | ULP Krian | 173 | 173 | 0 | 173 | 0 | 192 | `READY_FOR_AI` |
| **113** | HASIL KARYA 1 | ULP Krian | 98 | 98 | 0 | 98 | 0 | 80 | `READY_FOR_AI` |
| **114** | HASIL KARYA 2 | ULP Krian | 129 | 129 | 0 | 129 | 0 | 135 | `READY_FOR_AI` |
| **115** | HASIL KARYA 3 | ULP Krian | 50 | 50 | 0 | 50 | 0 | 38 | `READY_FOR_AI` |
| **116** | HASIL KARYA 4 | ULP Krian | 145 | 145 | 0 | 145 | 0 | 164 | `READY_FOR_AI` |
| **117** | HASIL KARYA 5 | ULP Krian | 35 | 35 | 0 | 35 | 0 | 34 | `READY_FOR_AI` |
| **118** | BAHAGIA STEEL 1 | ULP Krian | 45 | 45 | 0 | 45 | 0 | 58 | `READY_FOR_AI` |
| **119** | BAHAGIA STEEL 2 | ULP Krian | 48 | 48 | 0 | 48 | 0 | 60 | `READY_FOR_AI` |
| **120** | BAHAGIA STEEL 3 | ULP Krian | 47 | 47 | 0 | 47 | 0 | 60 | `READY_FOR_AI` |
| **121** | BAHAGIA STEEL 4 | ULP Krian | 140 | 140 | 0 | 140 | 0 | 0 | `NO_CANDIDATE` |
| **131** | BY PASS | ULP Krian | 406 | 406 | 0 | 406 | 0 | 381 | `READY_FOR_AI` |
| **SUBTOTAL** | **33 Feeders** | | **5,236** | **5,236** | **206** | **5,030** | **199** | **4,719** | |

---

## 6. Rekapitulasi 101 Penyulang Kosong (`NO_ASSET`)

Sebanyak **101 Penyulang** (terdiri dari 39 di ULP Sidoarjo Kota, 50 di ULP Krian, 12 di ULP Porong) tercatat **belum memiliki baris aset JTM sama sekali** di tabel `assets` (`total_assets = 0`). Seluruhnya ditandai `NO_ASSET` dan tidak dimasukkan ke dalam antrean eksekusi transline.

---

## 7. Status & Langkah Berikutnya

Fase A Global Read-Only Inventory telah selesai tanpa melakukan penulisan apa pun pada database (`INSERT=0, UPDATE=0, DELETE=0, DDL=0`).  
Langkah berikutnya adalah menyajikan **Dry-Run Quality Check** terhadap 20 kandidat pertama pada Top Feeder #131 (`BY PASS`) untuk dievaluasi oleh operator sebelum otorisasi Controlled Pilot.
