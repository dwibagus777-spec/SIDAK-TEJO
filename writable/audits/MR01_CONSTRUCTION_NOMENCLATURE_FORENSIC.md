# MR-01 Construction Nomenclature Forensic Report

**Gate**: MR-01 CONSTRUCTION NOMENCLATURE FORENSIC (READ $\rightarrow$ COMPARE $\rightarrow$ MAP $\rightarrow$ HUMAN VERIFY $\rightarrow$ DISPLAY LABEL $\rightarrow$ STOP)  
**Status**: 🟢 **FORENSIC AUDIT COMPLETE — ZERO DATABASE MUTATION ENFORCED**  
**Target Domain**: Standard PLN Construction Blueprint (`KONSTRUKSI JTM`, `KONSTRUKSI MVTIC`, `KONSTRUKSI GTT`)  
**Primary Invariants**:
- `construction_type_id`: 🔒 **TIDAK BERUBAH** ($\Delta = 0$)
- `construction_bom_items`: 🔒 **TIDAK BERUBAH** ($\Delta = 0$)
- `master_materials`: 🔒 **TIDAK BERUBAH** ($\Delta = 0$)
- `temuan_materials`: 🔒 **TIDAK BERUBAH** ($\Delta = 0$)
- `assets`: 🔒 **TIDAK BERUBAH** ($\Delta = 0$)
- `temuan`: 🔒 **TIDAK BERUBAH** ($\Delta = 0$)

---

## 1. Problem Statement & Forensic Motivation

Pada implementasi awal, label konstruksi di Material Picker UI menampilkan nama generik yang menghilangkan makna fungsional kelistrikan PLN:
- `TM-4A — Konstruksi Tiang Tarik Sudut TM-4A` (Hilang informasi: Dilengkapi **Lightning Arrester**)
- `TM-5C — Konstruksi Tiang Penegang Tipe C TM-5C` (Hilang informasi: Dilengkapi **Fuse Cut Out / CO**)
- `TM-8C — Konstruksi Tiang Percabangan Tipe C TM-8C` (Hilang informasi: Dilengkapi **Fuse Cut Out / CO**)

Sesuai arahan operator:
1. Nama konstruksi di lapangan harus merepresentasikan **fungsi teknis kelistrikan** dan **ciri pembeda utama** (misal: Arrester, CO/FCO, Line Tap, DS).
2. Sumber kebenaran fisik material tetap berada pada **Bill of Materials (BOM)** sheet `KONSTRUKSI JTM`.
3. Database `construction_types` **TIDAK BOLEH** di-update secara gegabah. Translasi nama teknis ditangani melalui **2-Layer Architecture (Canonical Code + Technical Display Name)** pada service layer.
4. UI Material Picker harus menampilkan kartu 3-tier vertikal (Code, Technical Name, Feature Badge) agar petugas lapangan dapat mengidentifikasi konstruksi dalam **1–2 detik**.

---

## 2. Construction Nomenclature Register Matrix

Berikut adalah hasil komparasi forensik antara kode sheet, nama database eksisting, bukti BOM material, dan rekomendasi nomenklatur teknis PLN:

