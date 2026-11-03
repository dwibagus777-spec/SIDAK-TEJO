const { test, expect } = require('@playwright/test');

test('Automated Comprehensive QA and Error Crawling', async ({ page, baseURL }) => {
  const targetUrl = process.env.BASE_URL || baseURL;
  if (!targetUrl) {
    throw new Error('[QA CONFIG ERROR]: Target URL (BASE_URL) belum ditentukan.');
  }

  const urlObj = new URL(targetUrl);
  const baseOrigin = urlObj.origin;
  const errors = [];
  const brokenAssets = [];

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

  await page.goto(targetUrl, { waitUntil: 'domcontentloaded' });
  
  const usernameInput = page.locator('input[type="email"], input[type="text"], [placeholder*="username" i], [placeholder*="email" i]').first();
  const passwordInput = page.locator('input[type="password"]').first();
  const loginButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("Login"), button:has-text("Masuk")').first();

  if (await usernameInput.isVisible()) {
    const username = process.env.QA_USERNAME;
    const password = process.env.QA_PASSWORD;
    
    if (!username || !password) {
      throw new Error(`[QA SECURITY GUARD]: Form login terdeteksi pada ${targetUrl}, namun kredensial QA_USERNAME atau QA_PASSWORD tidak tersedia di environment. Eksekusi dihentikan untuk mencegah login anonim.`);
    }

    await usernameInput.fill(username);
    await passwordInput.fill(password);
    await loginButton.click();
    await page.waitForNavigation({ waitUntil: 'networkidle' }).catch(() => {});
  }

  const links = await page.$$eval('a', (anchors, origin) => 
    anchors.map(a => a.href).filter(href => href.startsWith(origin)),
    baseOrigin
  );

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

  console.log('=== HASIL AUDIT FUNGSIONAL DAN KONSOL ===');
  console.log(`Target URL: ${targetUrl} (Origin: ${baseOrigin})`);
  console.log(`Menu terperiksa: ${uniqueLinks.length} tautan`);
  if (errors.length > 0) console.error('Daftar Error JS/Navigasi Ditemukan:', errors);
  if (brokenAssets.length > 0) console.error('Daftar Aset Terputus (CSS/JS/API Gagal):', brokenAssets);
  
  expect(errors.length).toBe(0);
  expect(brokenAssets.length).toBe(0);
});
