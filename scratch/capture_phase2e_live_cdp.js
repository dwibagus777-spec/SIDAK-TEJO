const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const PORT = 9345;
const ARTIFACT_DIR = "C:\\Users\\INSPEKSIKOTA\\.gemini\\antigravity\\brain\\e5e83a3d-4509-4271-8129-9355c979dd3c";

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function getJson(url) {
    return new Promise((resolve, reject) => {
        http.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try { resolve(JSON.parse(data)); }
                catch (e) { reject(e); }
            });
        }).on('error', reject);
    });
}

class CDP {
    constructor(wsUrl) {
        this.ws = new WebSocket(wsUrl);
        this.id = 1;
        this.handlers = new Map();
        this.events = new Map();
    }
    async init() {
        return new Promise((resolve, reject) => {
            this.ws.onopen = () => resolve();
            this.ws.onerror = err => reject(err);
            this.ws.onmessage = (event) => {
                const msg = JSON.parse(event.data);
                if (msg.id && this.handlers.has(msg.id)) {
                    const { resolve, reject } = this.handlers.get(msg.id);
                    this.handlers.delete(msg.id);
                    if (msg.error) reject(new Error(msg.error.message));
                    else resolve(msg.result);
                } else if (msg.method) {
                    const cbs = this.events.get(msg.method) || [];
                    cbs.forEach(cb => cb(msg.params));
                }
            };
        });
    }
    async send(method, params = {}) {
        const id = this.id++;
        return new Promise((resolve, reject) => {
            this.handlers.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
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
}

async function captureScreenshot(cdp, outputPath, fullPage = false) {
    const params = { format: 'png' };
    if (fullPage) {
        params.captureBeyondViewport = true;
    }
    const res = await cdp.send('Page.captureScreenshot', params);
    const buffer = Buffer.from(res.data, 'base64');
    fs.writeFileSync(outputPath, buffer);
    console.log(`📸 Screenshot saved: ${path.basename(outputPath)} (${buffer.length} bytes)`);
}

async function main() {
    const tmpDir = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'chrome_phase2e_' + Date.now());
    fs.mkdirSync(tmpDir, { recursive: true });

    let cookieTxt = '';
    const cookiePath = 'e:\\XAMPP\\htdocs\\SIDAK TEJO\\scratch\\prod_cookie.txt';
    if (fs.existsSync(cookiePath)) cookieTxt = fs.readFileSync(cookiePath, 'utf8');
    let sessionVal = '';
    const m = cookieTxt.match(/sidaktejo_session\s+([a-f0-9]+)/);
    if (m) sessionVal = m[1];

    console.log("Launching headless Chrome on port " + PORT + "...");
    const chrome = spawn(CHROME_PATH, [
        '--headless=new',
        `--remote-debugging-port=${PORT}`,
        `--user-data-dir=${tmpDir}`,
        '--ignore-certificate-errors',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--disable-extensions',
        '--host-resolver-rules=MAP sidaktejo.site 91.108.119.5,MAP *.sidaktejo.site 91.108.119.5',
        '--window-size=1440,900',
        'about:blank'
    ]);

    for (let i = 0; i < 30; i++) {
        await sleep(300);
        try {
            await getJson(`http://127.0.0.1:${PORT}/json/version`);
            break;
        } catch (e) {}
    }

    const tabs = await getJson(`http://127.0.0.1:${PORT}/json/list`);
    const pageTab = tabs.find(t => t.type === 'page') || tabs[0];
    const cdp = new CDP(pageTab.webSocketDebuggerUrl);
    await cdp.init();

    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');

    if (sessionVal) {
        await cdp.send('Network.setCookie', {
            name: 'sidaktejo_session',
            value: sessionVal,
            domain: 'sidaktejo.site',
            path: '/',
            httpOnly: true,
            secure: true
        });
    }

    console.log("Navigating to https://sidaktejo.site/dashboard...");
    await cdp.send('Page.navigate', { url: 'https://sidaktejo.site/dashboard' });

    // Wait for document ready & elements
    for (let i = 0; i < 40; i++) {
        await sleep(500);
        const ready = await cdp.evaluate(`document.readyState === 'complete' && !!document.querySelector('.constellation-canvas-card')`);
        if (ready) {
            console.log(`Phase 2E Constellation Canvas ready after ${(i + 1) * 0.5}s!`);
            break;
        }
    }
    await sleep(3000);

    // Live Forensic Audit of Phase 2E Elements
    const forensic = await cdp.evaluate(`(() => {
        const canvas = document.querySelector('.constellation-canvas-card');
        const csCanvas = canvas ? window.getComputedStyle(canvas) : null;
        const centralNode = document.getElementById('node-hub-dashboard');
        const csCentral = centralNode ? window.getComputedStyle(centralNode) : null;
        const contextualCard = document.querySelector('.contextual-dashboard-card');
        const csContextual = contextualCard ? window.getComputedStyle(contextualCard) : null;
        const floatingDock = document.querySelector('.sidak-floating-dock');
        const csDock = floatingDock ? window.getComputedStyle(floatingDock) : null;

        const nodes = Array.from(document.querySelectorAll('.constellation-node')).map(n => ({
            id: n.id,
            label: n.querySelector('.constellation-node-label') ? n.querySelector('.constellation-node-label').innerText : '',
            rect: n.getBoundingClientRect()
        }));

        return {
            canvasDisplay: csCanvas ? csCanvas.display : null,
            canvasMinHeight: csCanvas ? csCanvas.minHeight : null,
            canvasBg: csCanvas ? csCanvas.backgroundColor : null,
            centralNodePresent: !!centralNode,
            contextualPresent: !!contextualCard,
            contextualCardBg: csContextual ? csContextual.backgroundColor : null,
            floatingDockPresent: !!floatingDock,
            floatingDockDisplay: csDock ? csDock.display : null,
            satelliteNodeCount: nodes.length,
            nodes: nodes,
            kpiCardCount: document.querySelectorAll('.contextual-kpi-card').length,
            hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth,
            docScrollWidth: document.documentElement.scrollWidth,
            winInnerWidth: window.innerWidth
        };
    })()`);

    console.log("\n=== Live Phase 2E Constellation Forensic Audit ===");
    console.log(JSON.stringify(forensic, null, 2));

    // 1. Desktop Viewport 1440x900 - Mission Control Launcher Primary View
    console.log("\n1. Capturing Desktop 1440x900 (Mission Control Launcher)...");
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 1440,
        height: 900,
        deviceScaleFactor: 1,
        mobile: false
    });
    await sleep(1500);
    await captureScreenshot(cdp, path.join(ARTIFACT_DIR, 'phase2e_menu_desktop_1440.png'));