| Canonical Code | Nama Eksisting DB (Preserved) | Nama Teknis PLN (Technical Name) | Ciri Pembeda (UX Badge) | Bukti Kunci BOM Material |
|:---:|---|---|:---:|---|
| **`TM1`** | Konstruksi Tiang Tumpu Tunggal TM-1 | Konstruksi Tiang Tumpu Lurus | `TIANG TUMPU` | Pin Post Insulator 20 kV (3); Travers UNP 2000 (1) |
| **`TM1A`** | Konstruksi Tiang Tumpu Sudut Kecil TM-1A | Konstruksi Tiang Tumpu dengan Percabangan Tap | `🔀 DENGAN LINE TAP` | Pin Post Insulator 20 kV; Line Tap Connector; Travers UNP 2000 |
| **`TM1C`** | Konstruksi Tiang Tumpu Tipe C TM-1C | Konstruksi Tiang Tumpu Tipe C | `TIANG TUMPU TIPE C` | Pin Post Insulator 20 kV; Konstruksi kantilever C (ROW Sempit) |
| **`TM2`** | Konstruksi Tiang Tumpu Ganda Sudut Sedang TM-2 | Konstruksi Tiang Tumpu Sudut Ganda | `📐 SUDUT KECIL / GANDA` | Pin Post Insulator 20 kV (6); Double Cross Arm UNP 2000 (2) |
| **`TM4`** | Konstruksi Tiang Tarik Awal / Akhir TM-4 | Konstruksi Tiang Tarik Tunggal (Akhir / Awal) | `TIANG TARIK TUNGGAL` | Strain Insulator SIR (3); Strain Clamp 3-Nut; Travers UNP 2000 (1) |
| **`TM4A`** | Konstruksi Tiang Tarik Sudut TM-4A | **Konstruksi Tiang Tarik Sudut dengan Arrester** | `⚡ DENGAN ARRESTER` | Strain Insulator SIR (6); **LIGHTNING ARRESTER 20KV (3)**; MOUNTING LA |
| **`TM5`** | Konstruksi Tiang Penegang / Tension TM-5 | Konstruksi Tiang Penegang (Tension) | `TIANG PENEGANG` | Strain Insulator SIR (6); Double Cross Arm UNP 2000 (2); Pin Post (1) |
| **`TM5C`** | Konstruksi Tiang Penegang Tipe C TM-5C | **Konstruksi Tiang Penegang dengan CO** | `🔌 DENGAN CO` | Strain Insulator SIR (6); **FUSE CUT OUT 24KV (3)**; DUDUKAN FCO |
| **`TM8`** | Konstruksi Tiang Percabangan Sudut TM-8 | Konstruksi Tiang Percabangan | `🔀 PERCABANGAN / T-OFF` | Strain Insulator SIR (6); Pin Post (3); **LINE TAP CONNECTOR** |
| **`TM8C`** | Konstruksi Tiang Percabangan Tipe C TM-8C | **Konstruksi Tiang Percabangan dengan CO** | `🔌 DENGAN CO` | Strain Insulator SIR (6); **LINE TAP CONNECTOR**; **FUSE CUT OUT (3)** |
| **`TM10`** | Konstruksi Tiang Transposisi Fasa TM-10 | Konstruksi Tiang Transposisi Fasa | `TIANG TRANSPOSISI` | Strain Insulator SIR (6); Pin Post (6); Cross Arm UNP 2000 |
| **`TM11`** | Konstruksi Tiang Penegang Lengkap LA TM-11 | **Konstruksi Tiang Penegang dengan Arrester** | `⚡ DENGAN ARRESTER` | Strain Insulator SIR (6); **LIGHTNING ARRESTER 20KV (3)**; Grounding Rod |
| **`TM11-DS`**| Konstruksi Tiang TM-11 dengan DS | **Konstruksi Tiang Penegang dengan Arrester & DS** | `🛡️ DENGAN LA & DS` | Strain Insulator (6); **LA (3)**; **DISCONNECTING SWITCH 24KV (DS)** |
| **`TM11-CO`**| Konstruksi Tiang TM-11 dengan CO | **Konstruksi Tiang Penegang dengan Arrester & CO** | `🛡️ DENGAN LA & CO` | Strain Insulator (6); **LA (3)**; **FUSE CUT OUT 24KV (CO)**; Dudukan FCO |
| **`TM12`** | Konstruksi Tiang Transposisi Tarik TM-12 | **Konstruksi Trafo Portal Akhir Sejajar Jaringan dengan CO** | `🔌 PORTAL DENGAN CO` | Strain Insulator (6); Pin Post (3); **FUSE CUT OUT (3)**; Dudukan FCO |
| **`TM13`** | Konstruksi Tiang Sudut Besar / Terminal TM-13 | **Konstruksi Trafo Portal Akhir dengan CO** | `🔌 PORTAL DENGAN CO` | Strain Insulator (6); Cross Arm UNP 3000 (2); **FUSE CUT OUT (3)** |
| **`TMTP`** | Konstruksi Tiang Tanpa Travers (Vertikal) TM-TP| Konstruksi Tiang Khusus HT Pole | `TIANG KHUSUS HT POLE` | Cross Arm UNP 3000; Besi Siku L 50x50; Tanpa travers standar |
| **`TMMVTIC1`**| Konstruksi Kabel Pilin Udara TM MVTIC-1 | Konstruksi MVTIC Tumpu Lurus | `MVTIC TUMPU` | Suspension Clamp Assembly; Stainless Steel Strip; Stopping Buckle |
| **`TMMVTIC2`**| Konstruksi Kabel Pilin Udara TM MVTIC-2 | Konstruksi MVTIC Sudut Kecil | `📐 MVTIC SUDUT` | Connection Clamp Netral; Strain Clamp 150/240mm; Turn Buckle 3/4; GSW 70mm |
| **`TMMVTIC3`**| Konstruksi Kabel Pilin Udara TM MVTIC-3 | Konstruksi MVTIC Sudut dengan Proteksi PVC | `🛡️ MVTIC SUDUT PROTEKSI` | Arm Tie Band 8"; Separator Clamp Netral; Pipa PVC AW 4"; Strain Clamp |
| **`TMMVTIC4`**| Konstruksi Kabel Pilin Udara TM MVTIC-4 | Konstruksi MVTIC Tarik Akhir (Dead-End Tunggal) | `MVTIC TARIK AKHIR` | Preformed Termination 70mm; Termination Outdoor 24kV 150mm; Turn Buckle; Ground Rod |
| **`TMMVTIC4-DS`**| Konstruksi Kabel Pilin Udara TM MVTIC-4 | Konstruksi MVTIC Tarik Akhir dengan DS | `🎛️ DENGAN DS` | Tension Clamp MVTIC; **DISCONNECTING SWITCH 24KV (DS)**; Dudukan DS |
| **`TMMVTIC4-CO`**| Konstruksi Kabel Pilin Udara TM MVTIC-4 | Konstruksi MVTIC Tarik Akhir dengan CO | `🔌 DENGAN CO` | Tension Clamp MVTIC; **FUSE CUT OUT 24KV (CO)**; Dudukan FCO |
| **`TMMVTIC5`**| Konstruksi Kabel Pilin Udara TM MVTIC-5 (Salaman Atas) | Konstruksi MVTIC Percabangan Sambungan Salaman Atas JTM | `🔀 SALAMAN ATAS JTM` | Cross Arm UNP 100/80; Plat Tembaga Jumper 600x50x5; Pipa PVC 4"; AAAC-S 150 |
| **`TMMVTIC5A`**| Konstruksi Kabel Pilin Udara TM MVTIC-5A | Konstruksi MVTIC Sambungan Percabangan Tipe A | `🔗 SAMBUNGAN TIPE A` | Cross Arm UNP 65-750mm; Jointing Termination 24kV 150mm; Compression Joint Sleeve |
| **`TMMVTIC5B`**| Konstruksi Kabel Pilin Udara TM MVTIC-5B | Konstruksi MVTIC Sambungan Percabangan Tipe B | `🔗 SAMBUNGAN TIPE B` | Cross Arm UNP 65-750mm; Jointing Termination 24kV; Ornament Cable Band 3" |
| **`TMMVTIC10`**| Konstruksi Kabel Pilin Udara TM MVTIC-10 | Konstruksi MVTIC Tiang Penegang Khusus | `MVTIC PENEGANG` | Preformed Termination 70mm; Turn Buckle 3/4"; Strain Clamp 150-240mm |
| **`GTT1`** | Gardu Trafo Tiang 1 Tiang (Cantol) | Gardu Distribusi Tiang Cantol (1 Tiang) | `⚡🔌 CANTOL DENGAN LA & CO` | Trafo Cantol 50-160 kVA; **LIGHTNING ARRESTER (3)**; **FUSE CUT OUT (3)** |
| **`GTT2`** | Gardu Trafo Tiang 2 Tiang (Portal) | Gardu Distribusi Tiang Portal (2 Tiang) | `⚡🔌 PORTAL DENGAN LA & CO` | Trafo Portal 200-400 kVA; **LIGHTNING ARRESTER (3)**; **FUSE CUT OUT (3)** |

