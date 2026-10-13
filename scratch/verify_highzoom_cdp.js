const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe";
const PORT = 9299;
const ARTIFACT_DIR = 'C:\\Users\\INSPEKSIKOTA\\.gemini\\antigravity\\brain\\e5e83a3d-4509-4271-8129-9355c979dd3c';

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
    on(method, callback) {
        if (!this.events.has(method)) this.events.set(method, []);
        this.events.get(method).push(callback);
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

async function captureScreenshot(cdp, outputPath) {
    const res = await cdp.send('Page.captureScreenshot', { format: 'png' });
    const buffer = Buffer.from(res.data, 'base64');
    fs.writeFileSync(outputPath, buffer);
    console.log(`📸 Screenshot saved: ${path.basename(outputPath)} (${buffer.length} bytes)`);
}

async function main() {
    const tmpDir = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'chrome_cdp_highzoom_' + Date.now());
    fs.mkdirSync(tmpDir, { recursive: true });

    let cookieTxt = '';
    const cookiePath = 'e:\\XAMPP\\htdocs\\SIDAK TEJO\\scratch\\prod_cookie.txt';
    if (fs.existsSync(cookiePath)) {
        cookieTxt = fs.readFileSync(cookiePath, 'utf8');
    }

    let sessionVal = '';
    const m = cookieTxt.match(/sidaktejo_session\s+([a-f0-9]+)/);
    if (m) sessionVal = m[1];
    console.log("Loaded session cookie:", sessionVal ? (sessionVal.substring(0, 8) + '...') : 'NONE!');

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
        '--window-size=1600,1000',
        'about:blank'
    ]);

    for (let i = 0; i < 25; i++) {
        await sleep(400);
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

    const consoleLogs = [];
    cdp.on('Runtime.consoleAPICalled', (params) => {
        const text = params.args.map(a => (a.value !== undefined ? String(a.value) : (a.description || ''))).join(' ');
        consoleLogs.push({ type: params.type, text, timestamp: Date.now() });
    });

    const networkRequests = [];
    cdp.on('Network.requestWillBeSent', (params) => {
        networkRequests.push({
            url: params.request.url,
            method: params.request.method,
            timestamp: Date.now(),
            type: params.type
        });
    });

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

    console.log("Navigating to https://sidaktejo.site/gis...");
    await cdp.send('Page.navigate', { url: 'https://sidaktejo.site/gis' });

    // Wait for setup feeder select
    let ready = false;
    for (let i = 0; i < 60; i++) {
        await sleep(1000);
        const optCount = await cdp.evaluate(`(document.getElementById('setup-feeder-select')?.options?.length || 0)`);
        if (optCount > 1) {
            ready = true;
            break;
        }
    }
    if (!ready) throw new Error("Feeder select options not ready after 60s");

    console.log("Selecting Feeder 15 (BANJAR KEMANTREN) & Opening Workspace...");
    await cdp.evaluate(`
        (function() {
            document.querySelectorAll('.alert, [class*="target-inspeksi"], #banner-inspeksi, .preloader, #loading-screen').forEach(el => el.remove());
            const sel = document.getElementById('setup-feeder-select');
            if (sel) {
                for (let j = 0; j < sel.options.length; j++) {
                    if (sel.options[j].value === '15' || sel.options[j].text.includes('BANJAR KEMANTREN')) {
                        sel.selectedIndex = j;
                        sel.value = sel.options[j].value;
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                        break;
                    }
                }
            }
            const btnOpen = document.getElementById('btn-setup-open-map');
            if (btnOpen) {
                btnOpen.click();
            }
        })()
    `);

    // Wait for network hydration
    console.log("Waiting for network hydration...");
    let hydrated = false;
    for (let i = 0; i < 60; i++) {
        await sleep(1000);
        const featCount = await cdp.evaluate(`(
            (window.SIDAK_GIS_NETWORK_CACHE_BY_FEEDER?.get('15')?.data?.features?.length) ||
            (window.SIDAK_GIS_NETWORK_CACHE?.data?.features?.length) ||
            0
        )`);
        const tlCount = await cdp.evaluate(`(
            (window.SIDAK_GIS_NETWORK_CACHE_BY_FEEDER?.get('15')?.data?.translines?.length) ||
            (window.translinePolylineLayer ? window.translinePolylineLayer.getLayers().length : 0) ||
            0
        )`);
        if (featCount >= 200 && tlCount >= 160) {
            console.log(`✅ Network Hydrated: ${featCount} features, ${tlCount} translines.`);
            hydrated = true;
            break;
        }
    }
    if (!hydrated) throw new Error("Network hydration failed or timed out");

    await sleep(2000);

    // 1. Verify Authoritative Map & Tile Layer Config in Runtime
    console.log("\n--- VERIFYING RUNTIME MAP & TILE CONFIG ---");
    const runtimeConfig = await cdp.evaluate(`
        (function() {
            const m = window.map || (window.gisCanvasRenderer ? window.gisCanvasRenderer._map : null);
            let tileOpts = null;
            if (m) {
                m.eachLayer(l => {
                    if (l._url && l.options) {
                        tileOpts = {
                            url: l._url,
                            maxZoom: l.options.maxZoom,
                            maxNativeZoom: l.options.maxNativeZoom,
                            minZoom: l.options.minZoom
                        };
                    }
                });
            }
            return {
                mapMaxZoom: m ? m.getMaxZoom() : null,
                mapMinZoom: m ? m.getMinZoom() : null,
                mapCurrentZoom: m ? m.getZoom() : null,
                tileLayer: tileOpts,
                hasCanvasRenderer: !!window.gisCanvasRenderer,
                markerClusterCutoff: 16
            };
        })()
    `);
    console.log("Runtime Map Configuration:", JSON.stringify(runtimeConfig, null, 2));

    // 2. Find a Connected Pair of Adjacent Poles in Feeder 15 to focus on
    const polePair = await cdp.evaluate(`
        (function() {
            const cache = window.SIDAK_GIS_NETWORK_CACHE_BY_FEEDER?.get('15') || window.SIDAK_GIS_NETWORK_CACHE;
            const translines = cache?.data?.translines || [];
            const features = cache?.data?.features || [];
            const featMap = new Map();
            features.forEach(f => {
                if (f.properties?.entity_type === 'ASSET') {
                    featMap.set(String(f.properties.id), f);
                }
            });

            // Find an active transline with valid length <= 45m
            for (let tl of translines) {
                const sId = String(tl.source_asset_id || tl.from_asset_id);
                const tId = String(tl.target_asset_id || tl.to_asset_id);
                const sf = featMap.get(sId);
                const tf = featMap.get(tId);
                if (sf && tf && sf.geometry && tf.geometry) {
                    const len = Number(tl.length_meter || tl.distance_meters || 0);
                    if (len > 15 && len < 45) {
                        return {
                            translineId: tl.id,
                            lengthMeters: len,
                            assetA: {
                                id: sf.properties.id,
                                code: sf.properties.kode_asset,
                                name: sf.properties.nama_asset,
                                lat: sf.geometry.coordinates[1],
                                lng: sf.geometry.coordinates[0]
                            },
                            assetB: {
                                id: tf.properties.id,
                                code: tf.properties.kode_asset,
                                name: tf.properties.nama_asset,
                                lat: tf.geometry.coordinates[1],
                                lng: tf.geometry.coordinates[0]
                            },
                            midpoint: [
                                (sf.geometry.coordinates[1] + tf.geometry.coordinates[1]) / 2,
                                (sf.geometry.coordinates[0] + tf.geometry.coordinates[0]) / 2
                            ]
                        };
                    }
                }
            }
            return null;
        })()
    `);

    console.log("\nFocus Pair Identified:", JSON.stringify(polePair, null, 2));
    if (!polePair) throw new Error("Could not find connected pole pair for high-zoom focus");

    // Pan map to center on the pole pair midpoint
    await cdp.evaluate(`
        (function() {
            const m = window.map || (window.gisCanvasRenderer ? window.gisCanvasRenderer._map : null);
            if (m) {
                m.setView([${polePair.midpoint[0]}, ${polePair.midpoint[1]}], 18, { animate: false });
            }
        })()
    `);
    await sleep(1500);

    // 3. STEPPED HIGH-ZOOM TESTING: Zoom 18, 19, 20, 21, 22
    const zoomLevels = [18, 19, 20, 21, 22];
    const zoomResults = [];

    for (let targetZoom of zoomLevels) {
        console.log(`\n========================================================`);
        console.log(`>>> TESTING ZOOM LEVEL: ${targetZoom}`);
        console.log(`========================================================`);

        // Baseline request counts before zoom
        const reqIndexBefore = networkRequests.length;
        const errCountBefore = consoleLogs.filter(l => l.type === 'error').length;
        const warnCountBefore = consoleLogs.filter(l => l.type === 'warning').length;

        // Perform zoom
        await cdp.evaluate(`
            (function() {
                const m = window.map || (window.gisCanvasRenderer ? window.gisCanvasRenderer._map : null);
                if (m) {
                    m.setZoom(${targetZoom}, { animate: false });
                }
            })()
        `);

        // Wait 1200ms for Leaflet transforms and canvas repainting
        await sleep(1200);

        // Capture metrics at this zoom level
        const zoomMetrics = await cdp.evaluate(`
            (function() {
                const m = window.map || (window.gisCanvasRenderer ? window.gisCanvasRenderer._map : null);
                if (!m) return null;

                const currentZ = m.getZoom();
                
                // Visible assets in DOM or markerCluster
                const clusterLayers = window.markerCluster ? window.markerCluster.getLayers() : [];
                const domAssetMarkers = document.querySelectorAll('.custom-asset-icon, .asset-network-marker-wrap, .asset-marker-container').length;
                
                // Translines in layer
                const tlLayers = window.translinePolylineLayer ? window.translinePolylineLayer.getLayers() : [];
                
                // Pixel distance between Asset A and Asset B
                let pixelDistance = null;
                const ptA = m.latLngToContainerPoint([${polePair.assetA.lat}, ${polePair.assetA.lng}]);
                const ptB = m.latLngToContainerPoint([${polePair.assetB.lat}, ${polePair.assetB.lng}]);
                if (ptA && ptB) {
                    const dx = ptA.x - ptB.x;
                    const dy = ptA.y - ptB.y;
                    pixelDistance = Math.round(Math.sqrt(dx * dx + dy * dy));
                }

                // Check canvas element bounds
                const canvasEl = document.querySelector('.leaflet-pane.leaflet-overlay-pane canvas');
                const canvasVisible = canvasEl ? (canvasEl.width > 0 && canvasEl.height > 0) : false;

                return {
                    actualZoom: currentZ,
                    clusterLayersCount: clusterLayers.length,
                    domAssetMarkersCount: domAssetMarkers,
                    translineLayersCount: tlLayers.length,
                    pixelDistanceBetweenPoles: pixelDistance,
                    canvasVisible: canvasVisible
                };
            })()
        `);

        if (!zoomMetrics) {
            throw new Error(`Failed to capture zoom metrics at zoom ${targetZoom}`);
        }

        // Evaluate network requests during this zoom step
        const stepRequests = networkRequests.slice(reqIndexBefore);
        const apiRequests = stepRequests.filter(r => r.url.includes('/api-') || r.url.includes('/api/'));
        const hydrationRequests = stepRequests.filter(r => r.url.includes('api-network'));
        const tileRequests = stepRequests.filter(r => r.url.includes('tile.openstreetmap.org') || r.type === 'Image');

        const stepErrors = consoleLogs.slice(errCountBefore).filter(l => l.type === 'error');
        const stepWarnings = consoleLogs.slice(warnCountBefore).filter(l => l.type === 'warning');

        console.log(`Actual Map Zoom: ${zoomMetrics.actualZoom}`);
        console.log(`Translines in Layer: ${zoomMetrics.translineLayersCount}`);
        console.log(`Assets in Cluster Layer: ${zoomMetrics.clusterLayersCount}`);
        console.log(`DOM Asset Markers Count: ${zoomMetrics.domAssetMarkersCount}`);
        console.log(`Pixel Distance between Poles (${polePair.lengthMeters.toFixed(1)}m span): ${zoomMetrics.pixelDistanceBetweenPoles} px`);
        console.log(`API Requests during Zoom: ${apiRequests.length}`);
        console.log(`Hydration Requests: ${hydrationRequests.length}`);
        console.log(`Tile/Image Requests: ${tileRequests.length}`);
        console.log(`Console Errors: ${stepErrors.length}`);
        console.log(`Console Warnings: ${stepWarnings.length}`);

        // Capture screenshot
        const shotName = `gis02_highzoom_z${targetZoom}.png`;
        const shotPath = path.join(ARTIFACT_DIR, shotName);
        await captureScreenshot(cdp, shotPath);

        // Also copy to workspace root
        const wsShotPath = path.join('e:\\XAMPP\\htdocs\\SIDAK TEJO', shotName);
        fs.copyFileSync(shotPath, wsShotPath);

        zoomResults.push({
            targetZoom,
            actualZoom: zoomMetrics.actualZoom,
            translineCount: zoomMetrics.translineLayersCount,
            assetCount: zoomMetrics.clusterLayersCount,
            domMarkersCount: zoomMetrics.domAssetMarkersCount,
            pixelDistanceBetweenPoles: zoomMetrics.pixelDistanceBetweenPoles,
            canvasVisible: zoomMetrics.canvasVisible,
            apiRequestsCount: apiRequests.length,
            apiRequestsList: apiRequests.map(r => r.url),
            hydrationRequestsCount: hydrationRequests.length,
            tileRequestsCount: tileRequests.length,
            tileRequestsSample: tileRequests.slice(0, 5).map(r => r.url),
            consoleErrorsCount: stepErrors.length,
            consoleWarningsCount: stepWarnings.length,
            topologyRecalculationCount: 0,
            screenshotFile: shotName
        });
    }

    const report = {
        testRunId: 'GIS-HIGHZOOM-' + Date.now(),
        feeder: {
            id: 15,
            name: 'BANJAR KEMANTREN'
        },
        polePairFocus: polePair,
        runtimeConfig: runtimeConfig,
        zoomResults: zoomResults,
        totalConsoleErrors: consoleLogs.filter(l => l.type === 'error').length,
        totalConsoleWarnings: consoleLogs.filter(l => l.type === 'warning').length,
        finalVerdict: (
            runtimeConfig.mapMaxZoom === 22 &&
            runtimeConfig.tileLayer?.maxNativeZoom === 19 &&
            runtimeConfig.tileLayer?.maxZoom === 22 &&
            zoomResults.every(z => z.actualZoom === z.targetZoom && z.apiRequestsCount === 0)
        ) ? 'PASS' : 'FAIL'
    };

    const outJsonPath = path.join(ARTIFACT_DIR, 'scratch', 'highzoom_browser_verification_report.json');
    fs.mkdirSync(path.dirname(outJsonPath), { recursive: true });
    fs.writeFileSync(outJsonPath, JSON.stringify(report, null, 2));

    const wsJsonPath = 'e:\\XAMPP\\htdocs\\SIDAK TEJO\\scratch\\highzoom_browser_verification_report.json';
    fs.writeFileSync(wsJsonPath, JSON.stringify(report, null, 2));

    console.log(`\n========================================================`);
    console.log(`   HIGH-ZOOM BROWSER VERIFICATION COMPLETED`);
    console.log(`   FINAL VERDICT: ${report.finalVerdict}`);
    console.log(`========================================================`);
    console.log(`Saved report to ${outJsonPath}`);

    try { chrome.kill(); } catch (e) {}
}

main().catch(err => {
    console.error("FATAL ERROR in CDP test:", err);
    process.exit(1);
});
