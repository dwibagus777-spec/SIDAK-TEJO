# 📌 PROJECT CONTEXT: SIDAK TEJO

## 1. DESKRIPSI SISTEM
**SIDAK TEJO** (Sistem Informasi Digital Akurasi Kinerja & Inspeksi Terintegrasi) adalah platform enterprise berbasis web dan GIS untuk inspeksi jaringan listrik PLN (JTM / Penyulang / Gardu / Tiang).

## 2. SPESIFIKASI TEKNOLOGI
- **Framework**: CodeIgniter 4.7.4
- **Language**: PHP 8.3+
- **Database**: MySQL (`u532206332_sidaktejo`)
- **Hosting Environment**: Hostinger Shared Hosting (`https://sidaktejo.site`)
- **Frontend / GIS**: HTML5, Vanilla CSS, Bootstrap 5, Leaflet 1.9.4, Leaflet.markercluster 1.5.3 (via CDNJS)
- **Version Control**: Git / GitHub (`dwibagus777-spec/SIDAK-TEJO`)

## 3. PERAN PENGGUNA (USER ROLES)
1. **Administrator / Admin Pusat**: Akses penuh ke master asset, planning, WO, GIS, dan konfigurasi.
2. **Supervisor UP3 / SPV ULP**: Mengelola planning inspeksi ULP, memantau progress real-time, dan mengaudit temuan.
3. **Inspector / Petugas Lapangan**: Melakukan claim atomic task pool ULP, membuka Guided Inspection, menginput hasil PASS/FAIL + Foto evidence + Catatan temuan di lapangan.

## 4. MODUL UTAMA
- 📋 **Planning & Claim Pool**: Atomic ownership locking (`assigned_inspector_id = user_id`, `status = IN_PROGRESS`).
- 🗺️ **GIS Network Intelligence Center**: Single LineString Transline Polyline, Viewport Bounding Box Loading, Smart Proximity Haversine Distance, dan Leaflet MarkerCluster.
- 📱 **Guided Sequential Inspection Engine**: Guided execution aset per aset (`#001` s/d `#N`) dengan dynamic CSRF token refresh.
- 📊 **Smart Work Order & Analytics**: Algoritma AI Work Order Optimizer dan statistik kesehatan aset.
