# ARSITEKTUR RESOLVER TAXONOMY & MODERNISASI VISUAL IKON GIS (GIS-02)

**Fase:** GIS-02 — Modernisasi Visual Ikon Aset GIS & Resolusi Taksonomi Autentik PLN  
**Status:** **TERVERIFIKASI & DISEAL (ACCEPTANCE COMPLETE)**  
**Target Feeder:** Feeder 15 (`BANJAR KEMANTREN`) — 205 Aset Master, 169 Transline Aktif, 36 Temuan  
**Prinsip Keamanan:** Zero-Write Strict Read-Only Firewall (0 Mutasi Database, 0 Modifikasi Topologi)

---

## 1. Analisis Akar Masalah (Root Cause Analysis)

Pada evaluasi fase GIS-01, ditemukan bahwa visualisasi aset pada kanvas peta kerap menampilkan ikon lingkaran generik atau didominasi oleh `tm1.png` (95+ aset), bahkan peralatan gardu trafo (`GTT2`, `GTT1`) dan saklar (`PMS`) jatuh ke `tm1.png`.

Investigasi forensik membuktikan akar masalah struktural:
1. **Homogenitas Field `jenis_asset`:**  
   Seluruh 205 aset pada Feeder 15 memiliki nilai `assets.jenis_asset = 'JTM'`.
2. **Prioritas Resolver Terbalik (Inverted Evaluation Order):**  
   Pada resolver legacy, pengecekan `in_array($jenis, ['JTM'])` dievaluasi **sebelum** mengevaluasi `construction_type`. Akibatnya:
   - Gardu Trafo Portal 2-Tiang (`GTT2`, 15 aset) langsung dicocokkan ke kategori `JTM` dan jatuh ke `TM_1` (`tm1.png`).
   - Gardu Cantilever 1-Tiang (`GTT` / `GTT1`, 8 aset) jatuh ke `TM_1` (`tm1.png`).
   - Tiang Penegang Tunggal (`TM2`, 7 aset) dan Penegang Ganda (`TM4`, 2 aset) jatuh ke fallback `TM_1`.
3. **Efek Donut Lingkaran Hitam (CSS Halo Clutter):**  
   Elemen pembungkus `.asset-condition-halo` berukuran 32x32px dengan latar belakang solid lingkaran berwarna gelap. Ikon PNG di dalamnya hanya berukuran 28x28px sehingga siluet piktogram tertelan oleh lingkaran luar, tampak seperti "donat".

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           ALUR RESOLVER SEBELUMNYA                              │
│                                                                                 │
│   Asset Data ──> Jenis Asset == 'JTM'? ──[YES]──> Return TM_1 (tm1.png)         │
│                        │                                                        │
│                      [NO]                                                       │
│                        ▼                                                        │
│               Cek construction_type (GTT2, PMS, dll.)  <── TIDAK PERNAH DICAPAI!│
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Arsitektur Resolver Baru (Subtype-First Hierarchy)

