# 🚀 Panduan Audit Website Terstruktur: Kecepatan, Efisiensi Kode, & Keamanan Terdalam

Dokumen ini dirancang sebagai *checklist* teknis untuk memastikan website Anda berjalan secepat kilat (tanpa redundansi kode) dan aman hingga ke lapisan backend paling dalam.

---

## 1. Audit Kecepatan & Efisiensi Kode (Bebas Redundansi)

Bagian ini berfokus pada penghapusan *dead code* (CSS/JS tidak terpakai) dan optimalisasi pengiriman aset ke *browser*.

### 🟩 CSS & JavaScript Efisiensi
- [ ] **Hapus Unused CSS/JS:** Gunakan fitur **Coverage** di Chrome DevTools untuk mendeteksi kode CSS dan JS yang dimuat tetapi tidak dieksekusi pada halaman tersebut.
- [ ] **Minifikasi & Kompresi:** Pastikan semua file `.css` dan `.js` telah diminifikasi (menghapus spasi dan komentar) serta dikompresi menggunakan **Brotli** atau **Gzip** di sisi server.
- [ ] **Bundling & Code Splitting:** Pecah file JS besar menjadi potongan kecil (*chunks*) yang hanya dimuat saat dibutuhkan (misal: memisahkan kode halaman admin dengan halaman utama).
- [ ] **Critical CSS:** Pisahkan CSS yang bertugas merender bagian atas halaman (*above-the-fold*) dan muat sisa CSS lainnya secara asinkronus (`rel="preload"`).
- [ ] **Eliminasi Render-Blocking:** Pastikan script pihak ketiga atau non-kritis menggunakan atribut `defer` atau `async`.

### 🟩 Server & Database Performance
- [ ] **Time to First Byte (TTFB):** Pastikan nilai TTFB server Anda berada di bawah **200ms**.
- [ ] **Object & Page Caching:** Implementasikan caching di sisi server (seperti Redis atau Memcached) dan halaman statis untuk mengurangi beban eksekusi PHP/Node.js/Python.
- [ ] **Database Query Optimization:** Periksa log database untuk mendeteksi *slow queries*, dan pastikan kolom yang sering dicari sudah diberi *Index*.

---

## 2. Audit Keamanan Menyeluruh (Deep Security)

Memastikan seluruh celah masuk—baik dari manipulasi input user hingga konfigurasi server—sudah tertutup rapat.

### 🔒 Lapisan Front-End & Network
- [ ] **Sertifikat SSL/TLS:** Gunakan enkripsi modern (TLS 1.2 atau TLS 1.3) dan matikan protokol lama yang rentan (TLS 1.0 & 1.1).
- [ ] **HTTP Security Headers:** Konfigurasikan header berikut pada server web Anda (Nginx/Apache/Cloudflare):
  - `Content-Security-Policy (CSP)` (Mencegah XSS dengan membatasi sumber script yang boleh berjalan).
  - `Strict-Transport-Security (HSTS)` (Memaksa koneksi selalu HTTPS).
  - `X-Content-Type-Options: nosniff` (Mencegah sniffing tipe file).
  - `X-Frame-Options: DENY` (Mencegah pembajakan klik / *Clickjacking*).
- [ ] **Rate Limiting:** Batasi jumlah request per IP untuk mencegah serangan DDoS dan *Brute Force* pada halaman login atau API.

### 🔒 Lapisan Back-End & Source Code
- [ ] **Sanitasi & Validasi Input:** Pastikan semua data yang dimasukkan user melalui form atau API divalidasi dengan ketat untuk mencegah **SQL Injection (SQLi)** dan **Cross-Site Scripting (XSS)**.
- [ ] **Dependency Security:** Jalankan pemindaian otomatis (seperti `npm audit`, `snyk test`, atau `composer audit`) untuk memastikan *library* pihak ketiga yang Anda gunakan tidak memiliki celah keamanan publik.
- [ ] **Prinsip Hak Akses Minimum:** Jangan pernah menjalankan aplikasi web menggunakan *user* `root` pada server.

### 🔒 Lapisan Sistem File & Database
- [ ] **File Permissions:** Folder krusial harus diatur dengan hak akses ketat (misal: `755` untuk direktori dan `644` untuk file). Larang keras penggunaan izin `777`.
- [ ] **Penyimpanan Data Sensitif:** Password wajib di-hash menggunakan algoritma kuat seperti **Bcrypt** atau **Argon2** (bukan MD5 atau SHA1). Informasi sensitif lainnya di database harus dienkripsi menggunakan AES-256.

---

## 3. Alat Pemindai (Tools Checklist)

Gunakan kombinasi alat otomatis berikut secara berkala:

| Kategori | Nama Alat | Fokus Pemeriksaan |
| :--- | :--- | :--- |
| **Performa & Kode** | [Google PageSpeed Insights](https://pagespeed.web.dev/) | Menilai Core Web Vitals, Unused CSS, dan TTFB. |
| **Performa & Kode** | [PurgeCSS / UnusedCSS](https://purgecss.com/) | Alat otomatis untuk membuang CSS yang tidak terpakai saat *build*. |
| **Keamanan Luar** | [Mozilla Observatory](https://observatory.mozilla.org/) | Memeriksa kekuatan implementasi HTTP Security Headers. |
| **Keamanan Luar** | [Qualys SSL Labs](https://www.ssllabs.com/ssltest/) | Menganalisis konfigurasi enkripsi SSL/TLS secara mendalam. |
| **Keamanan Dalam** | [OWASP ZAP](https://www.zaproxy.org/) | Pemindaian aktif (*DAST*) untuk mencari celah SQLi, XSS, dan *Broken Auth*. |
| **Keamanan Kode** | [SonarQube](https://www.sonarqube.org/) | Menganalisis baris kode (*SAST*) untuk menemukan *vulnerability* dan *code smell*. |

---

*Catatan: Keamanan dan kecepatan bukan hasil akhir sekali jadi, melainkan sebuah proses berkelanjutan yang harus diaudit setiap kali ada pembaruan kode.*