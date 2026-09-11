# LAPORAN VERIFIKASI RUNTIME IKON ASET (GIS-02)

**Fase:** GIS-02 — Modernisasi Visual Ikon Aset GIS  
**Penyulang Uji:** Feeder 15 (`BANJAR KEMANTREN`)  
**Total Aset Master:** 205 Aset  
**Waktu Audit:** 2026-09-11 20:28:42 WIB  
**Audit Source:** Headless Chrome CDP Live DOM & Runtime Resolver Audit (`scratch/gis02_browser_verification_report.json`)

---

## 1. Sensus Lengkap Resolusi Runtime 205 Aset

Berikut adalah hasil evaluasi runtime terhadap seluruh 205 fitur aset pada Feeder 15:

| Subtype Kontruksi | Ikon PNG Terpetakan | Key Simbol | Jumlah Aset | Status Fallback | Peran Semantik PLN | Contoh ID Aset | Contoh Kode Aset |
| :--- | :--- | :--- | :---: | :---: | :--- | :---: | :--- |
| **`TM1`** | `tm1.png` | `TM_1` | 95 | **TIDAK** (`false`) | Tiang Tumpu SUTM | 3328 | `AST-KOTA-BNJRKMNTRN-JTM-167` |
| **`TM8`** | `tm8.png` | `TM_8` | 21 | **TIDAK** (`false`) | Tiang / Gardu Portal | 3337 | `AST-KOTA-BNJRKMNTRN-JTM-176` |
| **`TM11`** | `tm11.png` | `TM_11` | 17 | **TIDAK** (`false`) | Tiang Percabangan T-Tap | 3330 | `AST-KOTA-BNJRKMNTRN-JTM-169` |
| **`GTT2`** | `gtt2-dist.png` | `GARDU_GTT2_DIST` | 15 | **TIDAK** (`false`) | Gardu Trafo Portal 2-Tiang | 3339 | `AST-KOTA-BNJRKMNTRN-JTM-178` |
| **`TM5`** | `tm5.png` | `TM_5` | 12 | **TIDAK** (`false`) | Tiang Sudut Besar | 3333 | `AST-KOTA-BNJRKMNTRN-JTM-172` |
| **`TM10`** | `tm10.png` | `TM_10` | 8 | **TIDAK** (`false`) | Tiang Akhir SUTM | 3335 | `AST-KOTA-BNJRKMNTRN-JTM-174` |
| **`TM2`** | `tm2.png` | `TM_2` | 7 | **TIDAK** (`false`) | Tiang Penegang Tunggal | 3358 | `AST-KOTA-BNJRKMNTRN-JTM-197` |
| **`TM1` (Hint Akhir)** | `tm10.png` | `TM_10` | 6 | **TIDAK** (`false`) | Tiang Akhir (dari petunjuk kode) | 3261 | `AST-KOTA-BNJRKMNTRN-JTM-100` |
| **`GTT`** | `gtt1-dist.png` | `GARDU_GTT1_DIST` | 4 | **TIDAK** (`false`) | Gardu Cantilever 1-Tiang | 3361 | `AST-KOTA-BNJRKMNTRN-JTM-200` |
| **`GTT1`** | `gtt1-dist.png` | `GARDU_GTT1_DIST` | 4 | **TIDAK** (`false`) | Gardu Cantilever 1-Tiang | 3362 | `AST-KOTA-BNJRKMNTRN-JTM-201` |
| **`PMS`** | `lbsm.png` | `LBSM` | 4 | **TIDAK** (`false`) | Pemisah Beban Manual (PMS/LBSM) | 3188 | `AST-KOTA-BNJRKMNTRN-JTM-027` |
| **`TM1` (Hint Cabang)** | `tm11.png` | `TM_11` | 4 | **TIDAK** (`false`) | Tiang Percabangan (dari kode) | 3271 | `AST-KOTA-BNJRKMNTRN-JTM-110` |
| **`TM4`** | `tm4.png` | `TM_4` | 2 | **TIDAK** (`false`) | Tiang Penegang Ganda | 3349 | `AST-KOTA-BNJRKMNTRN-JTM-188` |
| **`TM1` (Hint Penegang)** | `tm2.png` | `TM_2` | 2 | **TIDAK** (`false`) | Tiang Penegang (dari kode) | 3364 | `AST-KOTA-BNJRKMNTRN-JTM-203` |
| **`TMTP`** | `tm8.png` | `TM_8` | 2 | **TIDAK** (`false`) | Tiang Portal TMTP | 3234 | `AST-KOTA-BNJRKMNTRN-JTM-073` |
| **`TM10` (Hint Cabang)** | `tm11.png` | `TM_11` | 1 | **TIDAK** (`false`) | Tiang Percabangan | 3273 | `AST-KOTA-BNJRKMNTRN-JTM-112` |
| **`TM5` (Hint Cabang)** | `tm11.png` | `TM_11` | 1 | **TIDAK** (`false`) | Tiang Percabangan | 3274 | `AST-KOTA-BNJRKMNTRN-JTM-113` |
| **TOTAL** | | | **205** | **0 Fallback (0.00%)** | **Tingkat Akurasi Ikon: 100.0%** | | |

---

## 2. Analisis Perbandingan Sebelum vs Sesudah GIS-02

| Parameter Metrik | Sebelum GIS-02 | Sesudah GIS-02 | Dampak Operasional Lapangan |
| :--- | :---: | :---: | :--- |
| **Aset Jatuh ke `tm1.png`** | 141+ aset (68.8%) | **95 aset (46.3%)** | Hanya tiang tumpu sejati yang menggunakan ikon TM-1 |
| **Gardu Trafo 2-Tiang (`GTT2`)** | 0% (Jatuh ke TM-1) | **100% (15/15) `gtt2-dist.png`** | Petugas inspeksi dapat membedakan tiang biasa vs gardu trafo |
| **Gardu Cantilever (`GTT`/`GTT1`)** | 0% (Jatuh ke TM-1) | **100% (8/8) `gtt1-dist.png`** | Transformator tiang tunggal tampak jelas pada peta |
| **Pemisah Beban Manual (`PMS`)** | 0% (Jatuh ke TM-1) | **100% (4/4) `lbsm.png`** | Titik manuver jaringan langsung teridentifikasi |
| **Tiang Penegang (`TM2` / `TM4`)** | 0% (Jatuh ke TM-1) | **100% (11/11) `tm2`/`tm4.png`** | Struktur penegang kabel terlihat jelas |
| **Total Fallback Tak Diketahui** | Tak Terlacak (Blind) | **0 aset (0.00%)** | Memenuhi Hard Amendment 2 secara sempurna |

---

## 3. Kepatuhan Hard Amendment 2 (No Blind Fallback)

Setiap resolusi visual menyertakan payload diagnostik:
```json
{
  "iconUrl": "/assets/gis/icons/gtt2-dist.png",
  "iconKey": "GARDU_GTT2_DIST",
  "category": "GARDU",
  "semanticRole": "Gardu Trafo Portal 2-Tiang Distribusi (GTT-2)",
  "isFallback": false,
  "fallbackReason": null
}
```
Jika ada tipe konstruksi masa depan yang belum terdefinisi dalam taksonomi, sistem secara eksplisit menandai:
```json
{
  "isFallback": true,
  "fallbackReason": "UNKNOWN_CONSTRUCTION_TYPE"
}
```
Sensus runtime membuktikan bahwa **tidak ada satu pun aset yang mengalami fallback** pada penyulang uji Feeder 15.