    // 2. Interactive Constellation Hover Preview (hover GIS node)
    console.log("2. Capturing Constellation Node Hover Preview (GIS Node)...");
    await cdp.evaluate(`if (typeof window.showNodePreview === 'function') window.showNodePreview('gis');`);
    await sleep(800);
    await captureScreenshot(cdp, path.join(ARTIFACT_DIR, 'phase2e_menu_hover_gis.png'));
    // Reset back to dashboard view
    await cdp.evaluate(`if (typeof window.showNodePreview === 'function') window.showNodePreview('dashboard');`);
    await sleep(500);

    // 3. Command Palette Modal (Ctrl+K / Cmd+K style matching media_1789284433272.png)
    console.log("3. Capturing Command Palette Modal...");
    await cdp.evaluate(`if (typeof window.openCommandPalette === 'function') window.openCommandPalette();`);
    await sleep(800);
    await captureScreenshot(cdp, path.join(ARTIFACT_DIR, 'phase2e_command_palette.png'));
    await cdp.evaluate(`if (typeof window.closeCommandPalette === 'function') window.closeCommandPalette();`);
    await sleep(500);

    // 4. Secondary Sidebar Drawer Open (Offcanvas Drawer)
    console.log("4. Capturing Secondary Sidebar Drawer Opened...");
    await cdp.evaluate(`if (typeof window.toggleSidebarDrawer === 'function') window.toggleSidebarDrawer();`);
    await sleep(800);
    await captureScreenshot(cdp, path.join(ARTIFACT_DIR, 'phase2e_sidebar_drawer.png'));
    await cdp.evaluate(`if (typeof window.toggleSidebarDrawer === 'function') window.toggleSidebarDrawer();`);
    await sleep(500);

    // 5. Tablet Viewport 768x1024
    console.log("\n5. Capturing Tablet 768x1024...");
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 768,
        height: 1024,
        deviceScaleFactor: 1,
        mobile: true
    });
    await sleep(1500);
    await captureScreenshot(cdp, path.join(ARTIFACT_DIR, 'phase2e_menu_tablet_768.png'));

    // 6. Mobile Viewport 390x844
    console.log("\n6. Capturing Mobile 390x844...");
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 390,
        height: 844,
        deviceScaleFactor: 1,
        mobile: true
    });
    await sleep(1500);
    await captureScreenshot(cdp, path.join(ARTIFACT_DIR, 'phase2e_menu_mobile_390.png'));

    chrome.kill();
    console.log("\nAll live Phase 2E screenshots captured and saved successfully!");
    process.exit(0);
}

main().catch(e => {
    console.error(e);
    process.exit(1);
});
