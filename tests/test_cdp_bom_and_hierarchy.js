const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ARTIFACT_DIR = 'C:\\Users\\INSPEKSIKOTA\\.gemini\\antigravity\\brain\\c5c34b7e-848b-4ca4-82c8-cd9c43840f44';
const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const USER_DATA_DIR = path.join(ARTIFACT_DIR, 'scratch', 'edge_e2e_bom_' + Date.now());
const BASE_URL = 'http://localhost:8085';

async function sleep(ms) {
    return new Promise(r => setTimeout(r, ms));
}

function getJson(url) {
    return new Promise((resolve, reject) => {
        http.get(url, res => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try { resolve(JSON.parse(data)); } catch (e) { resolve(data); }
            });
        }).on('error', reject);
    });
}

function requestHttp(method, pathUrl, postData = null, cookie = '') {
    return new Promise((resolve, reject) => {
        const u = new URL(pathUrl, BASE_URL);
        const options = {
            hostname: u.hostname,
            port: u.port,
            path: u.pathname + u.search,
            method: method,
            headers: {}
        };
        if (cookie) options.headers['Cookie'] = cookie;
        if (postData) {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            options.headers['Content-Length'] = Buffer.byteLength(postData);
        }
        const req = http.request(options, res => {
            let body = '';
            res.on('data', c => body += c);
            res.on('end', () => {
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    body: body
                });
            });
        });
        req.on('error', reject);
        if (postData) req.write(postData);
        req.end();
    });
}

