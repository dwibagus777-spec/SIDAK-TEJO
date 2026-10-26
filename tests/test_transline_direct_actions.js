const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ARTIFACT_DIR = 'C:\\Users\\INSPEKSIKOTA\\.gemini\\antigravity\\brain\\c5c34b7e-848b-4ca4-82c8-cd9c43840f44';
const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const USER_DATA_DIR = path.join(ARTIFACT_DIR, 'scratch', 'edge_tl_' + Date.now());

async function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

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
            console.error('EXCEPTION:', res.exceptionDetails);
        }
        return res.result ? res.result.value : null;
    }
    async captureScreenshot(outputPath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(outputPath, Buffer.from(res.data, 'base64'));
    }
}

async function run() {
    console.log('[TEST-TL] Launching Edge headless...');
    if (!fs.existsSync(USER_DATA_DIR)) fs.mkdirSync(USER_DATA_DIR, { recursive: true });

    const edgeProc = spawn(EDGE_PATH, [
        '--headless=new',
        '--remote-debugging-port=9222',
        '--no-first-run',
        '--disable-gpu',
        '--disable-blink-features=AutomationControlled',
        '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0',
        '--user-data-dir=' + USER_DATA_DIR,
        'about:blank'
    ], { stdio: 'ignore' });

    let versionInfo = null;
    for (let i = 0; i < 30; i++) {
        await sleep(500);
        try {
            versionInfo = await getJson('http://127.0.0.1:9222/json/version');
            if (versionInfo && versionInfo.webSocketDebuggerUrl) break;
        } catch (e) {}
    }

    const targets = await getJson('http://127.0.0.1:9222/json/list');
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const cdp = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await cdp.connect();

    await cdp.send('Page.enable');
    await cdp.send('DOM.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Page.addScriptToEvaluateOnNewDocument', {
        source: `Object.defineProperty(navigator, 'webdriver', { get: () => undefined });`
    });

    const targetUrl = process.argv[2] || 'http://localhost:8080/gis?penyulang_id=15';
    console.log('[TEST-TL] Navigating to ' + targetUrl + '...');
    await cdp.send('Page.navigate', { url: targetUrl });
    await sleep(3500);

    let title = await cdp.evaluate('document.title');
    let currentHref = await cdp.evaluate('window.location.href');
    console.log('[TEST-TL] Initial page:', title, currentHref);

    if (title && title.includes('Login') || (currentHref && currentHref.includes('/login'))) {
        console.log('[TEST-TL] Logging in as admin / admin123...');
        await cdp.evaluate(`(() => {
            const u = document.querySelector('input[name="username"]') || document.getElementById('username');
            const p = document.querySelector('input[name="password"]') || document.getElementById('password');
            if (u) u.value = 'admin';
            if (p) p.value = 'admin123';
            const btn = document.getElementById('btn-submit');
            if (btn) btn.click();
            else {
                const f = document.querySelector('form');
                if (f) f.submit();
            }
        })()`);
        await sleep(4000);
        console.log('[TEST-TL] Post-login URL:', await cdp.evaluate('window.location.href'));
        console.log('[TEST-TL] Re-navigating to ' + targetUrl + '...');
        await cdp.send('Page.navigate', { url: targetUrl });
        await sleep(4000);
    }

    console.log('[TEST-TL] Initializing map and checking translines...');
    // If setup screen is open, open the map
    await cdp.evaluate(`(() => {
        const btn = document.getElementById('btn-setup-open-map');
        if (btn && $('#gis-setup-screen').is(':visible')) {
            const sel = document.getElementById('setup-feeder-select');
            if (sel && sel.options.length > 1 && !sel.value) {
                sel.selectedIndex = 1;
            }
            btn.click();
        }
    })()`);
    await sleep(4000);

    // Verify IS_ADMIN is true for logged in admin
    const isAdminVal = await cdp.evaluate('typeof IS_ADMIN !== "undefined" ? IS_ADMIN : null');
    console.log('[TEST-TL] IS_ADMIN in page:', isAdminVal);

    // Find any rendered transline on the map
    const translineInfo = await cdp.evaluate(`(() => {
        const entries = window.allTranslineEntries || [];
        if (entries.length === 0) {
            if (window.currentGisData && window.currentGisData.translines && window.currentGisData.translines.length > 0) {
                return {
                    status: 'data_present_but_not_rendered',
                    translinesInGisData: window.currentGisData.translines.length
                };
            }
            return null;
        }
        
        // Find 3327 or first entry
        const entry = entries.find(e => (e.data && e.data.transline_code && e.data.transline_code.includes('3327'))) || entries[0];
        return {
            id: entry.id,
            code: entry.data ? entry.data.transline_code : null,
            totalEntries: entries.length
        };
    })()`);
    console.log('[TEST-TL] Found transline:', JSON.stringify(translineInfo, null, 2));

    if (translineInfo && translineInfo.id) {
        // Trigger popup click on this transline
        console.log('[TEST-TL] Triggering click on transline hitPoly #' + translineInfo.id + '...');
        const popupOpened = await cdp.evaluate(`(() => {
            const entry = (window.allTranslineEntries || []).find(e => Number(e.id) === ${translineInfo.id});
            if (entry && entry.hitPoly) {
                entry.hitPoly.fire('click', {
                    latlng: entry.hitPoly.getLatLngs()[0]
                });
                return true;
            }
            return false;
        })()`);
        console.log('[TEST-TL] Popup trigger result:', popupOpened);
        await sleep(1500);

        // Verify popup DOM content
        const popupCheck = await cdp.evaluate(`(() => {
            const popup = document.querySelector('.leaflet-popup-content');
            if (!popup) return { visible: false };
            const text = popup.innerText;
            const hasEditBtn = popup.innerHTML.includes('Edit Transline');
            const hasDelBtn = popup.innerHTML.includes('Hapus Transline');
            const codeEl = popup.querySelector('span[style*="font-weight: 700"]');
            return {
                visible: true,
                hasEditBtn,
                hasDelBtn,
                codeText: codeEl ? codeEl.innerText.trim() : null,
                preview: text.substring(0, 150)
            };
        })()`);
        console.log('[TEST-TL] Popup verification:', JSON.stringify(popupCheck, null, 2));
        await cdp.captureScreenshot(path.join(ARTIFACT_DIR, 'test_transline_popup_actions.png'));

        // Test Edit Transline from popup
        console.log('[TEST-TL] Calling window.editTranslineFromPopup(' + translineInfo.id + ')...');
        await cdp.evaluate(`window.editTranslineFromPopup(${translineInfo.id})`);
        await sleep(1500);

        const editSheetCheck = await cdp.evaluate(`(() => {
            const sheet = document.getElementById('offcanvas-conductor-spec-sheet');
            return {
                visible: sheet && sheet.classList.contains('show'),
                source: document.getElementById('spec-source-name') ? document.getElementById('spec-source-name').innerText : null,
                target: document.getElementById('spec-target-name') ? document.getElementById('spec-target-name').innerText : null,
                condType: document.getElementById('spec-conductor-type') ? document.getElementById('spec-conductor-type').value : null,
                condSize: document.getElementById('spec-conductor-size') ? document.getElementById('spec-conductor-size').value : null
            };
        })()`);
        console.log('[TEST-TL] Edit Conductor Sheet check:', JSON.stringify(editSheetCheck, null, 2));
        await cdp.captureScreenshot(path.join(ARTIFACT_DIR, 'test_transline_edit_sheet.png'));

        // Close edit sheet
        await cdp.evaluate(`(() => {
            const el = document.getElementById('offcanvas-conductor-spec-sheet');
            if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                const inst = bootstrap.Offcanvas.getInstance(el) || new bootstrap.Offcanvas(el);
                inst.hide();
            } else if (window.$) {
                $('#offcanvas-conductor-spec-sheet').offcanvas('hide');
            }
        })()`);
        await sleep(1000);

        // Test Delete Transline from popup
        console.log('[TEST-TL] Calling window.deleteTranslineFromPopup(' + translineInfo.id + ')...');
        await cdp.evaluate(`window.deleteTranslineFromPopup(${translineInfo.id})`);
        await sleep(1500);

        const delSheetCheck = await cdp.evaluate(`(() => {
            const sheet = document.getElementById('offcanvas-delete-connection-sheet');
            return {
                visible: sheet && sheet.classList.contains('show'),
                code: document.getElementById('del-segment-code') ? document.getElementById('del-segment-code').innerText : null,
                source: document.getElementById('del-source-name') ? document.getElementById('del-source-name').innerText : null,
                target: document.getElementById('del-target-name') ? document.getElementById('del-target-name').innerText : null,
                distance: document.getElementById('del-distance-meters') ? document.getElementById('del-distance-meters').innerText : null,
                spec: document.getElementById('del-conductor-spec') ? document.getElementById('del-conductor-spec').innerText : null
            };
        })()`);
        console.log('[TEST-TL] Delete Sheet check:', JSON.stringify(delSheetCheck, null, 2));
        await cdp.captureScreenshot(path.join(ARTIFACT_DIR, 'test_transline_delete_confirm_sheet.png'));

        // Close sheet
        await cdp.evaluate(`(() => {
            const el = document.getElementById('offcanvas-delete-connection-sheet');
            if (typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
                const inst = bootstrap.Offcanvas.getInstance(el) || new bootstrap.Offcanvas(el);
                inst.hide();
            } else if (window.$) {
                $('#offcanvas-delete-connection-sheet').offcanvas('hide');
            }
        })()`);
        await sleep(1000);
    }

    edgeProc.kill();
    console.log('[TEST-TL] Test completed successfully!');
}

run().catch(err => {
    console.error('[TEST-TL] Error:', err);
    process.exit(1);
});
