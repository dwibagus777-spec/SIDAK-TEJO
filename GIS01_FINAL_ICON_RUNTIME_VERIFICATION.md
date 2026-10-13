# LAPORAN AUDIT RUNTIME ICON ASET & PEMERIKSAAN 24 PNG PLN (GIS-01)
**Sistem:** SIDAK TEJO Enterprise  
**Komponen:** Modul Visualisasi Simbolik & Marker Lapangan  
**Status:** **TERDOKUMENTASI DENGAN TEMUAN TEKNIS (FINDINGS REPORTED)**

---

## 1. Inventaris 24 File PNG Otentik PLN di Server Produksi
Seluruh 24 file PNG otentik PLN hadir pada path `public/assets/gis/icons/` dan merespons dengan HTTP 200 di Hostinger:

| Nama File | Ukuran | Resolusi | Peran Semantik | Status HTTP |
| :--- | :---: | :---: | :--- | :---: |
| `tm1.png` | 119.833 B | 2113x2175 | Tiang TM-1 (Tiang Tumpu Garis Lurus SUTM) | 200 OK |
| `tm2.png` | 167.481 B | 2113x2175 | Tiang TM-2 (Tiang Penegang Tunggal) | 200 OK |
| `tm4.png` | 125.005 B | 2113x2175 | Tiang TM-4 (Tiang Penegang Ganda) | 200 OK |
| `tm5.png` | 139.344 B | 2113x2175 | Tiang TM-5 (Tiang Sudut Kecil / Angle Pole) | 200 OK |
| `tm8.png` | 123.378 B | 2113x2175 | Tiang TM-8 (Gardu Tiang Portal / Trafo Portal) | 200 OK |
| `tm10.png` | 123.794 B | 2113x2175 | Tiang TM-10 (Tiang Akhir / Dead-End) | 200 OK |
| `tm11.png` | 123.576 B | 2113x2175 | Tiang TM-11 (Tiang Percabangan T-Off) | 200 OK |
| `tm11-i3.png` | 126.959 B | 2113x2175 | Tiang TM-11 Isolator Tumpu 3 Arah | 200 OK |
| `gtt1-dist.png` | 144.828 B | 2336x3150 | Gardu Tiang Trafo 1 Tiang (GTT-1 Cantilever) | 200 OK |
| `gtt1-i2.png` | 141.070 B | 2336x3154 | Gardu Tiang Trafo 1 Tiang Tipe I-2 | 200 OK |
| `gtt2-dist.png` | 156.663 B | 2336x3150 | Gardu Tiang Trafo 2 Tiang (GTT-2 Portal) | 200 OK |
| `gtt2-i2.png` | 152.726 B | 2336x3150 | Gardu Tiang Trafo 2 Tiang Tipe I-2 | 200 OK |
| `gi.png` | 170.419 B | 2628x2276 | Gardu Induk (GI) | 200 OK |
| `lbs.png` | 140.900 B | 2160x2161 | LBS Manual (Load Break Switch) | 200 OK |
| `lbsm.png` | 38.422 B | 2133x2129 | LBS Motorized / PMS (Switch Seksi) | 200 OK |
| `pmcb-rec.png` | 109.184 B | 2291x2258 | Recloser / PMCB (Circuit Breaker Otomatis) | 200 OK |
| `co-branch.png` | 24.407 B | 3347x693 | Fuse Cut Out (FCO / Cutout Percabangan) | 200 OK |
| `a3c-70.png` | 5.332 B | 3190x119 | Konduktor A3C 70 mm² | 200 OK |
| `a3c-150.png` | 5.093 B | 3245x119 | Konduktor A3C 150 mm² | 200 OK |
| `a3c-240.png` | 6.551 B | 3245x150 | Konduktor A3C 240 mm² | 200 OK |
| `a3cs-150.png` | 4.559 B | 3226x119 | Konduktor A3CS 150 mm² | 200 OK |
| `a3cs-240.png` | 6.341 B | 3226x234 | Konduktor A3CS 240 mm² | 200 OK |
| `mvtic-150.png`| 146.071 B | 3411x587 | Kabel Tanah MVTIC 150 mm² | 200 OK |
| `xlpe.png` | 150.539 B | 3411x587 | Kabel Tanah XLPE 240 mm² | 200 OK |

---

## 2. Resolusi Aktual pada 205 Aset Feeder 15
Berdasarkan eksekusi `resolveAssetIconUrl()` pada seluruh 205 fitur aset Feeder 15:
- `tm1.png`: **141 aset (68,8%)**
- `tm8.png`: **21 aset (10,2%)**
- `tm11.png`: **17 aset (8,3%)**
- `tm5.png`: **13 aset (6,3%)**
- `tm10.png`: **9 aset (4,4%)**
- `lbsm.png`: **4 aset (2,0%)**
- **Total:** 205 aset.

---

## 3. Analisis Penyebab Visual "Marker Lingkaran"
### Akar Masalah:
1. **Bentuk Dasar Donat Lingkaran:** Seluruh ikon tiang TM PLN (`tm1`, `tm5`, `tm8`, `tm10`, `tm11`) didesain mengikuti standar simbol top-down Single Line Diagram (lingkaran berdiameter 28px dengan variasi simbol di tengahnya).
2. **Halo Ring CSS 32px:** Kelas `.asset-condition-halo` membungkus setiap marker dengan lingkaran bergaris tepi warna (hijau, biru, kuning, merah) berukuran 32px.
3. **Data Taxonomy Mismatch:** Kolom `jenis_asset` pada tabel `assets` seluruhnya bernilai `'JTM'`, sehingga filter `if (type === 'GARDU')` dilewati, menyebabkan transformator tiang (GTT2, GTT1) tidak menampilkan ikon visual gardu `gtt2-dist.png` melainkan tiang `tm8.png` / `tm1.png`.
4. **Clustering:** Pada zoom awal 15, `markerClusterGroup` mengelompokkan marker ke dalam lingkaran hijau/kuning angka.