# SLD-05 LIVE ROUTE & DEPLOYMENT FORENSIC REPORT
**Document ID**: `SLD-05_LIVE_ROUTE_FORENSIC.md`  
**Milestone**: Phase SLD-05 (Deployment / Route Gate Forensic Audit)  
**Status**: ⏸ **BLOCKED — ENVIRONMENT / PROTOCOL (SECURE COOKIE OVER HTTP)**  
**Audit Date**: 2026-09-15  
**Primary Pilot**: Feeder 15 (`BANJAR KEMANTREN`, PYL-015)  
**Invariant State**: Strict Read-Only ($\Delta = 0$: `INSERT=0, UPDATE=0, DELETE=0, TRUNCATE=0, ALTER=0, DROP=0`)

---

## 1. Executive Summary & Progression of Forensic Blockers

Dalam verifikasi visual langsung (*live browser audit*), proses evaluasi melalui dua gerbang kendala bertingkat:

```
[Percobaan #1: http://localhost/sld/view/15]
       │
       ▼
Apache DocumentRoot (E:/XAMPP/htdocs)
       │
       ▼
❌ BLOCKER #1: HTTP 404 Not Found (Apache Bawaan)
   -> STATUS: TERPECAHKAN (URL harus menyertakan subfolder: /SIDAK TEJO/public/)

[Percobaan #2: http://localhost/SIDAK%20TEJO/public/sld/view/15]
       │
       ▼
Apache XAMPP
       │
       ▼
CodeIgniter 4 Front Controller (index.php)
       │
       ▼
Auth Filter Redirect -> /login
       │
       ▼
ResponseTrait::dispatchCookies()
       │
       ▼
❌ BLOCKER #2: SecurityException: Attempted to send a secure cookie over a non-secure connection.
   -> STATUS: TERDIAGNOSIS (Konflik protokol HTTP localhost vs Kebijakan Produksi Secure Cookie)
```

**STATUS MILESTONE SLD-05: 🔒 SEALED = NO (DITAHAN)**.

---

## 2. Investigasi Forensik Blocker #1 — Path Perutean Apache (SELESAI)

* **Apache DocumentRoot**: `E:/XAMPP/htdocs` (`httpd.conf` baris 252).
* **Lokasi Aplikasi**: `E:/XAMPP/htdocs/SIDAK TEJO/public`.
* **Akar Masalah #1**: URL `localhost/sld/view/15` langsung ditolak oleh Apache pada level DocumentRoot karena tidak adanya folder `sld` di root `htdocs`.
* **Solusi Perutean**: URL valid aplikasi adalah:
  `http://localhost/SIDAK%20TEJO/public/sld/view/15`
  *(atau versi HTTPS: `https://localhost/SIDAK%20TEJO/public/sld/view/15`)*.

---

## 3. Investigasi Forensik Blocker #2 — Secure Cookie / HTTP Local Environment

### 3.1 Detail Eksepsi Runtime
* **Pesan Kesalahan**: `SecurityException: Attempted to send a secure cookie over a non-secure connection.`
* **Lokasi Stack Trace**:
  ```text
  #0 ...\system\HTTP\ResponseTrait.php(680): CodeIgniter\Security\Exceptions\SecurityException::forInsecureCookie()
  #1 ...\system\HTTP\ResponseTrait.php(670): CodeIgniter\HTTP\Response->dispatchCookies()
  #2 ...\system\HTTP\ResponseTrait.php(373): CodeIgniter\HTTP\Response->sendCookies()
  #3 ...\system\CodeIgniter.php(1148): CodeIgniter\HTTP\Response->send()
  #4 ...\system\CodeIgniter.php(386): CodeIgniter\CodeIgniter->sendResponse()
  #5 ...\system\Boot.php(417): CodeIgniter\CodeIgniter->run()
  #6 ...\system\Boot.php(68): CodeIgniter\Boot::runCodeIgniter(Object(CodeIgniter\CodeIgniter))
  #7 ...\public\index.php(112): CodeIgniter\Boot::bootWeb(Object(Config\Paths))
  ```

### 3.2 Konfigurasi Cookie Efektif Runtime
Hasil inspeksi langsung terhadap berkas konfigurasi:
* **`app/Config/Cookie.php`**:
  - `public string $prefix = '';`
  - `public $expires = 28800;` (8 Jam)
  - `public string $path = '/';`
  - `public string $domain = '';`
  - **`public bool $secure = true;`** *(Kebijakan Keamanan Standar Produksi / HTTPS Enforced)*
  - `public bool $httponly = true;`
  - `public string $samesite = 'Lax';`
  - `public bool $raw = false;`
* **`app/Config/Security.php`**:
  - `public string $csrfProtection = 'cookie';`
  - `public string $cookieName = 'csrf_cookie_name';`
* **`app/Config/Session.php`**:
  - `public string $cookieName = 'sidaktejo_session';`
* **`app/Config/App.php`**:
  - `public bool $forceGlobalSecureRequests = false;`
  - `public string $baseURL`: Dinamis mendeteksi protokol (`http` vs `https`) berdasarkan `$_SERVER['HTTPS']`.

### 3.3 Mekanisme Kegagalan pada ResponseTrait.php
Pada `system/HTTP/ResponseTrait.php` baris 678–681:
```php
foreach ($this->cookieStore->display() as $cookie) {
    if ($cookie->isSecure() && ! $request->isSecure()) {
        throw SecurityException::forInsecureCookie();
    }
```
Ketika peramban mengakses melalui `http://localhost/...`:
1. Request berjalan melalui HTTP biasa (tidak terenkripsi), sehingga `$request->isSecure()` bernilai `false`.
2. Saat CodeIgniter hendak mengirimkan cookie session (`sidaktejo_session`) atau cookie CSRF (`csrf_cookie_name`), cookie tersebut mewarisi nilai `$secure = true` dari `Config\Cookie`.
3. Validasi `$cookie->isSecure() && ! $request->isSecure()` menghasilkan `true && !false` $\to$ **`TRUE`**.
4. CodeIgniter memicu `SecurityException::forInsecureCookie()` untuk mencegah pengiriman cookie sensitif melalui koneksi terbuka yang tidak aman.