Sesuai dengan **Hard Amendment 1, 2, dan 3**, resolver dirombak total di kedua sisi (Backend & Frontend) dengan aturan: **Subtype (`construction_type`) dievaluasi TERLEBIH DAHULU sebelum tipe payung (`jenis_asset`).**

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                            ALUR RESOLVER BARU (GIS-02)                          │
│                                                                                 │
│   Asset Data ──> 1. Normalisasi Subtype: strip spasi/tanda hubung               │
│                        │                                                        │
│                        ▼                                                        │
│                  2. Cek Subtype Engineering (construction_type):                │
│                     • GTT2 / GTT-2   ──> GARDU_GTT2_DIST (gtt2-dist.png)        │
│                     • GTT1 / GTT     ──> GARDU_GTT1_DIST (gtt1-dist.png)        │
│                     • PMS / LBSM     ──> LBSM (lbsm.png)                        │
│                     • TM8 / TMTP     ──> TM_8 (tm8.png)                         │
│                     • TM11 / PERCAB  ──> TM_11 (tm11.png)                       │
│                     • TM10 / AKHIR   ──> TM_10 (tm10.png)                       │
│                     • TM5 / SUDUT    ──> TM_5 (tm5.png)                         │
│                     • TM4            ──> TM_4 (tm4.png)                         │
│                     • TM2            ──> TM_2 (tm2.png)                         │
│                     • TM1 / TUMPU    ──> TM_1 (tm1.png)                         │
│                        │                                                        │
│                   [Tidak Cocok]                                                 │
│                        ▼                                                        │
│                  3. Cek Jenis Asset Payung (JTM, GARDU, SWITCH, SLD)            │
│                        │                                                        │
│                   [Tidak Cocok]                                                 │
│                        ▼                                                        │
│                  4. Explicit Fallback dengan Diagnostic Metadata:               │
│                     isFallback = true, fallbackReason = 'UNKNOWN_SUBTYPE'       │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 2.1 Backend: [`AssetVisualRegistryService.php`](file:///e:/XAMPP/htdocs/SIDAK%20TEJO/app/Services/AssetVisualRegistryService.php)
- Menambahkan key simbol baru: `TM_2`, `TM_4`, `GARDU_GTT2_DIST`, `GARDU_GTT1_DIST`.
- Mengimplementasikan `resolveVisual()` dengan kontrak:
  ```php
  return [
      'symbol_key'    => $symbolKey,
      'icon_url'      => $symbol['png_url'],
      'category'      => $symbol['category'],
      'semantic_role' => $symbol['semantic_role'],
      'is_fallback'   => $isFallback,
      'fallback_reason' => $fallbackReason,
  ];
  ```

### 2.2 Backend Config: [`GisIconConfig.php`](file:///e:/XAMPP/htdocs/SIDAK%20TEJO/app/Config/GisIconConfig.php)
- Menyelaraskan normalisasi unhyphenated codes (`GTT2`, `GTT1`, `PMS`, `TM1`..`TM11`, `TMTP`).
- Mendukung resolusi aman terhadap variasi input lapangan.

### 2.3 Frontend Workspace: [`app/Views/gis/index.php`](file:///e:/XAMPP/htdocs/SIDAK%20TEJO/app/Views/gis/index.php)
- Menggantikan fungsi lama `resolveAssetIconUrl()` dengan fungsi modern:
  `resolveAssetIcon(props, visual)` yang diekspos ke `window.resolveAssetIcon` untuk verifikasi otomatis dan interoperabilitas layer.
- Mengembalikan objek visual lengkap:
  ```javascript
  {
      iconUrl: '...',
      iconKey: '...',
      category: '...',
      semanticRole: '...',
      isFallback: false,
      fallbackReason: null
  }
  ```

---

## 3. Desain Visual Marker & Visual Prominence

Untuk mengatasi masalah "lingkaran donat", hierarki CSS marker disesuaikan secara presisi:

| Parameter | Konfigurasi Lama (GIS-01) | Konfigurasi Baru (GIS-02) | Alasan Teknis |
| :--- | :--- | :--- | :--- |
| **Dimensi Halo (`.asset-condition-halo`)** | 32px x 32px | **38px x 38px** | Memberikan ruang nafas bagi siluet piktogram 34px di dalamnya |
| **Background Halo Normal** | Lingkaran solid gelap (`#1e293b`) | **Transparan (`rgba(0,0,0,0)`)** | Menghilangkan cincin donat hitam yang mengaburkan bentuk icon |
| **Border Halo Normal** | 2px solid semi-transparan | **1.5px solid rgba(255,255,255,0.45)** | Ring tipis elegan tanpa menutupi piktogram |
| **Border Halo Warning/Danger** | 2px solid amber/merah | **2.5px solid #f59e0b / #ef4444** | Status temuan/anomali tetap menyala tajam dan kontras |
| **Dimensi Ikon PNG (`.asset-flat-svg`)** | 28px x 28px | **34px x 34px** | Siluet piktogram peralatan menjadi fokus visual utama |
| **Drop Shadow Ikon** | None | **`drop-shadow(0 2px 4px rgba(0,0,0,0.45))`** | Memberi elevasi 3D agar kontras terhadap peta satelit & OSM |
| **Cluster Threshold** | Zoom $\ge$ 17 uncluster | **`disableClusteringAtZoom: 16`, `maxClusterRadius: 30`** | Membuka identitas visual tiang & trafo lebih awal pada zoom kerja |

---

## 4. Kepatuhan Terhadap 7 Hard Amendments

1. **Forensic Field Inventory (Amendment 1):**  
   Terbukti di lapangan bahwa `assets.jenis_asset` adalah `'JTM'`, namun `construction_type` memuat identitas engineering (`TM1`, `TM8`, `TM11`, `GTT2`, `TM5`, `TM10`, `TM2`, `GTT`, `GTT1`, `PMS`, `TM4`, `TMTP`).
2. **No Blind TM_1 Universal Fallback (Amendment 2):**  
   Ditambahkan flag `{ isFallback, fallbackReason }`. Tingkat fallback pada 205 aset Feeder 15 adalah **0 dari 205 (0.00%)**.
3. **Semantic Mapping Only (Amendment 3):**  
   Pemetaan murni berbasis subtype engineering (`construction_type`). Tidak ada 1 pun asset ID yang di-hardcode.
4. **No Wholesale Rewrite of `index.php` (Amendment 4):**  
   Refactoring dilakukan secara bedah mikro (surgical edit) pada fungsi resolver icon dan styling CSS marker.
5. **Separate Code Unit Tests from Production Zero-Write Proof (Amendment 5):**  
   Unit test dijalankan pada test harness lokal (87/87 pass); pembuktian zero-write dijalankan independen via SHA-256 database fingerprint.
6. **Full Protected Domain Fingerprinting (Amendment 6):**  
   SHA-256 diverifikasi pada 7 domain database pre- vs post-deployment (100% identik).
7. **Actual Browser Icon Verification (Amendment 7):**  
   Verifikasi DOM marker sesungguhnya, ukuran CSS, zoom/pan zero-calls, dan penangkapan 7 screenshot via Chrome CDP.
