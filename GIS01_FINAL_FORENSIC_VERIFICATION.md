# LAPORAN FORENSIK FINAL GIS-01: VERIFIKASI KINERJA, INTEGRITAS DATA & AUDIT RUNTIME
**Sistem:** SIDAK TEJO Enterprise (PLN UP3 Sidoarjo)  
**Lingkungan:** Live Production (`https://sidaktejo.site/gis`)  
**Penyulang Sasaran:** BANJAR KEMANTREN (Penyulang ID: 15, ULP Sidoarjo Kota)  
**Tanggal Verifikasi:** 11 September 2026  
**Status Gate:** **PASS_WITH_FINDINGS** (Performa & Keselamatan Data Lolos Penuh; Temuan Visual Marker Dicatat)

---

## 1. Verifikasi Deployment & Sinkronisasi Repositori
| Atribut | Nilai Terverifikasi | Status |
| :--- | :--- | :---: |
| **Git HEAD Local** | `089313db53949423251dd212076b861b008db98c` | SINKRON |
| **Git HEAD origin/main** | `089313db53949423251dd212076b861b008db98c` | SINKRON |
| **Production Running Commit** | `089313db53949423251dd212076b861b008db98c` | SINKRON |
| **Status Deploy Hostinger** | HTTP 200 (Auto-deploy & OPcache purged) | AKTIF |
| **Integritas Working Tree** | Bersih (Tidak ada modifikasi tak terduga pada tracked files) | TERVERIFIKASI |

---

## 2. Forensic Baseline Database & Rekonsiliasi Transline (169 vs 168)
Pertanyaan kunci mengenai perbedaan angka **169 total vs 168 aktif** telah diselidiki secara mendalam hingga ke level baris database:

### A. Hasil Audit Baris Nyata Tabel `gis_translines`
- **Total Baris:** `169`
- **Baris Aktif (`is_active = 1`):** `169`
- **Baris Tidak Aktif (`is_active = 0` / NULL):** `0` (NOL)
- **Status Distribution:** `ACTIVE` = `169` (100%)
- **Penyulang ID Distribution:** `15` = `169` (100%)
- **Daftar ID Inaktif:** Tidak ada (`NONE`).

### B. Distribusi Provenance (Asal-Usul) 169 Transline:
1. **Transline Manual (TL-01 Baseline):** 43 jalur
   - `Administrator`: 42 jalur
   - `DWI BAGUS ARIANTO`: 1 jalur
2. **Transline Rekonstruksi AI TL-02 (Progressive Loop):** 56 jalur
3. **Transline Rekonstruksi AI TL-03 (Re-Score):** 2 jalur
4. **Transline Rekonstruksi AI TL-04 (Accelerated Progressive):** 27 jalur
5. **Transline Rekonstruksi AI (Network Completion Engine v1):** 41 jalur
   - **Total Kumulatif:** $43 + 56 + 2 + 27 + 41 = 169$ jalur.

### C. Penjelasan Forensik Angka "168":
> **FAKTA FORENSIK:** Tidak pernah ada baris ke-169 yang berstatus non-aktif atau dibatalkan di database.  
> Angka "168" murni berasal dari teks prompt awal pengguna (*"±205 aset dan 168 transline"*). Pada saat agen AI sebelumnya melakukan snapshot baseline sebelum fase optimasi GIS-01 dimulai, file `scratch/perf_pre_fingerprint.json` mencatat baseline aktual adalah **169 translines** dengan hash SHA-256:  
> `1c18cf9a3588eb2716da8c149860a09e3e7f5b8edd184c659d53df2f334fc68f`.  
> Agen sebelumnya keliru mengutip frasa teks prompt "168 aktif" sebagai pelengkap narasi "169 total / 168 aktif", padahal secara riil database berisi **169 transline aktif dan valid**. Perbandingan hash membuktikan dataset transline 100% identik bit-per-bit.

---

## 3. Hasil Pengujian Kinerja Browser Riil (Chrome CDP Benchmark)
Pengujian performa dieksekusi secara otomatis pada Chrome Headless menggunakan instrumentasi native WebSocket DevTools Protocol (CDP) terhadap `https://sidaktejo.site/gis`:

| Parameter Pengujian | Target / Ekspektasi | Hasil Terukur Riil | Kepatuhan |
| :--- | :--- | :--- | :---: |
| **Initial Page Load** | < 3000 ms | **1.623 ms** | MEMENUHI |
| **Elemen SVG `<path>` Transline** | 0 (Dihilangkan dari DOM) | **0 elemen** | MEMENUHI |
| **Canvas Vector Renderer (`L.canvas`)** | 1 instance aktif | **1 canvas aktif** | MEMENUHI |
| **Total DOM Nodes** | Berkurang signifikan | **4.152 nodes** | MEMENUHI |
| **Waktu Bingkai (Frame Timing)** | Rata-rata < 20 ms | **16,79 ms** | MEMENUHI |
| **Framerate Riil Terukur (FPS)** | Stabil ~60 FPS | **59,5 FPS** | **TERBUKTI** |
| **Tugas Panjang (> 50 ms)** | 0 kejadian | **0 kejadian** | MEMENUHI |
| **Bingkai Terjatuh (> 33 ms)** | Minimal | **1 bingkai** (dari 234) | MEMENUHI |
| **Permintaan Jaringan saat Zoom x5** | 0 request hidrasi GIS | **0 request** | MEMENUHI |
| **Permintaan Jaringan saat Pan x5** | 0 request hidrasi GIS | **0 request** | MEMENUHI |
| **Uji Kebocoran Memori (20 Siklus)** | Heap stabil (< 15 MB) | **-0,01 MB** (-7.972 bytes) | **STABIL 100%** |

---

