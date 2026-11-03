const http = require('http');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const ARTIFACT_DIR = process.env.ARTIFACT_DIR || path.join(__dirname, '..', 'writable', 'audits');
if (!fs.existsSync(ARTIFACT_DIR)) {
    fs.mkdirSync(ARTIFACT_DIR, { recursive: true });
}
const defaultEdgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const altEdgePath = 'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe';
const EDGE_PATH = process.env.EDGE_PATH || (fs.existsSync(defaultEdgePath) ? defaultEdgePath : altEdgePath);
const USER_DATA_DIR = path.join(os.tmpdir(), 'edge_mobile_' + Date.now());
const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

// Minimal 100-byte valid JPEG
const VALID_JPEG_BASE64 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=';
const TEST_IMAGE_PATH = path.join(os.tmpdir(), 'test_dummy_photo.jpg');

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
                if (msg.method === 'Runtime.consoleAPICalled') {
                    const args = (msg.params.args || []).map(a => a.value || a.description).join(' ');
                    console.log('[BROWSER-CONSOLE]', msg.params.type, args);
                }
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

    async close() {
        if (this.ws) {
            try { this.ws.close(); } catch (e) {}
        }
    }
}

async function main() {
    console.log('====================================================================');
    console.log('E2E HEADLESS MOBILE VERIFICATION: TEMUAN FLOW & ROLE SCOPING');
    console.log('====================================================================\n');

    // Ensure dummy image exists
    const scratchDir = path.dirname(TEST_IMAGE_PATH);
    if (!fs.existsSync(scratchDir)) fs.mkdirSync(scratchDir, { recursive: true });
    fs.writeFileSync(TEST_IMAGE_PATH, Buffer.from(VALID_JPEG_BASE64, 'base64'));
    console.log('[Setup] Test image prepared at:', TEST_IMAGE_PATH);

    if (!fs.existsSync(USER_DATA_DIR)) fs.mkdirSync(USER_DATA_DIR, { recursive: true });

    const edgeProc = spawn(EDGE_PATH, [
        '--headless=new',
        '--remote-debugging-port=9222',
        '--no-first-run',
        '--disable-gpu',
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

    if (!versionInfo || !versionInfo.webSocketDebuggerUrl) {
        throw new Error('Edge CDP debugger failed to start.');
    }
    console.log('[Setup] Edge CDP connected.');

    const targets = await getJson('http://127.0.0.1:9222/json/list');
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const cdp = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await cdp.connect();

    await cdp.send('Page.enable');
    await cdp.send('DOM.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');

    // Emulate Mobile Device (Huawei/Samsung phone, 375x667, touch enabled)
    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width: 375,
        height: 667,
        deviceScaleFactor: 2,
        mobile: true
    });
    await cdp.send('Emulation.setTouchEmulationEnabled', {
        enabled: true,
        maxTouchPoints: 5
    });
    console.log('[Setup] Mobile emulation configured (375x667, mobile: true, touch: true).\n');

    const testSummary = {};

    // -------------------------------------------------------------------------
    // TEST 1: LOGIN AS ADMIN
    // -------------------------------------------------------------------------
    console.log('--- TEST 1: Admin Login & Mobile Viewport Navigation ---');
    await cdp.send('Page.navigate', { url: BASE_URL + '/login' });
    await sleep(2000);

    await cdp.evaluate(`(() => {
        document.querySelector('input[name="username"]').value = 'admin';
        document.querySelector('input[name="password"]').value = 'admin123';
        document.querySelector('button[type="submit"], input[type="submit"]').click();
        return true;
    })()`);
    await sleep(3000);

    const afterLoginUrl = await cdp.evaluate('window.location.href');
    console.log(' - Post-Login URL:', afterLoginUrl);
    const loginOk = !afterLoginUrl.includes('/login');
    console.log(' - Admin Login Status:', loginOk ? 'PASS' : 'FAIL');
    testSummary['admin_login'] = loginOk ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // TEST 2: MOBILE DROPDOWN (PRIORITAS & PELAKSANA) BOTTOM SHEET BEHAVIOR
    // -------------------------------------------------------------------------
    console.log('\n--- TEST 2: Mobile Dropdown (Select2) Bottom Sheet Verification ---');
    await cdp.send('Page.navigate', { url: BASE_URL + '/temuan/create' });
    await sleep(3000);

    // Open Prioritas dropdown
    console.log(' - Waiting for Select2 initialization...');
    for (let i = 0; i < 20; i++) {
        const isReady = await cdp.evaluate(`(() => {
            return typeof jQuery !== 'undefined' && $('#prioritas').length > 0 && $('#prioritas').hasClass('select2-hidden-accessible');
        })()`);
        if (isReady) break;
        await sleep(500);
    }

    console.log(' - Tapping Prioritas dropdown...');
    await cdp.evaluate(`(() => {
        if ($.fn.select2) {
            $('#prioritas').select2('open');
        }
        if (!document.querySelector('.select2-container--open')) {
            $('#prioritas').next('.select2-container').find('.select2-selection').trigger('click');
        }
        return true;
    })()`);
    await sleep(1000);

    // Inspect Dropdown CSS & Positioning
    const prioritasRaw = await cdp.evaluate(`
        (() => {
            const dropdown = document.querySelector('.select2-container--open .select2-dropdown');
            if (!dropdown) return JSON.stringify({ found: false });
            const rect = dropdown.getBoundingClientRect();
            const computed = window.getComputedStyle(dropdown);
            const searchField = dropdown.querySelector('.select2-search');
            const searchVisible = searchField && (searchField.offsetWidth > 0 || searchField.offsetHeight > 0);
            const options = Array.from(dropdown.querySelectorAll('.select2-results__option')).map(o => o.innerText.trim());
            const header = document.querySelector('.mobile-app-header');
            const headerRect = header ? header.getBoundingClientRect() : null;
            return JSON.stringify({
                found: true,
                position: computed.position,
                bottom: computed.bottom,
                rect: { top: rect.top, bottom: rect.bottom, height: rect.height, width: rect.width },
                headerRect: headerRect ? { top: headerRect.top, bottom: headerRect.bottom, height: headerRect.height } : null,
                searchVisible: !!searchVisible,
                optionCount: options.length,
                options
            });
        })()
    `);
    const prioritasDropdownInfo = JSON.parse(prioritasRaw);

    console.log(' Prioritas Dropdown Analysis:');
    console.log('   * Found open dropdown:', prioritasDropdownInfo.found);
    console.log('   * CSS Position:', prioritasDropdownInfo.position);
    console.log('   * CSS Bottom:', prioritasDropdownInfo.bottom);
    console.log('   * Bounding Rect:', JSON.stringify(prioritasDropdownInfo.rect));
    console.log('   * Search Field Visible (Keyboard trigger):', prioritasDropdownInfo.searchVisible ? 'YES (BAD)' : 'NO (GOOD - Infinity Search)');
    console.log('   * Options available count:', prioritasDropdownInfo.optionCount);

    // Verify it does NOT collide with header
    const topCollision = prioritasDropdownInfo.found && prioritasDropdownInfo.rect.top < 56 && prioritasDropdownInfo.position !== 'fixed';
    console.log('   * Top Header Collision:', topCollision ? 'YES (FAIL - Header Collided)' : 'NO (PASS - Clear of Header)');

    // Capture screenshot of mobile bottom sheet
    const prioritasSheetPath = path.join(ARTIFACT_DIR, 'mobile_prioritas_bottom_sheet.png');
    await cdp.captureScreenshot(prioritasSheetPath);
    console.log('   * Screenshot captured:', prioritasSheetPath);

    // Select 'HIGH' from options
    await cdp.evaluate(`(() => {
        $('#prioritas').val('HIGH').trigger('change').select2('close');
        return true;
    })()`);
    await sleep(400);
    const selectedPrioritas = await cdp.evaluate(`$('#prioritas').val()`);
    console.log('   * Selected Prioritas Value:', selectedPrioritas);

    // Open Pelaksana dropdown
    console.log(' - Tapping Pelaksana dropdown...');
    await cdp.evaluate(`(() => {
        $('#pelaksana').select2('open');
        return true;
    })()`);
    await sleep(600);

    const pelaksanaRaw = await cdp.evaluate(`
        (() => {
            const dropdown = document.querySelector('.select2-container--open .select2-dropdown');
            if (!dropdown) return JSON.stringify({ found: false });
            const rect = dropdown.getBoundingClientRect();
            const computed = window.getComputedStyle(dropdown);
            const searchField = dropdown.querySelector('.select2-search');
            const searchVisible = searchField && (searchField.offsetWidth > 0 || searchField.offsetHeight > 0);
            const options = Array.from(dropdown.querySelectorAll('.select2-results__option')).map(o => o.innerText.trim());
            return JSON.stringify({
                found: true,
                position: computed.position,
                bottom: computed.bottom,
                rect: { top: rect.top, bottom: rect.bottom, height: rect.height, width: rect.width },
                searchVisible: !!searchVisible,
                options
            });
        })()
    `);
    const pelaksanaDropdownInfo = JSON.parse(pelaksanaRaw);

    console.log(' Pelaksana Dropdown Analysis:');
    console.log('   * Found open dropdown:', pelaksanaDropdownInfo.found);
    console.log('   * CSS Position:', pelaksanaDropdownInfo.position);
    console.log('   * Search Field Visible:', pelaksanaDropdownInfo.searchVisible ? 'YES' : 'NO');
    console.log('   * Options available:', pelaksanaDropdownInfo.options ? pelaksanaDropdownInfo.options.join(', ') : 'none');

    const pelaksanaSheetPath = path.join(ARTIFACT_DIR, 'mobile_pelaksana_bottom_sheet.png');
    await cdp.captureScreenshot(pelaksanaSheetPath);
    console.log('   * Screenshot captured:', pelaksanaSheetPath);

    // Select 'HAR KONSTRUKSI'
    await cdp.evaluate(`(() => {
        $('#pelaksana').val('HAR KONSTRUKSI').trigger('change').select2('close');
        return true;
    })()`);
    await sleep(400);
    const selectedPelaksana = await cdp.evaluate(`$('#pelaksana').val()`);
    console.log('   * Selected Pelaksana Value:', selectedPelaksana);

    const dropdownPass = prioritasDropdownInfo.found && !topCollision && !prioritasDropdownInfo.searchVisible;
    testSummary['mobile_dropdown_bottom_sheet'] = dropdownPass ? 'PASS' : 'FAIL';

    // -------------------------------------------------------------------------
    // TEST 3: CASCADING DROPDOWNS RESET ON CHANGE
    // -------------------------------------------------------------------------
    console.log('\n--- TEST 3: Cascading Dropdowns Reset Behavior ---');
    // Set ULP to Porong (3), wait for penyulang, pick first penyulang
    await cdp.evaluate(`(() => {
        $('#ulp_id').val('3').trigger('change');
        return true;
    })()`);
    await sleep(1500);

    const penyulangPorongCount = await cdp.evaluate(`$('#penyulang_id option').length`);
    console.log(' - Porong (ULP 3) Penyulang Options Count:', penyulangPorongCount);

    // Pick first valid feeder
    await cdp.evaluate(`(() => {
        const firstVal = $('#penyulang_id option:not([value=""])').first().val();
        $('#penyulang_id').val(firstVal).trigger('change');
        return true;
    })()`);
    await sleep(1500);

    const feederSelected = await cdp.evaluate(`$('#penyulang_id').val()`);
    console.log(' - Selected Feeder ID:', feederSelected);

    // Now change ULP to Sidoarjo Kota (1)
    console.log(' - Changing ULP to Sidoarjo Kota (1)...');
    await cdp.evaluate(`(() => {
        $('#ulp_id').val('1').trigger('change');
        return true;
    })()`);
    await sleep(1500);

    const feederAfterUlpChange = await cdp.evaluate(`$('#penyulang_id').val()`);
    const sectionAfterUlpChange = await cdp.evaluate(`$('#section_id').val()`);
    const assetAfterUlpChange = await cdp.evaluate(`$('#mr01_asset_id').val()`);
    console.log(' - Feeder after ULP change (Should be empty):', `"${feederAfterUlpChange}"`);
    console.log(' - Section after ULP change (Should be empty):', `"${sectionAfterUlpChange}"`);
    console.log(' - Asset after ULP change (Should be empty):', `"${assetAfterUlpChange}"`);

    const cascadePass = (!feederAfterUlpChange || feederAfterUlpChange === '') &&
                        (!sectionAfterUlpChange || sectionAfterUlpChange === '') &&
                        (!assetAfterUlpChange || assetAfterUlpChange === '');
    testSummary['cascading_reset'] = cascadePass ? 'PASS' : 'FAIL';
    console.log(' - Cascading Reset Status:', cascadePass ? 'PASS' : 'FAIL');

    // -------------------------------------------------------------------------
    // TEST 4: ADMIN CREATE TEMUAN & DETAIL NAVIGATION
    // -------------------------------------------------------------------------
    console.log('\n--- TEST 4: Admin Save Temuan Flow & Detail Verification ---');
    // Fill in required fields for Sidoarjo Kota
    await cdp.evaluate(`(() => {
        $('#ulp_id').val('1').trigger('change');
        return true;
    })()`);
    await sleep(1500);

    await cdp.evaluate(`(() => {
        const fVal = $('#penyulang_id option:not([value=""])').first().val();
        $('#penyulang_id').val(fVal).trigger('change');
        return true;
    })()`);
    await sleep(1500);

    await cdp.evaluate(`(() => {
        const sVal = $('#section_id option:not([value=""])').first().val();
        $('#section_id').val(sVal).trigger('change');
        $('#jenis_temuan').val('KONSTRUKSI').trigger('change');
        $('#pelaksana').val('HAR KONSTRUKSI').trigger('change');
        $('#prioritas').val('HIGH').trigger('change');
        $('#potensi_gangguan').val('OCR').trigger('change');
        $('#conductor_type').val('AAAC').trigger('change');
        $('#conductor_size').val('150').trigger('change');
        $('#noga').val('NOGA-E2E-ADMIN');
        $('#detail_temuan').val('Verifikasi Independen E2E Mobile Temuan Admin');
        $('#alamat').val('Jl. Ahmad Yani No. 1 Sidoarjo');
        return true;
    })()`);
    await sleep(500);

    // Attach valid dummy photo using DOM.setFileInputFiles
    const docNode = await cdp.send('DOM.getDocument');
    const fileInputNode = await cdp.send('DOM.querySelector', {
        nodeId: docNode.root.nodeId,
        selector: 'input#foto'
    });

    if (fileInputNode && fileInputNode.nodeId) {
        await cdp.send('DOM.setFileInputFiles', {
            nodeId: fileInputNode.nodeId,
            files: [TEST_IMAGE_PATH]
        });
        console.log(' - Attached test photo via CDP setFileInputFiles.');
        await cdp.evaluate(`(() => {
            $('#foto').trigger('change');
            return true;
        })()`);
        await sleep(1500);
    } else {
        console.log(' - WARNING: File input input#foto not found!');
    }

    // Submit form via #btn-submit
    console.log(' - Submitting temuan form as Admin via #btn-submit...');
    await cdp.evaluate(`(() => {
        document.getElementById('btn-submit').click();
        return true;
    })()`);

    // Wait for redirect to detail
    await sleep(5000);
    const postAdminUrl = await cdp.evaluate('window.location.href');
    console.log(' - Redirected URL:', postAdminUrl);

    // Verify detail page
    const adminDetailRaw = await cdp.evaluate(`
        (() => {
            const swalTitle = document.querySelector('.swal2-title') ? document.querySelector('.swal2-title').innerText : '';
            const swalContent = document.querySelector('.swal2-html-container') ? document.querySelector('.swal2-html-container').innerText : '';
            const flashError = document.querySelector('.alert-danger') ? document.querySelector('.alert-danger').innerText : '';
            const h1 = document.querySelector('h1, h2, .page-title') ? document.querySelector('h1, h2, .page-title').innerText : '';
            const bodyText = document.body.innerText;
            const hasAccessError = bodyText.includes('Temuan tidak ditemukan atau Anda tidak memiliki akses ke data tersebut');
            return JSON.stringify({
                url: window.location.href,
                swalTitle,
                swalContent,
                flashError,
                hasAccessError,
                isDetailPage: window.location.href.includes('/temuan/detail/'),
                h1
            });
        })()
    `);
    const adminDetailCheck = JSON.parse(adminDetailRaw);

    console.log(' Admin Detail Page Evaluation:');
    console.log('   * Is Detail Page:', adminDetailCheck.isDetailPage);
    console.log('   * Heading / Title:', adminDetailCheck.h1);
    console.log('   * SweetAlert Error:', adminDetailCheck.swalTitle || adminDetailCheck.swalContent ? `"${adminDetailCheck.swalTitle}: ${adminDetailCheck.swalContent}"` : 'NONE (CORRECT)');
    console.log('   * Flash Error:', adminDetailCheck.flashError || 'NONE (CORRECT)');
    console.log('   * Has Access Denied Error:', adminDetailCheck.hasAccessError ? 'YES (FAIL - Access Denied Bug!)' : 'NO (PASS - Access Granted)');

    const adminDetailScreenshot = path.join(ARTIFACT_DIR, 'mobile_admin_detail_success.png');
    await cdp.captureScreenshot(adminDetailScreenshot);
    console.log('   * Screenshot captured:', adminDetailScreenshot);

    const adminFlowPass = adminDetailCheck.isDetailPage && !adminDetailCheck.hasAccessError;
    testSummary['admin_save_and_detail_flow'] = adminFlowPass ? 'PASS' : 'FAIL';

    // Extract created Admin temuan ID from URL
    const adminCreatedIdMatch = postAdminUrl.match(/\/temuan\/detail\/(\d+)/);
    const adminCreatedId = adminCreatedIdMatch ? parseInt(adminCreatedIdMatch[1], 10) : null;
    console.log('   * Extracted Created Temuan ID:', adminCreatedId);

    // -------------------------------------------------------------------------
    // TEST 5: LOGOUT & LOGIN AS INSPEKSI PORONG (ROLE RESTRICTION & SAVE FLOW)
    // -------------------------------------------------------------------------
    console.log('\n--- TEST 5: Inspeksi Porong Scoped Form & Save Flow Verification ---');
    await cdp.send('Page.navigate', { url: BASE_URL + '/logout' });
    await sleep(2000);

    // Login as inspeksi_prg
    await cdp.send('Page.navigate', { url: BASE_URL + '/login' });
    await sleep(2000);

    await cdp.evaluate(`(() => {
        document.querySelector('input[name="username"]').value = 'inspeksi_prg';
        document.querySelector('input[name="password"]').value = 'admin123';
        document.querySelector('button[type="submit"], input[type="submit"]').click();
        return true;
    })()`);
    await sleep(3000);

    const afterInspeksiLoginUrl = await cdp.evaluate('window.location.href');
    console.log(' - Post-Login URL (Inspeksi):', afterInspeksiLoginUrl);

    // Navigate to /temuan/create
    await cdp.send('Page.navigate', { url: BASE_URL + '/temuan/create' });
    await sleep(3000);

    // Check ULP dropdown restriction
    const inspeksiFormStateRaw = await cdp.evaluate(`
        (() => {
            const ulpSelect = document.querySelector('#ulp_id');
            const ulpOptions = Array.from(ulpSelect.options).map(o => ({ value: o.value, text: o.text.trim() }));
            return JSON.stringify({
                ulpValue: ulpSelect.value,
                ulpOptionsCount: ulpOptions.length,
                ulpOptions
            });
        })()
    `);
    const inspeksiFormState = inspeksiFormStateRaw ? JSON.parse(inspeksiFormStateRaw) : { ulpValue: '', ulpOptionsCount: 0, ulpOptions: [] };

    console.log(' Inspeksi Form State:');
    console.log('   * Pre-selected ULP Value:', inspeksiFormState.ulpValue);
    console.log('   * Available ULP Count:', inspeksiFormState.ulpOptionsCount, '(Expected: 1)');
    console.log('   * Options:', JSON.stringify(inspeksiFormState.ulpOptions));

    const isRestrictedCorrect = (inspeksiFormState.ulpOptionsCount === 1) && (inspeksiFormState.ulpValue === '3');
    console.log('   * ULP Scoping Restriction:', isRestrictedCorrect ? 'PASS (Locked to Porong)' : 'FAIL (Leaked ULPs)');
    testSummary['inspeksi_ulp_scoping'] = isRestrictedCorrect ? 'PASS' : 'FAIL';

    // Wait for penyulang options to populate for ULP 3
    console.log(' - Waiting for Porong feeders to populate...');
    for (let i = 0; i < 20; i++) {
        const pCount = await cdp.evaluate(`(() => {
            return $('#penyulang_id option:not([value=""])').length;
        })()`);
        if (pCount > 0) break;
        await sleep(500);
    }

    const debugPenyulang = await cdp.evaluate(`(() => {
        return JSON.stringify({
            ulp: $('#ulp_id').val(),
            penyulangCount: $('#penyulang_id option').length,
            options: Array.from(document.querySelectorAll('#penyulang_id option')).map(o => ({ v: o.value, t: o.text }))
        });
    })()`);
    console.log(' - Debug Penyulang Options:', debugPenyulang);

    // Explicitly trigger change if options are not loaded yet
    await cdp.evaluate(`(() => {
        if ($('#penyulang_id option:not([value=""])').length === 0) {
            $('#ulp_id').trigger('change');
        }
        return true;
    })()`);
    await sleep(1500);

    // Select first feeder in Porong
    const selectedFeeder = await cdp.evaluate(`(() => {
        const fVal = $('#penyulang_id option:not([value=""])').first().val();
        if (fVal) {
            $('#penyulang_id').val(fVal).trigger('change');
            if ($.fn.select2) $('#penyulang_id').trigger('change.select2');
        }
        return fVal;
    })()`);
    console.log(' - Selected Feeder in Porong:', selectedFeeder);
    await sleep(1500);

    // Wait for sections to populate
    console.log(' - Waiting for sections to populate...');
    for (let i = 0; i < 20; i++) {
        const sCount = await cdp.evaluate(`(() => {
            return $('#section_id option:not([value=""])').length;
        })()`);
        if (sCount > 0) break;
        await sleep(500);
    }

    const selectedSection = await cdp.evaluate(`(() => {
        const sVal = $('#section_id option:not([value=""])').first().val();
        if (sVal) {
            $('#section_id').val(sVal).trigger('change');
            if ($.fn.select2) $('#section_id').trigger('change.select2');
        }
        $('#jenis_temuan').val('ROW').trigger('change');
        $('#pelaksana').val('HAR ROW').trigger('change');
        $('#prioritas').val('MEDIUM').trigger('change');
        $('#potensi_gangguan').val('DGR').trigger('change');
        $('#conductor_type').val('AAACS').trigger('change');
        $('#conductor_size').val('150').trigger('change');
        $('#noga').val('NOGA-E2E-INSPEKSI');
        $('#detail_temuan').val('Verifikasi Independen E2E Mobile Temuan Inspeksi Porong');
        $('#alamat').val('Jl. Raya Porong No. 15');
        return sVal;
    })()`);
    console.log(' - Selected Section in Porong:', selectedSection);
    await sleep(500);

    // Attach valid dummy photo
    const docNodeInspeksi = await cdp.send('DOM.getDocument');
    const fileInputNodeInspeksi = await cdp.send('DOM.querySelector', {
        nodeId: docNodeInspeksi.root.nodeId,
        selector: 'input#foto'
    });

    if (fileInputNodeInspeksi && fileInputNodeInspeksi.nodeId) {
        await cdp.send('DOM.setFileInputFiles', {
            nodeId: fileInputNodeInspeksi.nodeId,
            files: [TEST_IMAGE_PATH]
        });
        console.log(' - Attached test photo via CDP setFileInputFiles.');
        await cdp.evaluate(`(() => {
            $('#foto').trigger('change');
            return true;
        })()`);
        await sleep(1500);
    }

    // Log form values before submit
    const formValuesRaw = await cdp.evaluate(`(() => {
        return JSON.stringify({
            ulp_id: $('#ulp_id').val(),
            penyulang_id: $('#penyulang_id').val(),
            section_id: $('#section_id').val(),
            asset_id: $('#mr01_asset_id').val(),
            authoritative_asset_id: $('#authoritative_asset_id').val(),
            noga: $('#noga').val(),
            latitude: $('#latitude').val(),
            longitude: $('#longitude').val()
        });
    })()`);
    console.log(' - Inspeksi Form Payload Before Submit:', formValuesRaw);

    // Submit form via #btn-submit
    console.log(' - Submitting temuan form as Inspeksi Porong via #btn-submit...');
    await cdp.evaluate(`(() => {
        document.getElementById('btn-submit').click();
        return true;
    })()`);

    // Wait for redirect to detail
    await sleep(5000);
    const postInspeksiUrl = await cdp.evaluate('window.location.href');
    console.log(' - Redirected URL (Inspeksi):', postInspeksiUrl);

    // Verify detail page
    const inspeksiDetailRaw = await cdp.evaluate(`
        (() => {
            const swalTitle = document.querySelector('.swal2-title') ? document.querySelector('.swal2-title').innerText : '';
            const swalContent = document.querySelector('.swal2-html-container') ? document.querySelector('.swal2-html-container').innerText : '';
            const flashError = document.querySelector('.alert-danger') ? document.querySelector('.alert-danger').innerText : '';
            const h1 = document.querySelector('h1, h2, .page-title') ? document.querySelector('h1, h2, .page-title').innerText : '';
            const bodyText = document.body.innerText;
            const hasAccessError = bodyText.includes('Temuan tidak ditemukan atau Anda tidak memiliki akses ke data tersebut');
            return JSON.stringify({
                url: window.location.href,
                swalTitle,
                swalContent,
                flashError,
                hasAccessError,
                isDetailPage: window.location.href.includes('/temuan/detail/'),
                h1
            });
        })()
    `);
    const inspeksiDetailCheck = JSON.parse(inspeksiDetailRaw);

    console.log(' Inspeksi Detail Page Evaluation:');
    console.log('   * Is Detail Page:', inspeksiDetailCheck.isDetailPage);
    console.log('   * Heading / Title:', inspeksiDetailCheck.h1);
    console.log('   * SweetAlert Error:', inspeksiDetailCheck.swalTitle || inspeksiDetailCheck.swalContent ? `"${inspeksiDetailCheck.swalTitle}: ${inspeksiDetailCheck.swalContent}"` : 'NONE (CORRECT)');
    console.log('   * Flash Error:', inspeksiDetailCheck.flashError || 'NONE (CORRECT)');
    console.log('   * Has Access Denied Error:', inspeksiDetailCheck.hasAccessError ? 'YES (FAIL - Access Denied Bug!)' : 'NO (PASS - Access Granted)');

    const inspeksiDetailScreenshot = path.join(ARTIFACT_DIR, 'mobile_inspeksi_detail_success.png');
    await cdp.captureScreenshot(inspeksiDetailScreenshot);
    console.log('   * Screenshot captured:', inspeksiDetailScreenshot);

    const inspeksiFlowPass = inspeksiDetailCheck.isDetailPage && !inspeksiDetailCheck.hasAccessError;
    testSummary['inspeksi_save_and_detail_flow'] = inspeksiFlowPass ? 'PASS' : 'FAIL';

    const inspeksiCreatedIdMatch = postInspeksiUrl.match(/\/temuan\/detail\/(\d+)/);
    const inspeksiCreatedId = inspeksiCreatedIdMatch ? parseInt(inspeksiCreatedIdMatch[1], 10) : null;
    console.log('   * Extracted Created Temuan ID:', inspeksiCreatedId);

    // Close CDP & Browser
    await cdp.close();
    try { edgeProc.kill(); } catch (e) {}

    // -------------------------------------------------------------------------
    // SUMMARY
    // -------------------------------------------------------------------------
    console.log('\n====================================================================');
    console.log('E2E HEADLESS MOBILE VERIFICATION RESULTS:');
    console.log('====================================================================');
    let allPassed = true;
    for (const [k, v] of Object.entries(testSummary)) {
        console.log(` [${v}] ${k}`);
        if (v !== 'PASS') allPassed = false;
    }
    console.log('====================================================================');
    console.log('Created Test IDs to verify/cleanup in DB: Admin=' + adminCreatedId + ', Inspeksi=' + inspeksiCreatedId);
    
    // Save report to JSON
    fs.writeFileSync(path.join(ARTIFACT_DIR, 'e2e_mobile_verification_results.json'), JSON.stringify({
        summary: testSummary,
        adminCreatedId,
        inspeksiCreatedId,
        allPassed
    }, null, 2));

    process.exit(allPassed ? 0 : 1);
}

main().catch(err => {
    console.error('[E2E-ERROR]', err);
    process.exit(1);
});
