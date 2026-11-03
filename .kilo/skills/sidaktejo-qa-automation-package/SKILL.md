# AI Skill: Autonomous QA & Performance Audit for sidaktejo.site

## 🤖 Role Definition & Mission
Anda adalah seorang **Senior QA Automation & Web Performance Engineer AI**. Misi utama Anda adalah masuk ke codebase lokal atau repositori GitHub proyek **SIDAK TEJO**, menulis serta mengeksekusi skrip pengujian berbasis Playwright dan Lighthouse, menangani alur login, mendeteksi kerusakan menu, melacak error konsol JS/CSS, serta mengukur apakah website mengalami degradasi performa (berat/lambat).

---

## 🛠️ Execution Prerequisites
Sebelum Anda (AI) mengeksekusi instruksi ini, pastikan dependensi berikut telah siap di lingkungan runtime:
- **Node.js** (v18+) terinstal.
- **Playwright** dan komponen browsernya terinstal.
- **Lighthouse CLI** terinstal secara global untuk pengujian performa.

Jika belum tersedia, jalankan perintah inisialisasi berikut secara otomatis:
```bash
npm install -g lighthouse
npm install @playwright/test
npx playwright install chromium
```

---

## 🔄 Step-by-Step AI Execution Workflow

Anda WAJIB mengikuti alur kerja otonom 4 langkah berikut:

### Langkah 1: Setup Environment Kredensial & Target Endpoint
Jangan pernah menulis *hardcoded password* ke dalam kode. Gunakan Environment Variables sistem atau file `.env` lokal dengan kunci berikut:
- `BASE_URL` : URL target aplikasi (default bawaan: `https://sidaktejo.site`, atau `http://localhost:8080` untuk testing lokal/staging).
- `QA_USERNAME` : Username / Email login akun pengujian.
- `QA_PASSWORD` : Kata sandi akun pengujian.

### Langkah 2: Pembuatan / Eksekusi Skrip Otomatisasi (Playwright)
Gunakan skrip uji fungsional `tests/qa-audit.spec.js` (dan `playwright.config.js`) untuk memvalidasi fungsionalitas aplikasi:

```javascript
const { test, expect } = require('@playwright/test');

test('Automated Comprehensive QA and Error Crawling', async ({ page, baseURL }) => {
  const targetUrl = process.env.BASE_URL || baseURL || 'https://sidaktejo.site';
  const urlObj = new URL(targetUrl);
  const baseOrigin = urlObj.origin;
  const errors = [];
  const brokenAssets = [];

  // 1. Listeners untuk menangkap Error Konsol JavaScript & Masalah Network
  page.on('pageerror', (exception) => {
    errors.push(`[JS ERROR]: ${exception.message}`);
  });

  page.on('requestfailed', (request) => {
    const failure = request.failure();
    brokenAssets.push(`[FAILED REQUEST]: ${request.url()} - Reason: ${failure ? failure.errorText : 'Unknown'}`);
  });

  page.on('response', (response) => {
    if (response.status() >= 400) {
      brokenAssets.push(`[HTTP ${response.status()}]: ${response.url()}`);
    }
  });

  // 2. Akses Halaman Utama & Proses Login
  await page.goto(targetUrl, { waitUntil: 'domcontentloaded' });
  
  const usernameInput = page.locator('input[type="email"], input[type="text"], [placeholder*="username" i], [placeholder*="email" i]').first();
  const passwordInput = page.locator('input[type="password"]').first();
  const loginButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("Login"), button:has-text("Masuk")').first();

  if (await usernameInput.isVisible()) {
    const username = process.env.QA_USERNAME || 'test_user';
    const password = process.env.QA_PASSWORD || 'test_password';
    await usernameInput.fill(username);
    await passwordInput.fill(password);
    await loginButton.click();
    await page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {});
  }

  // 3. Eksplorasi Menu Internal & Validasi Fungsionalitas
  const links = await page.$$eval('a', (anchors, origin) => 
    anchors.map(a => a.href).filter(href => href.startsWith(origin)),
    baseOrigin
  );

  // Lakukan crawling unik pada maksimal 15 menu utama untuk menghindari rate limiting
  const uniqueLinks = [...new Set(links)].slice(0, 15);
  
  for (const link of uniqueLinks) {
    try {
      await page.goto(link, { waitUntil: 'load', timeout: 10000 });
      const cleanName = link.replace(/[^a-z0-9]/gi, '_').toLowerCase();
      await page.screenshot({ path: `qa_reports/screenshots/${cleanName}.png`, fullPage: true }).catch(() => {});
    } catch (e) {
      errors.push(`[NAVIGATION FAILED]: Gagal mengakses menu ${link}. Error: ${e.message}`);
    }
  }

  // 4. Assertions & Laporan Akhir Ke AI Console
  console.log('=== HASIL AUDIT FUNGSIONAL DAN KONSOL ===');
  console.log(`Target URL: ${targetUrl} (Origin: ${baseOrigin})`);
  console.log(`Menu terperiksa: ${uniqueLinks.length} tautan`);
  if (errors.length > 0) console.error('Daftar Error JS/Navigasi Ditemukan:', errors);
  if (brokenAssets.length > 0) console.error('Daftar Aset Terputus (CSS/JS/API Gagal):', brokenAssets);
  
  expect(errors.length).toBe(0);
  expect(brokenAssets.length).toBe(0);
});
```

Jalankan pengujian menggunakan runner:
```bash
npx playwright test --config=.kilo/skills/sidaktejo-qa-automation-package/playwright.config.js
```

### Langkah 3: Pengujian Kecepatan & Muatan Berat (Lighthouse)
Setelah pengujian fungsional selesai, Anda (AI) dapat mengeksekusi audit performa (utamakan lingkungan lokal/staging atau pengujian terjadwal pada jam non-sibuk):

```bash
TARGET_URL="${BASE_URL:-https://sidaktejo.site}"
lighthouse "$TARGET_URL" --output json --output-path ./qa_reports/lighthouse-report.json --chrome-flags="--headless"
```

Analisis file `qa_reports/lighthouse-report.json` hasil generate di atas, dan periksa metrik performa utama berikut:
- **Largest Contentful Paint (LCP)**: Waktu render elemen utama halaman (> 2.5s berarti berat).
- **Total Blocking Time (TBT)**: Beban eksekusi JavaScript utama yang memblokir interaksi user (> 200ms berarti berat).

### Langkah 4: Konsolidasi & Pelaporan
Rangkum seluruh hasil temuan Anda ke dalam berkas kesimpulan bernama `QA-AUDIT-SUMMARY.md` yang berisi tabel status kelulusan menu, *dump log* kegagalan CSS/JS, serta skor akhir dari audit performa Lighthouse.

---

## 🚫 Execution Constraints
- Jangan jalankan pengujian tanpa memicu mode `--headless` di lingkungan server/CI.
- Utamakan pengujian terhadap server lokal (`BASE_URL=http://localhost:8080`) untuk menghindari beban atau spam trafik berlebih ke domain produksi.
- Pastikan folder `qa_reports/`, `playwright-report/`, dan `test-results/` telah dimasukkan ke dalam daftar `.gitignore` lokal sebelum melakukan commit agar file screenshot tidak mengotori repositori GitHub proyek **SIDAK TEJO**.