## 4. Temuan Kritis Runtime Icon PNG PLN (Section 8)
Sesuai instruksi kontrak forensik: **"Do NOT hide the problem. If a generic marker is still used: REPORT IT."**

### A. Status File Fisik & Konfigurasi Server
- **24 file PNG otentik PLN:** 100% hadir di direktori `public/assets/gis/icons/` dengan status HTTP 200 di server produksi.
- Metadata lengkap (dimensi, MIME, ukuran file, SHA256) terdaftar dalam `scratch/icon_metadata_registry.json`.

### B. Hasil Investigasi Visual & Runtime (Akar Masalah "Tampilan Marker Lingkaran")
Mengapa pengguna melihat **lingkaran kecil berwarna** daripada icon visual peralatan PLN yang mudah dibedakan? Ditemukan 4 faktor teknis:

1. **Efek Leaflet MarkerCluster pada Zoom Rendah/Normal (< 16):**
   - Pada zoom awal level 15, Leaflet MarkerCluster mengelompokkan aset yang berdekatan menjadi cluster bundar (`marker-cluster-small`, warna hijau/kuning dengan angka 6, 14, 18). Akibatnya, 95% aset tidak menampilkan ikon individu sama sekali.
2. **Karakteristik Siluet Icon PNG SLD:**
   - File icon PNG tiang SUTM (`tm1.png`, `tm5.png`, `tm8.png`, `tm10.png`, `tm11.png`) didesain berdasarkan simbol top-down Single Line Diagram PLN standar (`shape: circle-donut`). Gambar di dalamnya berupa lingkaran hitam kecil dengan variasi garis/lengkung di dalamnya (diameter 28px).
3. **Pembungkus CSS Halo Ring (`.asset-condition-halo`):**
   - Setiap marker dibungkus elemen `<span>` halo lingkaran berdiameter 32px dengan border berwarna sesuai kondisi aset (`#10b981` hijau, `#0ea5e9` biru, `#f59e0b` kuning, `#ef4444` merah). Halo lingkaran ini mendominasi visual, sehingga icon di dalamnya terlihat seperti titik di tengah cincin berwarna.
4. **Bypass Cabang Resolusi Icon Gardu (`type === 'GARDU'`):**
   - Pada fungsi `resolveAssetIconUrl(props, visual)` di [index.php:3041](file:///e:/XAMPP/htdocs/SIDAK%20TEJO/app/Views/gis/index.php#L3041), pemilihan icon gardu kaya (`gtt1-dist.png`, `gtt2-dist.png`, `gi.png`) dibatasi oleh kondisi `if (type === 'GARDU')`.
   - Namun pada database produksi Feeder 15, **seluruh 205 aset memiliki `jenis_asset = 'JTM'`**, sedangkan klasifikasi gardu tersimpan di kolom `construction_type` (`GTT2`, `GTT`, `GTT1`).
   - Akibatnya, kondisi `if (type === 'GARDU')` dievaluasi `FALSE`, sehingga aset GTT2/GTT1 gagal menggunakan icon transformator gardu dan jatuh ke `tm8.png` atau fallback `tm1.png` (141 dari 205 aset atau 68,8% memakai `tm1.png`).

---

## 5. Bukti Visual Produksi (Screenshots Terlampir)
Telah diambil 5 screenshot beresolusi tinggi langsung dari Chrome CDP live:
1. `forensic_gis_overview.png` (962 KB) — Ikhtisar jaringan feeder 15 dengan transline canvas dan cluster.
2. `forensic_closeup_pole_tm1.png` (247 KB) — Tampilan dekat tiang lurus TM-1 pada zoom 18.
3. `forensic_closeup_gardu_tm8.png` (273 KB) — Tampilan dekat tiang portal TM-8 pada zoom 18.
4. `forensic_closeup_switch_lbsm.png` (220 KB) — Tampilan dekat pemisah seksi LBSM pada zoom 18.
5. `forensic_closeup_temuan.png` (221 KB) — Tampilan dekat penanda temuan inspeksi (terisolasi penuh).

---

## 6. Audit Keselamatan Database (Zero-Write Audit)
Perbandingan fingerprint SHA-256 database sebelum dan sesudah seluruh pengujian browser:
- **Tabel `assets` (205 baris):**  
  `f837ac1ede9d8fad04c45214f211fa5b21bb9e358410af5d9b9d1fd270fe6d6c` (**100% IDENTIK**)
- **Tabel `gis_translines` (169 baris):**  
  `1c18cf9a3588eb2716da8c149860a09e3e7f5b8edd184c659d53df2f334fc68f` (**100% IDENTIK**)
- **Operasi Menulis (INSERT/UPDATE/DELETE/DDL):** **0 (Strictly Zero)**.

---

## 7. Keputusan Akhir (Verdict)
### **STATUS: PASS_WITH_FINDINGS**

**Alasan Penetapan:**
1. Kinerja rendering vektor berbasis kanvas terbukti secara ilmiah mencapai **59,5 FPS** dengan waktu bingkai rata-rata **16,79 ms** dan 0 tugas panjang.
2. Firewall jaringan saat zoom dan pan terbukti **100% nol request hidrasi**.
3. Database produksi 100% kebal mutasi (zero-write lolos kriptografis).
4. Rekonsiliasi 169 vs 168 transline terbukti tuntas (169 transline aktif).
5. **Temuan Visual (Findings):** Icon peralatan tiang tampak seperti lingkaran kecil akibat desain siluet SLD donut, efek clustering pada zoom < 16, halo ring CSS 32px, dan normalisasi klasifikasi `jenis_asset = 'JTM'`. Temuan ini didokumentasikan secara transparan tanpa modifikasi logika kode pada fase verifikasi ini.