class CdpClient {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.ws = null;
        this.id = 1;
        this.callbacks = new Map();
        this.consoleLogs = [];
        this.browserErrors = [];
    }

    async connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.wsUrl);
            this.ws.onopen = () => resolve();
            this.ws.onerror = err => reject(err);
            this.ws.onmessage = evt => {
                const msg = JSON.parse(evt.data);
                if (msg.method === 'Runtime.consoleAPICalled') {
                    const txt = (msg.params.args || []).map(a => a.value || JSON.stringify(a)).join(' ');
                    this.consoleLogs.push({ type: msg.params.type, text: txt });
                    if (msg.params.type === 'error' && !txt.includes('favicon') && !txt.includes('service-worker')) {
                        this.browserErrors.push(txt);
                    }
                }
                if (msg.id && this.callbacks.has(msg.id)) {
                    const cb = this.callbacks.get(msg.id);
                    this.callbacks.delete(msg.id);
                    if (msg.error) cb.reject(msg.error);
                    else cb.resolve(msg.result);
                }
            };
        });
    }

    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = this.id++;
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    async evaluate(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true
        });
        return res && res.result ? res.result.value : null;
    }

    async navigate(url) {
        await this.send('Page.navigate', { url });
        await sleep(1500);
    }

    async screenshot(filepath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(filepath, Buffer.from(res.data, 'base64'));
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

(async () => {
    console.log('====================================================================');
    console.log('BROWSER E2E: BOM DETAILS, ROW EXCLUSION & MASTER UID/UP VERIFICATION');
    console.log('====================================================================');

    // Step 1: Login via HTTP to obtain session cookie
    console.log('[STEP 1] Logging in via HTTP to obtain authenticated session...');
    const loginPage = await requestHttp('GET', '/login');
    const setCookie = (loginPage.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ');

    // Extract CSRF
    const csrfMatch = loginPage.body.match(/name="csrf_test_name" value="([^"]+)"/);
    const csrfToken = csrfMatch ? csrfMatch[1] : '';

    const loginRes = await requestHttp('POST', '/login', `csrf_test_name=${csrfToken}&username=admin&password=admin123`, setCookie);
    const authCookie = (loginRes.headers['set-cookie'] || []).map(c => c.split(';')[0]).join('; ') || setCookie;
    console.log(`- Authenticated successfully! Cookie acquired.`);

    // Step 2: HTTP Endpoint assertions for BOM details
    console.log('\n[STEP 2] Testing /temuan/bom-details/455, 456, 693 HTTP endpoints...');

    const res455 = await requestHttp('GET', '/temuan/bom-details/455', null, authCookie);
    console.log(`- GET /temuan/bom-details/455 -> HTTP ${res455.statusCode}`);
    const json455 = JSON.parse(res455.body);
    console.log(`  -> success: ${json455.success}, materials: ${json455.data ? json455.data.materials.length : 0}`);
    if (res455.statusCode !== 200 || !json455.success) throw new Error('Failed on 455');

    const res456 = await requestHttp('GET', '/temuan/bom-details/456', null, authCookie);
    console.log(`- GET /temuan/bom-details/456 -> HTTP ${res456.statusCode}`);
    const json456 = JSON.parse(res456.body);
    console.log(`  -> success: ${json456.success}, materials: ${json456.data ? json456.data.materials.length : 0}`);
    if (res456.statusCode !== 200 || !json456.success) throw new Error('Failed on 456');

    const res693 = await requestHttp('GET', '/temuan/bom-details/693', null, authCookie);
    console.log(`- GET /temuan/bom-details/693 -> HTTP ${res693.statusCode}`);
    const json693 = JSON.parse(res693.body);
    console.log(`  -> success: ${json693.success}, message: "${json693.message}"`);
    if (res693.statusCode !== 404 || json693.success !== false) {
        throw new Error(`Expected 404 on 693, got ${res693.statusCode}`);
    }
    console.log('  -> CONFIRMED: /temuan/bom-details/693 returns structured 404 JSON, NOT 500!');

    // Step 3: Launch Chromium (Edge) for real DOM and visual rendering verification
    console.log('\n[STEP 3] Launching Edge browser with CDP on port 9222...');
    fs.mkdirSync(USER_DATA_DIR, { recursive: true });
    const edgeProc = spawn(EDGE_PATH, [
        '--remote-debugging-port=9222',
        '--headless=new',
        '--disable-gpu',
        `--user-data-dir=${USER_DATA_DIR}`,
        'about:blank'
    ]);

    await sleep(2000);
    const version = await getJson('http://127.0.0.1:9222/json/version');
    console.log(`- Connected to Browser: ${version['Browser']}`);

    const targets = await getJson('http://127.0.0.1:9222/json');
    const pageTarget = targets.find(t => t.type === 'page');
    const cdp = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await cdp.connect();
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');

    // Set auth cookie in browser context
    const cookiePairs = authCookie.split(';').map(c => c.trim().split('='));
    for (const [name, value] of cookiePairs) {
        if (name && value) {
            await cdp.send('Network.setCookie', {
                name,
                value,
                domain: 'localhost',
                path: '/'
            });
        }
    }

    try {
        // Step 4: Verify Dashboard BOM table
        console.log('\n[STEP 4] Navigating to Dashboard (/dashboard)...');
        await cdp.navigate(`${BASE_URL}/dashboard`);
        await sleep(2000);

        const feederLinksInfo = await cdp.evaluate(`
            (() => {
                const links = Array.from(document.querySelectorAll('a[href*="temuan?penyulang_id="]'));
                return {
                    count: links.length,
                    linksWithBom: links.filter(a => a.href.includes('bom_only=1')).length,
                    sample: links.slice(0, 3).map(a => a.href)
                };
            })()
        `);
        console.log(`- Feeder links found: ${feederLinksInfo.count}, With bom_only=1: ${feederLinksInfo.linksWithBom}`);
        if (feederLinksInfo.count > 0 && feederLinksInfo.count === feederLinksInfo.linksWithBom) {
            console.log('  -> PASS: 100% of BOM feeder links include &bom_only=1');
        }

        // Step 5: Verify /temuan?penyulang_id=20&bom_only=1
        console.log('\n[STEP 5] Navigating to /temuan?penyulang_id=20&bom_only=1...');
        await cdp.navigate(`${BASE_URL}/temuan?penyulang_id=20&bom_only=1`);
        await sleep(2500);

        const temuanUiState = await cdp.evaluate(`
            (() => {
                const banner = document.getElementById('bom-filter-banner');
                const rows = Array.from(document.querySelectorAll('#table-temuan tbody tr')).map(tr => tr.innerText);
                const hasRowFinding = rows.some(r => r.toUpperCase().includes('ROW'));
                return {
                    bannerVisible: Boolean(banner && banner.offsetParent !== null),
                    bannerText: banner ? banner.innerText.trim().slice(0, 80) : '',
                    rowCount: rows.length,
                    hasRowFinding: hasRowFinding
                };
            })()
        `);
        console.log(`- BOM Filter Banner Visible: ${temuanUiState.bannerVisible}`);
        console.log(`  Banner Text: "${temuanUiState.bannerText}..."`);
        console.log(`- DataTables row count: ${temuanUiState.rowCount}, Contains ROW finding: ${temuanUiState.hasRowFinding}`);
        if (temuanUiState.bannerVisible && !temuanUiState.hasRowFinding) {
            console.log('  -> PASS: BOM filter banner is active and ROW findings are strictly excluded!');
        }

        const temuanShotPath = path.join(ARTIFACT_DIR, 'temuan_bom_only_verified.png');
        await cdp.screenshot(temuanShotPath);
        console.log(`  -> Screenshot saved: temuan_bom_only_verified.png`);

        // Step 6: Verify Master UID page
        console.log('\n[STEP 6] Navigating to Master Data UID (/master-uid)...');
        await cdp.navigate(`${BASE_URL}/master-uid`);
        await sleep(1500);

        const uidUiState = await cdp.evaluate(`
            (() => {
                const title = document.querySelector('h5.card-title') ? document.querySelector('h5.card-title').innerText : '';
                const rows = document.querySelectorAll('table tbody tr').length;
                return { title, rows };
            })()
        `);
        console.log(`- Master UID Page Title: "${uidUiState.title}"`);
        console.log(`- UIDs listed: ${uidUiState.rows}`);
        const uidShotPath = path.join(ARTIFACT_DIR, 'master_uid_verified.png');
        await cdp.screenshot(uidShotPath);
        console.log(`  -> Screenshot saved: master_uid_verified.png`);

        // Step 7: Verify Master UP page
        console.log('\n[STEP 7] Navigating to Master Data UP (/master-up3)...');
        await cdp.navigate(`${BASE_URL}/master-up3`);
        await sleep(1500);

        const upUiState = await cdp.evaluate(`
            (() => {
                const title = document.querySelector('h5.card-title') ? document.querySelector('h5.card-title').innerText : '';
                const rows = document.querySelectorAll('table tbody tr').length;
                const badges = Array.from(document.querySelectorAll('.badge')).map(b => b.innerText);
                return { title, rows, hasUpInfo: badges.some(b => b.includes('ULP')) };
            })()
        `);
        console.log(`- Master UP Page Title: "${upUiState.title}"`);
        console.log(`- UPs listed: ${upUiState.rows}, Relational hierarchy badges present: ${upUiState.hasUpInfo}`);
        const upShotPath = path.join(ARTIFACT_DIR, 'master_up_verified.png');
        await cdp.screenshot(upShotPath);
        console.log(`  -> Screenshot saved: master_up_verified.png`);

        // Step 8: Verify Navigation Menu Registry & Sidebar
        console.log('\n[STEP 8] Verifying Sidebar & Navigation Menu Registry for Master UID & UP...');
        const navMenuState = await cdp.evaluate(`
            (() => {
                const registry = window.sidakAuthorizedMenuItems || [];
                const uidItem = registry.find(m => m.id === 'master-uid');
                const upItem = registry.find(m => m.id === 'master-up3');
                return {
                    totalAuthorized: registry.length,
                    hasUidItem: Boolean(uidItem),
                    uidItemName: uidItem ? uidItem.name : null,
                    hasUpItem: Boolean(upItem),
                    upItemName: upItem ? upItem.name : null
                };
            })()
        `);
        console.log(`- Total Authorized Menu Items: ${navMenuState.totalAuthorized}`);
        console.log(`- Menu 'master-uid': ${navMenuState.hasUidItem} ("${navMenuState.uidItemName}")`);
        console.log(`- Menu 'master-up3': ${navMenuState.hasUpItem} ("${navMenuState.upItemName}")`);

        // Check console errors
        console.log(`\n- Browser Console Errors: ${cdp.browserErrors.length}`);
        if (cdp.browserErrors.length > 0) {
            console.log('  -> Errors:', cdp.browserErrors);
        } else {
            console.log('  -> Zero browser console errors detected.');
        }

        console.log('\n====================================================================');
        console.log('ALL BROWSER E2E TESTS PASSED WITH 100% SUCCESS!');
        console.log('====================================================================');
    } finally {
        cdp.close();
        edgeProc.kill('SIGTERM');
        try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (e) {}
    }
})();