---

## 3. Two-Layer Architecture Design

```
+-------------------------------------------------------------+
| Layer 1: Canonical Code (Machine & Model Layer)             |
|   - TM4A                                                    |
|   - TM5C                                                    |
|   - TM8C                                                    |
+-------------------------------------------------------------+
                              │
                              ▼
+-------------------------------------------------------------+
| Layer 2: Canonical Display Name (Human & Field UX Layer)    |
|   - TM4A — Konstruksi Tiang Tarik Sudut dengan Arrester     |
|   - TM5C — Konstruksi Tiang Penegang dengan CO              |
|   - TM8C — Konstruksi Tiang Percabangan dengan CO           |
+-------------------------------------------------------------+
                              │
                              ▼
+-------------------------------------------------------------+
| UX Card Rendering (1–2 Seconds Fast Recognition in Mobile)   |
|   ┌─────────────────────────────────────────────────────┐   |
|   | [TM4A]                         [⚡ DENGAN ARRESTER]  |   |
|   | Konstruksi Tiang Tarik Sudut dengan Arrester        |   |
|   | JTM • Standar Konstruksi PLN                        |   |
|   └─────────────────────────────────────────────────────┘   |
+-------------------------------------------------------------+
```

---

## 4. Analisis BOM Pendukung (Ground Truth)

