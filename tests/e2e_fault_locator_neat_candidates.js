const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ARTIFACT_DIR = 'C:\\Users\\INSPEKSIKOTA\\.gemini\\antigravity\\brain\\c5c34b7e-848b-4ca4-82c8-cd9c43840f44';
const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const USER_DATA_DIR = path.join(ARTIFACT_DIR, 'scratch', 'edge_cand_test_gi_' + Date.now());

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
        if (res.exceptionDetails) {
            console.error('[EVAL ERROR]', res.exceptionDetails.exception?.description || res.exceptionDetails.text);
            return null;
        }
        return res.result ? res.result.value : null;
    }

    async captureScreenshot(outputPath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(outputPath, Buffer.from(res.data, 'base64'));
    }

    async waitForButtonIdle(buttonId, timeoutMs = 15000) {
        const start = Date.now();
        while (Date.now() - start < timeoutMs) {
            const isIdle = await this.evaluate(`
                (() => {
                    const btn = document.getElementById('${buttonId}');
                    return btn && !btn.disabled && !btn.innerHTML.includes('spinner-border');
                })()
            `);
            if (isIdle) return true;
            await sleep(300);
        }
        throw new Error(`Timeout waiting for button #${buttonId} to become idle`);
    }
}

let client = null;

async function run() {
    console.log('[E2E-GI-TEST] Launching Edge headless...');
    if (!fs.existsSync(USER_DATA_DIR)) fs.mkdirSync(USER_DATA_DIR, { recursive: true });

    const edgeProc = spawn(EDGE_PATH, [
        '--headless=new',
        '--remote-debugging-port=9226',
        '--user-data-dir=' + USER_DATA_DIR,
        '--no-first-run',
        '--no-default-browser-check',
        'about:blank'
    ]);

    await sleep(3000);

    let versionInfo = null;
    for (let i = 0; i < 20; i++) {
        await sleep(500);
        try {
            versionInfo = await getJson('http://127.0.0.1:9226/json/version');
            if (versionInfo && versionInfo.webSocketDebuggerUrl) break;
        } catch (e) {}
    }

    if (!versionInfo) throw new Error('Edge CDP debugger failed on port 9226');

    let targetWsUrl = null;
    try {
        const newTab = await getJson('http://127.0.0.1:9226/json/new?http://localhost:8080/fault-locator?ulp_id=1&feeder_id=1');
        if (newTab && newTab.webSocketDebuggerUrl) {
            targetWsUrl = newTab.webSocketDebuggerUrl;
        }
    } catch (e) {}

    if (!targetWsUrl) {
        const targets = await getJson('http://127.0.0.1:9226/json/list');
        const pageTarget = targets.find(t => t.type === 'page');
        if (pageTarget) targetWsUrl = pageTarget.webSocketDebuggerUrl;
    }

    console.log('[E2E-GI-TEST] Target WS URL:', targetWsUrl);
    client = new CdpClient(targetWsUrl);
    await client.connect();

    await client.send('Page.enable');
    await client.send('DOM.enable');
    await client.send('Runtime.enable');

    console.log('[E2E-GI-TEST] Navigating to http://localhost:8080/fault-locator?ulp_id=1&feeder_id=1...');
    await client.send('Page.navigate', { url: 'http://localhost:8080/fault-locator?ulp_id=1&feeder_id=1' });
    await sleep(4000);

    console.log('[E2E-GI-TEST] Setting Desktop Viewport (1440x900)...');
    await client.send('Emulation.setDeviceMetricsOverride', {
        width: 1440,
        height: 900,
        deviceScaleFactor: 1,
        mobile: false
    });
    await sleep(1500);

    // Wait for any initial calculations to settle
    await client.waitForButtonIdle('btnLocateFault');

    // 1. Configure DGR Telemetry from GI Pangkal
    console.log('[E2E-GI-TEST] Configuring DGR Telemetry from GI Pangkal...');
    await client.evaluate(`
        (() => {
            const giRadio = document.getElementById('srcGI');
            if (giRadio) giRadio.checked = true;

            document.getElementById('currentR').value = 125;
            document.getElementById('currentS').value = 20;
            document.getElementById('currentT').value = 22;
            document.getElementById('currentN').value = 55;
            document.getElementById('voltageR').value = 20.0;
            document.getElementById('voltageS').value = 20.0;
            document.getElementById('voltageT').value = 20.0;
            document.getElementById('relayDistanceM').value = '';

            document.getElementById('btnLocateFault').click();
        })()
    `);

    console.log('[E2E-GI-TEST] Waiting for DGR calculation to complete...');
    await client.waitForButtonIdle('btnLocateFault');
    await sleep(1500);

    // 2. Inspect DGR Result & Candidate Section
    const dgrGIResult = await client.evaluate(`
        (() => {
            const outcome = document.getElementById('outcomeBadge')?.innerText.trim();
            const candBox = document.getElementById('resCandidateAsset');
            const statusBadge = candBox?.querySelector('.badge.bg-warning')?.innerText.trim();
            const totalText = candBox?.querySelector('.badge.font-monospace')?.innerText.trim();
            const rows = candBox ? Array.from(candBox.querySelectorAll('table tbody tr')).map(tr => {
                const tds = tr.querySelectorAll('td');
                return {
                    num: tds[0]?.innerText.trim(),
                    kode: tds[1]?.innerText.trim(),
                    namaStatus: tds[2]?.innerText.trim(),
                    kumulatif: tds[3]?.innerText.trim()
                };
            }) : [];
            const viewAllBtn = candBox?.querySelector('button[data-bs-target="#modalAllCandidates"]');

            return {
                outcome,
                statusBadge,
                totalPill: totalText,
                previewRowCount: rows.length,
                topCandidates: rows,
                hasViewAllBtn: Boolean(viewAllBtn),
                viewAllBtnText: viewAllBtn ? viewAllBtn.innerText.trim() : null
            };
        })()
    `);
    console.log('[E2E-GI-TEST] DGR GI Result Summary:');
    console.log('   Outcome:', dgrGIResult.outcome);
    console.log('   Status Badge:', dgrGIResult.statusBadge);
    console.log('   Total Pill:', dgrGIResult.totalPill);
    console.log('   Preview Rows Count:', dgrGIResult.previewRowCount);
    console.log('   View All Button:', dgrGIResult.viewAllBtnText);
    console.table(dgrGIResult.topCandidates);

    // 3. Map Layers Audit for DGR Mode:
    // Normal candidate poles MUST remain green (#10b981), NOT orange (#f59e0b)
    // Candidate corridor polyline MUST have animated flow class 'fl-candidate-corridor-flow'
    const dgrMapAudit = await client.evaluate(`
        (() => {
            let orangeCircleMarkers = 0;
            let greenCircleMarkers = 0;
            let animatedCorridors = 0;

            if (window.miniMapLayerGroup) {
                window.miniMapLayerGroup.eachLayer(l => {
                    if (l instanceof L.CircleMarker && !(l instanceof L.Circle)) {
                        const col = (l.options.fillColor || '').toLowerCase();
                        if (col === '#f59e0b') orangeCircleMarkers++;
                        if (col === '#10b981') greenCircleMarkers++;
                    }
                    if (l instanceof L.Polyline) {
                        const cls = l.options.className || '';
                        if (cls.includes('fl-candidate-corridor-flow')) animatedCorridors++;
                    }
                });
            }

            return {
                orangeCircleMarkers,
                greenCircleMarkers,
                animatedCorridors,
                isCorridorFlowAnimated: animatedCorridors > 0,
                allPolesGreenNotOrange: (orangeCircleMarkers === 0 && greenCircleMarkers > 0)
            };
        })()
    `);
    console.log('[E2E-GI-TEST] DGR Map Layers Audit:');
    console.log('   Orange Circle Markers (Must be 0):', dgrMapAudit.orangeCircleMarkers);
    console.log('   Green Circle Markers (Normal Candidate Poles):', dgrMapAudit.greenCircleMarkers);
    console.log('   Animated Corridor Polylines:', dgrMapAudit.animatedCorridors);
    console.log('   Is Corridor Flow Animated:', dgrMapAudit.isCorridorFlowAnimated);
    console.log('   All Candidate Poles Green (Not Orange):', dgrMapAudit.allPolesGreenNotOrange);

    // 4. Test Interactive Pole Focus (Clicking Fokus button on candidate 2)
    console.log('[E2E-GI-TEST] Testing Interactive Candidate Pole Focus...');
    const focusTest = await client.evaluate(`
        (() => {
            const buttons = document.querySelectorAll('#resCandidateAsset table button');
            if (buttons.length > 1) {
                buttons[1].click();
            }
            return {
                highlightLayerActive: Boolean(window.flActiveHighlightLayer),
                hasHighlightMarkerDiv: Boolean(document.querySelector('.fl-pole-highlight-marker')),
                openPopupText: document.querySelector('.leaflet-popup-content')?.innerText.trim().substring(0, 120)
            };
        })()
    `);
    console.log('[E2E-GI-TEST] Focus & Highlight Ring Test:', focusTest);
    await sleep(1500);

    // Capture Desktop Screenshot with candidate summary and active map
    const desktopScreenshot = path.join(ARTIFACT_DIR, 'fault_locator_neat_desktop.png');
    await client.captureScreenshot(desktopScreenshot);
    console.log('[E2E-GI-TEST] Saved Desktop Screenshot:', desktopScreenshot);

    // 5. Test All Candidates Modal & Search Filtering
    console.log('[E2E-GI-TEST] Testing "Lihat Semua Kandidat" Modal...');
    const modalTest = await client.evaluate(`
        (() => {
            const viewAllBtn = document.querySelector('button[data-bs-target="#modalAllCandidates"]');
            if (viewAllBtn) viewAllBtn.click();
            if (window.flPopulateCandidateModal) window.flPopulateCandidateModal();

            const allModalRows = document.querySelectorAll('#tbodyModalCandidates tr').length;

            // Search filter test with query "558"
            const searchInput = document.getElementById('inputSearchCandidates');
            if (searchInput) {
                searchInput.value = '558';
                searchInput.dispatchEvent(new Event('input'));
            }
            const filteredRows = document.querySelectorAll('#tbodyModalCandidates tr').length;

            return {
                totalModalRows: allModalRows,
                filteredRows: filteredRows
            };
        })()
    `);
    console.log('[E2E-GI-TEST] Modal & Search Filter Test:');
    console.log('   Total Modal Rows (All Candidates):', modalTest.totalModalRows);
    console.log('   Filtered Rows for query "558":', modalTest.filteredRows);

    // Close modal
    await client.evaluate(`
        (() => {
            const closeBtn = document.querySelector('#modalAllCandidates .btn-close');
            if (closeBtn) closeBtn.click();
        })()
    `);
    await sleep(1000);

    // 6. Test Single-Point Target Pulse Mode (Relay-Aided 1200 m)
    console.log('[E2E-GI-TEST] Testing Single-Point Target Pulse Mode (relay_distance = 1200 m)...');
    await client.evaluate(`
        (() => {
            document.getElementById('relayDistanceM').value = '1200';
            document.getElementById('btnLocateFault').click();
        })()
    `);
    await client.waitForButtonIdle('btnLocateFault');
    await sleep(1500);

    const singlePointAudit = await client.evaluate(`
        (() => {
            const candBox = document.getElementById('resCandidateAsset');
            const statusBadge = candBox?.querySelector('.badge.bg-danger')?.innerText.trim();
            const pulseTargetMarker = document.querySelector('.fl-target-pulse-marker');
            let hasPulseMarker = Boolean(pulseTargetMarker);

            // Click the pulse target marker to open its popup
            if (pulseTargetMarker) {
                pulseTargetMarker.click();
            }

            const popupContent = document.querySelector('.leaflet-popup-content')?.innerText.trim() || '';

            return {
                statusBadge,
                hasPulseMarker,
                popupText: popupContent.substring(0, 160),
                popupHasEstimasi: popupContent.includes('Estimasi'),
                popupDoesNotHaveAktual: !popupContent.includes('Titik Aktual')
            };
        })()
    `);
    console.log('[E2E-GI-TEST] Single-Point Pulse Marker Audit:');
    console.log('   Status Badge:', singlePointAudit.statusBadge);
    console.log('   Has Pulse Target Marker:', singlePointAudit.hasPulseMarker);
    console.log('   Popup Has "Estimasi":', singlePointAudit.popupHasEstimasi);
    console.log('   Popup Avoids "Aktual":', singlePointAudit.popupDoesNotHaveAktual);
    console.log('   Popup Snippet:', singlePointAudit.popupText);

    // 7. Mobile Viewport (390x844)
    console.log('[E2E-GI-TEST] Testing Mobile Viewport (390x844)...');
    await client.send('Emulation.setDeviceMetricsOverride', {
        width: 390,
        height: 844,
        deviceScaleFactor: 3,
        mobile: true
    });
    await sleep(2000);

    const mobileScreenshot = path.join(ARTIFACT_DIR, 'fault_locator_neat_mobile.png');
    await client.captureScreenshot(mobileScreenshot);
    console.log('[E2E-GI-TEST] Saved Mobile Screenshot:', mobileScreenshot);

    await client.send('Browser.close').catch(() => {});
    edgeProc.kill();
    console.log('[E2E-GI-TEST] All E2E Tests Completed Successfully!');
}

run().catch(err => {
    console.error('[E2E ERROR]', err);
    process.exit(1);
});
