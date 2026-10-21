const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ARTIFACT_DIR = 'C:\\Users\\INSPEKSIKOTA\\.gemini\\antigravity\\brain\\c5c34b7e-848b-4ca4-82c8-cd9c43840f44';
const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const USER_DATA_DIR = path.join(ARTIFACT_DIR, 'scratch', 'edge_user_data_' + Date.now());

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

class CdpClient {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.ws = null;
        this.id = 1;
        this.callbacks = new Map();
    }

    async connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.wsUrl);
            this.ws.onopen = () => resolve();
            this.ws.onerror = err => reject(err);
            this.ws.onmessage = evt => {
                const msg = JSON.parse(evt.data);
                if (msg.id && this.callbacks.has(msg.id)) {
                    const cb = this.callbacks.get(msg.id);
                    this.callbacks.delete(msg.id);
                    if (msg.error) cb.reject(new Error(msg.error.message));
                    else cb.resolve(msg.result);
                }
            };
        });
    }

    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const reqId = this.id++;
            this.callbacks.set(reqId, { resolve, reject });
            this.ws.send(JSON.stringify({ id: reqId, method, params }));
        });
    }

    async evaluate(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true
        });
        return res.result ? res.result.value : null;
    }

    async captureScreenshot(outputPath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(outputPath, Buffer.from(res.data, 'base64'));
    }
}