---

## 4. Status Ketersediaan HTTPS pada Localhost (Port 443)

Pemeriksaan *read-only* terhadap konfigurasi Apache XAMPP membuktikan:
1. **Port 443 Aktif & Mendengarkan**:
   * Perintah `Get-NetTCPConnection` menunjukkan PID `7768` (`httpd.exe`) aktif mendengarkan di port `80` dan `443`.
2. **Modul SSL Apache Aktif**:
   * Berkas `e:/XAMPP/apache/conf/extra/httpd-ssl.conf` (baris 121–154) telah mengaktifkan:
     ```apache
     <VirtualHost _default_:443>
     DocumentRoot "E:/XAMPP/htdocs"
     SSLEngine on
     SSLCertificateFile "conf/ssl.crt/server.crt"
     SSLCertificateKeyFile "conf/ssl.key/server.key"
     ```
3. **Hasil Pengujian Empiris cURL HTTPS**:
   ```bash
   # Uji endpoint login via HTTPS:
   curl -k -s -o NUL -w "%{http_code}" "https://localhost/SIDAK%20TEJO/public/login"
   # HASIL: 200 OK! (Bukan SecurityException!)

   # Uji endpoint SLD via HTTPS:
   curl -k -s -o NUL -w "%{http_code} %{redirect_url}" "https://localhost/SIDAK%20TEJO/public/sld/view/15"
   # HASIL: 302 Found -> https://localhost/SIDAK%20TEJO/public/login
   ```

**KESIMPULAN HTTPS**:  
Lingkungan lokal XAMPP **SUDAH MEMILIKI HTTPS YANG AKTIF DAN BERFUNGSI SEMPURNA**.  
Ketika diakses melalui `https://`, `$request->isSecure()` bernilai `true`, sehingga **TIDAK ADA SecurityException** dan halaman login maupun SLD berjalan lancar.

---

## 5. Analisis Kebijakan Keamanan & Tindakan Perbaikan (Corrective Action)

Sesuai instruksi mutlak:
> *"Production security policy must remain untouched. Clearly distinguish LOCAL ENVIRONMENT CORRECTIVE ACTION from PRODUCTION SECURITY POLICY. Do not implement automatically unless explicitly authorized."*

### Opsi A (Tanpa Perubahan Kode / Konfigurasi — REKOMENDASI UTAMA)
Aplikasi diakses menggunakan protokol **HTTPS** yang sudah tersedia di server lokal:
👉 **`https://localhost/SIDAK%20TEJO/public/sld/view/15`**

* **Keunggulan**:
  1. Nol perubahan berkas (`app/Config/Cookie.php` tetap `secure = true`).
  2. Kebijakan keamanan produksi 100% identik dengan lingkungan lokal.
  3. Session dan CSRF token terenkripsi penuh.
* **Catatan Pengguna**:
  Karena menggunakan sertifikat lokal XAMPP (*self-signed certificate*), peramban (Chrome/Edge) akan menampilkan peringatan keamanan satu kali (*"Your connection is not private"*). Cukup klik **Advanced $\to$ Proceed to localhost (unsafe)** satu kali, dan seluruh aplikasi berjalan normal.

### Opsi B (Override Khusus Lingkungan Lokal via `.env` — Memerlukan Otorisasi)
Jika pengguna **hanya ingin menggunakan HTTP biasa** tanpa melewati dialog sertifikat HTTPS peramban, CodeIgniter 4 menyediakan mekanisme *environment override* melalui berkas `.env` lokal tanpa menyentuh berkas produksi `app/Config/Cookie.php`:
```ini
# Menambahkan konfigurasi lokal di .env:
cookie.secure = false
```
* **Status**: **TIDAK DIAPLIKASIKAN** (Menunggu otorisasi tertulis pengguna).
* **Catatan**: Berkas produksi `app/Config/Cookie.php` tetap mempertahankan `$secure = true`.

---

## 6. Pembuktian Invarian Nol Mutasi ($\Delta = 0$) & Keutuhan Milestone

Pemeriksaan integritas basis data dan service upstream:
* **Penghitungan Baris Basis Data Operasional**:
  - `assets`: 30
  - `gis_translines`: 0
  - `penyulang`: 134
  - `temuan`: 441
  - `temuan_observations`: 713
* **Mutasi Basis Data ($\Delta$)**:
  $$\Delta = 0 \quad (\text{INSERT}=0, \text{UPDATE}=0, \text{DELETE}=0, \text{TRUNCATE}=0, \text{ALTER}=0, \text{DROP}=0)$$
* **Keutuhan Upstream**:
  - **SLD-01**: 🔒 **SEALED** (Tidak berubah)
  - **SLD-02**: 🔒 **SEALED** (Topology Read Model tidak berubah)
  - **SLD-03**: 🔒 **SEALED** (Semantic Network Hierarchy tidak berubah)
  - **SLD-04**: 🔒 **SEALED** (Layout & Coordinate Engine tidak berubah)
  - **SLD-05 Renderer**: Tidak ada perubahan logika renderer.

---

## 7. Status Pengesahan Milestone

* **SLD-05 Status**: ⏸ **BLOCKED — ENVIRONMENT / PROTOCOL (SECURE COOKIE OVER HTTP)**
* **SEALED**: ❌ **BELUM DISEGEL (SEALED = NO)**