1. **TM4A (Tiang Tarik Sudut dengan Arrester)**:
   - BOM sheet baris 227 s/d 258:
     - `LIGHTNING ARRESTER 20KV` (3 set)
     - `MOUNTING BRACKET LA` (3 set)
     - `COOPER TUBE 35mm` (Out LA)
     - `TERMINAL LUG 35mm` (Skun LA)
     - `STRAIN ROD INSULATOR` (6 set)
   - **Kesimpulan**: Penamaan *"Konstruksi Tiang Tarik Sudut dengan Arrester"* memiliki dukungan material 100% pada BOM.

2. **TM5C (Tiang Penegang dengan CO)**:
   - BOM sheet baris 285 s/d 311:
     - `STRAIN ROD INSULATOR` (6 set)
     - `CROSS ARM UNP 100 - 2000 mm (DUDUKAN FCO)`
     - `FUSE CUT OUT 24KV` (3 set)
   - **Kesimpulan**: Varian 'C' pada TM-5 menandakan penambahan dudukan dan saklar proteksi *Cut Out* (CO).

3. **TM8C (Tiang Percabangan dengan CO)**:
   - BOM sheet baris 338 s/d 368:
     - `LINE TAP CONNECTOR` (komponen percabangan saluran udara)
     - `STRAIN ROD INSULATOR` (6 set)
     - `CROSS ARM UNP 100 - 2000 mm (DUDUKAN FCO)`
     - `FUSE CUT OUT 24KV` (3 set)
   - **Kesimpulan**: Varian 'C' pada TM-8 menandakan percabangan lateral yang dilengkapi saklar *Cut Out* (CO).

4. **GTT1 (Gardu Distribusi Tiang Cantol - 1 Tiang)**:
   - Sheet `KONSTRUKSI GTT` baris 1 s/d 66:
     - `Pondasi type A ( 1 tiang )` (baris 64, Pole Block)
     - `PHB TR 2 JURUSAN` (baris 54, LV Panel 250A - 2 Jurusan)
     - `Polymer Arrester 24 kV - 10 kA` (baris 12, 3 buah)
     - `Polymer Cut Out Switch 24 kV + Fuse` (baris 12, 3 buah)
   - **Kesimpulan**: GTT1 secara fisik merupakan **Gardu Distribusi Tiang Cantol (1 Tiang)** dengan panel PHB-TR 2 jurusan untuk trafo distribusi kapasitas 50 - 160 kVA.

5. **GTT2 (Gardu Distribusi Tiang Portal - 2 Tiang)**:
   - Sheet `KONSTRUKSI GTT` baris 67 s/d 131:
     - `Pondasi type D ( 2 Tiang )` (baris 129, Pole Block)
     - `PHB TR  4 JURUSAN  400 A - 2 PINTU` (baris 120, LV Panel 400A / 250A)
     - `Polymer Arrester 24 kV - 10 kA` (baris 78, 3 buah)
     - `Polymer Cut Out Switch 24 kV + Fuse` (baris 78, 3 buah)
   - **Kesimpulan**: GTT2 secara fisik merupakan **Gardu Distribusi Tiang Portal (2 Tiang)** dengan panel PHB-TR 4 jurusan untuk trafo distribusi kapasitas 200 - 400 kVA.
   - **Prinsip Isolasi BOM**: BOM GTT1 dan GTT2 diverifikasi independen 100% dari baris sheet masing-masing, **DILARANG KERAS** menyalin BOM GTT1 ke GTT2 atau sebaliknya.

---

## 5. Invariant & Governance Proof

- **Database Integrity**: Tidak ada baris pada tabel `construction_types`, `construction_bom_items`, maupun `assets` yang dimutasi.
- **Backwards Compatibility**: Payload API tetap menyediakan atribut `id`, `code`, `name`, `family` seperti sebelumnya, dengan tambahan `canonical_code`, `technical_name`, `feature_badge`, `display_label`, serta `assembly_semantics`.
- **Zero Infiltration**: Relasi tetap terjaga deterministik: `assets.construction_type_id` $\rightarrow$ `construction_types` $\rightarrow$ `construction_bom_items` $\rightarrow$ `master_materials`.