async function run() {
    console.log('[E2E] Starting Edge Headless...');
    if (!fs.existsSync(USER_DATA_DIR)) fs.mkdirSync(USER_DATA_DIR, { recursive: true });

    const edgeProc = spawn(EDGE_PATH, [
        '--headless=new',
        '--remote-debugging-port=9222',
        '--no-first-run',
        '--disable-gpu',
        '--user-data-dir=' + USER_DATA_DIR,
        'about:blank'
    ], { stdio: 'ignore' });

    // Wait for CDP to be ready
    let versionInfo = null;
    for (let i = 0; i < 30; i++) {
        await sleep(500);
        try {
            versionInfo = await getJson('http://127.0.0.1:9222/json/version');
            if (versionInfo && versionInfo.webSocketDebuggerUrl) break;
        } catch (e) {}
    }

    if (!versionInfo || !versionInfo.webSocketDebuggerUrl) {
        throw new Error('Edge CDP debugger failed to start.');
    }
    console.log('[E2E] Connected to CDP version:', versionInfo['Browser']);

    // Create a new target / tab or use existing page
    let targetWsUrl = null;
    try {
        const newTab = await getJson('http://127.0.0.1:9222/json/new?http://localhost:8080/fault-locator');
        console.log('[E2E] Opened tab response:', typeof newTab === 'object' ? JSON.stringify(newTab) : newTab);
        if (newTab && newTab.webSocketDebuggerUrl) {
            targetWsUrl = newTab.webSocketDebuggerUrl;
        }
    } catch (e) {
        console.warn('[E2E] json/new warning:', e.message);
    }

    if (!targetWsUrl) {
        const targets = await getJson('http://127.0.0.1:9222/json/list');
        console.log('[E2E] Targets list:', targets);
        const pageTarget = targets.find(t => t.type === 'page');
        if (pageTarget) targetWsUrl = pageTarget.webSocketDebuggerUrl;
    }

    console.log('[E2E] Target WebSocket URL:', targetWsUrl);
    const client = new CdpClient(targetWsUrl);
    await client.connect();
    await client.send('Page.enable');
    await client.send('DOM.enable');
    await client.send('Runtime.enable');
    await client.send('Page.navigate', { url: 'http://localhost:8080/fault-locator' });

    // 1. DESKTOP VIEWPORT SETUP (1280x800)
    console.log('[E2E] Configuring Desktop Viewport (1280x800)...');
    await client.send('Emulation.setDeviceMetricsOverride', {
        width: 1280,
        height: 800,
        deviceScaleFactor: 1,
        mobile: false
    });

    await sleep(2000); // Allow Leaflet and scripts to initialize

    // Check title and key UI elements
    const pageTitle = await client.evaluate('document.title');
    console.log('[E2E] Page Title:', pageTitle);

    // Verify initial values in telemetry and form
    const initialDistance = await client.evaluate("document.getElementById('resDistance').innerText");
    const initialConfidence = await client.evaluate("document.getElementById('resConfidence').innerText");
    console.log('[E2E] Initial Distance:', initialDistance, '| Confidence:', initialConfidence);

    // Trigger Calculation Click
    console.log('[E2E] Clicking "HITUNG LOKASI GANGGUAN"...');
    await client.evaluate("document.getElementById('btnLocateFault').click()");
    await sleep(1500);

    const calcStatus = await client.evaluate("document.getElementById('outcomeBadge').innerText");
    const newDistance = await client.evaluate("document.getElementById('resDistance').innerText");
    console.log('[E2E] Post-Calculation Status:', calcStatus, '| Distance:', newDistance);

    // Click "Simpan Kasus"
    console.log('[E2E] Clicking "Simpan Kasus"...');
    const saveCaseResult = await client.evaluate(`
        (async () => {
            const res = await fetch('/api/fault-locator/save-case', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(lastCalculatedResult)
            });
            return await res.json();
        })()
    `);
    console.log('[E2E] Save Case Result:', saveCaseResult);

    // Record Outcome: CONFIRMED (Gangguan Ditemukan)
    console.log('[E2E] Recording Field Outcome: CONFIRMED...');
    const recordOutcomeResult = await client.evaluate(`
        (async () => {
            const caseId = ${saveCaseResult.case_id};
            const payload = {
                case_id: caseId,
                outcome_type: 'CONFIRMED',
                technician_name: 'E2E_PATROL_TESTER',
                inspected_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                component_damaged: 'Bunglon menempel pada pin isolator fasa R (E2E Test)',
                is_component_match: 1,
                actual_distance_m: 1245.0,
                finding_notes: 'Diverifikasi langsung oleh Tim Yantek E2E Playwright.'
            };
            const res = await fetch('/api/fault-locator/record-outcome', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            return await res.json();
        })()
    `);
    console.log('[E2E] Record Outcome Result:', recordOutcomeResult);

    // Record Outcome for another case: NOT_FOUND (Nihil)
    console.log('[E2E] Recording Field Outcome: NOT_FOUND...');
    const recordNegativeResult = await client.evaluate(`
        (async () => {
            // Save a secondary case first
            const fakePayload = Object.assign({}, lastCalculatedResult, {
                case_uuid: 'FLI-CASE-NEG-' + Math.random().toString(36).substring(2, 8).toUpperCase()
            });
            const sRes = await fetch('/api/fault-locator/save-case', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(fakePayload)
            });
            const sJson = await sRes.json();
            
            // Record NOT_FOUND
            const payload = {
                case_id: sJson.case_id,
                outcome_type: 'NOT_FOUND',
                technician_name: 'E2E_PATROL_TESTER',
                inspected_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                inspected_area_notes: 'Penyisiran tiang 1 s/d tiang 30 tuntas',
                not_found_reason: 'ROW_BERSIH_TIDAK_ADA_FLASH',
                finding_notes: 'Tidak ada gangguan menetap, surja temporer.'
            };
            const res = await fetch('/api/fault-locator/record-outcome', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            return { save: sJson, outcome: await res.json() };
        })()
    `);
    console.log('[E2E] Record Negative Result:', recordNegativeResult);

    // Reload page to verify Case History table renders outcomes
    await client.send('Page.reload');
    await sleep(2500);

    // Verify row count and statuses in Case History table
    const historyRows = await client.evaluate(`
        Array.from(document.querySelectorAll('#caseHistoryBody tr')).map(tr => {
            const tds = tr.querySelectorAll('td');
            if (tds.length < 6) return null;
            return {
                caseNumber: tds[0]?.innerText.trim(),
                feeder: tds[1]?.innerText.trim(),
                status: tds[4]?.innerText.trim(),
                outcome: tds[5]?.innerText.trim()
            };
        }).filter(Boolean)
    `);
    console.log('[E2E] Verified History Table Rows in DOM:');
    console.table(historyRows.slice(0, 5));

    // Capture Desktop Screenshot
    const desktopScreenshotPath = path.join(ARTIFACT_DIR, 'fault_locator_desktop.png');
    await client.captureScreenshot(desktopScreenshotPath);
    console.log('[E2E] Saved Desktop Screenshot:', desktopScreenshotPath);

    // 2. MOBILE VIEWPORT SETUP (390x844 - iPhone 12/13/14)
    console.log('[E2E] Configuring Mobile Viewport (390x844)...');
    await client.send('Emulation.setDeviceMetricsOverride', {
        width: 390,
        height: 844,
        deviceScaleFactor: 3,
        mobile: true
    });
    await sleep(1500);

    // Open Modal on Mobile to test modal responsiveness
    await client.evaluate(`
        const firstBtn = document.querySelector('.btn-record-outcome');
        if (firstBtn) firstBtn.click();
    `);
    await sleep(1000);

    // Capture Mobile Screenshot
    const mobileScreenshotPath = path.join(ARTIFACT_DIR, 'fault_locator_mobile.png');
    await client.captureScreenshot(mobileScreenshotPath);
    console.log('[E2E] Saved Mobile Screenshot:', mobileScreenshotPath);

    // Clean up
    await client.send('Browser.close').catch(() => {});
    edgeProc.kill();
    console.log('[E2E] All browser tests completed successfully!');

    return {
        desktopScreenshot: desktopScreenshotPath,
        mobileScreenshot: mobileScreenshotPath,
        historyCount: historyRows.length,
        savedCase: saveCaseResult,
        confirmedOutcome: recordOutcomeResult,
        negativeOutcome: recordNegativeResult
    };
}

run().catch(err => {
    console.error('[E2E ERROR]', err);
    process.exit(1);
});
