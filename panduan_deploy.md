# 🚀 Panduan Deploy SIDAK TEJO ke Hosting

## 📦 File yang Disiapkan

| File | Lokasi | Keterangan |
|---|---|---|
| `sidaktejo_deploy.zip` | `e:\XAMPP\htdocs\` | Source code lengkap siap upload |
| `sidaktejo_database.sql` | `e:\XAMPP\htdocs\SIDAK TEJO\` | Database siap import ke hosting |
| `daftar_user_sidaktejo.csv` | Folder artifacts ini | Daftar user untuk distribusi |
| `env.hosting` | Di dalam ZIP | Template konfigurasi hosting |

---

## 🖥️ Kebutuhan Hosting

| Kebutuhan | Minimum | Rekomendasi |
|---|---|---|
| **PHP** | 8.1 | 8.2 atau 8.3 |
| **MySQL** | 5.7 | 8.0 |
| **Web Server** | Apache | Apache + mod_rewrite |
| **Ekstensi PHP** | mysqli, intl, json, mbstring | + curl, gd, zip |
| **Storage** | 500 MB | 2 GB (untuk foto eviden) |

> [!IMPORTANT]
> Pastikan **mod_rewrite** aktif di Apache hosting Anda.
> Jika menggunakan cPanel shared hosting, biasanya sudah aktif secara default.

---

## 📋 Langkah Deploy (cPanel / Shared Hosting)

### Langkah 1 — Import Database

1. Buka **cPanel → phpMyAdmin**
2. Buat database baru (contoh: `nama_db_sidaktejo`)
3. Buat user database dan beri akses **ALL PRIVILEGES** ke database tersebut
4. Klik tab **Import** → pilih file `sidaktejo_database.sql` → klik **Go**
5. Tunggu hingga selesai

---

### Langkah 2 — Upload File

**Opsi A: Upload ke Subdomain/Subfolder** *(Direkomendasikan)*
```
Contoh URL: https://sidaktejo.domain.com/public/
                 atau
             https://domain.com/sidaktejo/public/
```
1. Buka **cPanel → File Manager → public_html**
2. Buat folder baru: `sidaktejo`
3. Upload `sidaktejo_deploy.zip` ke dalam folder `sidaktejo`
4. Extract file ZIP tersebut
5. Pastikan struktur foldernya:
   ```
   public_html/
   └── sidaktejo/
       ├── SIDAK TEJO/          ← isi ZIP
       │   ├── app/
       │   ├── public/          ← ini yang diakses browser
       │   ├── vendor/
       │   ├── writable/
       │   └── env.hosting
   ```

---

### Langkah 3 — Konfigurasi `.env`

1. Di dalam folder `SIDAK TEJO/`, rename file `env.hosting` menjadi `.env`
2. Edit file `.env` sesuai data hosting:

```env
CI_ENVIRONMENT=production
app.baseURL=https://domain-anda.com/sidaktejo/SIDAK TEJO/public/

database.default.hostname=localhost
database.default.database=namauser_sidaktejo
database.default.username=namauser_dbuser
database.default.password=password_db_anda
```

> [!WARNING]
> Jangan lupa ubah `CI_ENVIRONMENT` dari `development` ke `production`!
> Jika dibiarkan `development`, error PHP akan tampil ke pengguna.

---

### Langkah 4 — Konfigurasi `.htaccess`

Buat file `.htaccess` di dalam folder `SIDAK TEJO/` (satu level di atas `public/`):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Atau arahkan **Document Root** subdomain langsung ke folder `public/` via cPanel → Subdomains.

---

### Langkah 5 — Atur Permissions Folder

Pastikan folder `writable/` memiliki permission **755** atau **775**:

```bash
chmod -R 755 writable/
chmod -R 755 public/uploads/
```

Bisa dilakukan via cPanel → File Manager → klik kanan folder → Change Permissions.

---

### Langkah 6 — Test Aplikasi

1. Buka URL aplikasi di browser
2. Login dengan akun administrator:
   - **Username**: `admin`
   - **Password**: `admin123`
3. **SEGERA GANTI PASSWORD** setelah berhasil login

---

## 🔐 Keamanan Setelah Deploy

> [!CAUTION]
> Langkah berikut WAJIB dilakukan setelah deploy!

- [ ] Ganti password `admin` dari `admin123`
- [ ] Ganti password semua user sesuai kebutuhan
- [ ] Set `CI_ENVIRONMENT=production` di `.env`
- [ ] Pastikan folder `writable/` tidak bisa diakses langsung dari browser
- [ ] Aktifkan HTTPS (SSL) di hosting

---

## 🗂️ Struktur Role User

| Role | Hak Akses |
|---|---|
| `administrator` | Akses penuh seluruh sistem |
| `admin_ulp` | Manajemen data ULP sendiri |
| `inspeksi` | Input & lihat temuan ULP sendiri |
| `pdkb` | Lihat temuan lintas ULP (PDKB) |
| `har_gardu` | Lihat & kelola eviden HAR Konstruksi |
| `har_row` | Lihat & kelola eviden HAR ROW |
| `har_crane` | Lihat temuan lintas ULP (HAR Crane) |
| `yantek` | Lihat & kelola data Yantek |

---

## 📞 Troubleshooting Umum

| Masalah | Solusi |
|---|---|
| **404 Not Found** | Aktifkan mod_rewrite, cek .htaccess |
| **500 Internal Server Error** | Cek error log, pastikan PHP 8.1+ |
| **Database Error** | Cek credential di .env |
| **Foto tidak muncul** | Cek permission folder `public/uploads/` (755) |
| **Halaman kosong/putih** | Cek PHP error log, set `CI_ENVIRONMENT=development` sementara |

---

*Dibuat otomatis oleh SIDAK TEJO Deployment System — 2026*