---

## 6. Material Unit & Phase Assembly Semantics (Micro-Correction #2)

### A. Aturan Semantik Lapangan (Ground Truth 3-Phasa)
Pada sistem distribusi 20 kV PLN UP3 Sidoarjo, peralatan switching dan proteksi beroperasi dalam konfigurasi 3-phasa (R, S, T):
- **Fuse Cut Out (FCO)**: 1 SET transaksi $\equiv$ 3 buah fisik (1 buah per phasa: R, S, T).
- **Lightning Arrester (LA)**: 1 SET transaksi $\equiv$ 3 buah fisik (1 buah per phasa: R, S, T).
- **Disconnecting Switch (DS)**: 1 SET transaksi $\equiv$ 3 pole mekanis gang-operated.

> [!IMPORTANT]
> **BOM count "(3)" $\neq$ "3 SET"**!  
> Angka "(3)" pada lembar BOM merepresentasikan **3 buah fisik konstituen**, bukan satuan transaksi 3 SET. Menampilkan `(3 SET)` adalah kekeliruan semantik fatal. UI dan API wajib menampilkan:
> `FUSE CUT OUT 24KV: 1 SET = 3 buah • 1/phasa`
> `LIGHTNING ARRESTER 20KV: 1 SET = 3 buah • 1/phasa`

### B. Aturan Transaksi & Tata Kelola Input
1. **BOM adalah Whitelist, bukan Auto-Fill Quantity**:
   - Kotak input kuantitas di Material Picker tetap **KOSONG/BLANK** secara default (`placeholder="Jumlah (misal: 1.00)"`).
   - Sistem **TIDAK PERNAH** mengisikan angka 3 atau angka default lainnya secara otomatis.
2. **Preservasi Kuantitas Transaksi**:
   - Jika operator memasukkan `1.00 SET`, sistem menyimpan kuantitas `1.00` dengan unit `SET`.
   - Sistem **TIDAK PERNAH** mengalikan kuantitas transaksi secara sepihak ($\times 3$) di backend.
3. **Isolasi Granular**:
   - Aturan assembly 3-phasa hanya diaplikasikan pada peralatan yang terverifikasi (FCO, LA, DS).
   - Material non-3-phasa (misal: Travers, Isolator Tumpu, Konduktor) tetap mempertahankan unit aslinya (`BH`, `SET`, `MTR`) tanpa asumsi global 3-phasa.

---

## 7. GTT Semantic Identity (Micro-Correction #3)

### A. Rekonsiliasi Identitas Konstruksi Gardu Trafo Tiang
Untuk mencegah kekeliruan pemilihan tipe gardu dan BOM di lapangan:
- **`GTT1`**:
  - `canonical_code`: **`GTT1`**
  - `technical_name`: **`Gardu Distribusi Tiang Cantol (1 Tiang)`**
  - `topology_meaning`: **`CANTOL`** (1 Tiang)
  - `feature_badge`: `⚡🔌 CANTOL DENGAN LA & CO`
  - Proteksi: LA = 1 SET (3 buah • 1/phasa), FCO = 1 SET (3 buah • 1/phasa)
- **`GTT2`**:
  - `canonical_code`: **`GTT2`**
  - `technical_name`: **`Gardu Distribusi Tiang Portal (2 Tiang)`**
  - `topology_meaning`: **`PORTAL`** (2 Tiang)
  - `feature_badge`: `⚡🔌 PORTAL DENGAN LA & CO`
  - Proteksi: LA = 1 SET (3 buah • 1/phasa), FCO = 1 SET (3 buah • 1/phasa)

### B. Isolasi BOM Mandiri GTT
- BOM GTT1 (Sheet `KONSTRUKSI GTT` R1–R66) dan BOM GTT2 (Sheet `KONSTRUKSI GTT` R67–R131) bersifat **independen dan terisolasi**.
- Tidak ada rekaman BOM yang disalin, ditukar, atau di-share antara GTT1 dan GTT2.
- ID dan baris tabel `construction_types`, `construction_bom_items`, `assets`, `master_materials`, `temuan` tetap steril ($\Delta = 0$).

