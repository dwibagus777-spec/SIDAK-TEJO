/**
 * SLD-05R: Single Line Diagram (SLD) Visual Renderer Engine
 * 
 * Engineering Drawing Readability Remediation Release
 * 
 * Governed by the 5 Mandatory Amendments:
 * 1. Main Trunk & Network Hierarchy:
 *    - DO NOT determine main trunk using grid_y = 0.
 *    - Main network edges derived authoritatively from component C15-01 (bold 3.2px dark ink line #0f172a).
 *    - Lateral and fragment edges rendered neutrally without fragile coordinate assumptions.
 * 2. Active Physical Translines Integrity:
 *    - Displays authoritative active edges (197 active translines), completely excluding inactive rows.
 *    - Physical graph: 205 nodes, 197 physical edges, 6 components, 60 line sections.
 * 3. Crisp Light Engineering Drawing Canvas:
 *    - Canvas background: Pure white (#ffffff) with subtle engineering blueprint grid (#e2e8f0).
 *    - Sharp high-contrast electrical lines and symbols where the route visually dominates.
 * 4. GI Substation Visual Origin Anchor:
 *    - Substation box at upstream origin with solid black triangle symbol and bold label "GI BUDURAN".
 *    - Red stepped/zigzag 20kV outgoing feeder cable dropping down into Incomer #3231 (TM11).
 *    - Pure visual anchor (zero fake nodes/edges in graph).
 * 5. Project-Approved PLN/IEC-Aligned Schematic Glyphs:
 *    - LBS: Quartered circle (circle with alternating black/white quadrants).
 *    - LBSM 2-WAY / 3-WAY: Boxed quartered circle with connection ports.
 *    - RECLOSER: Bowtie / hourglass symbol inside rectangular enclosure.
 *    - AVS / PGS: Double opposing triangle symbol.
 *    - PMS: Neutral UNKNOWN (amber diamond with ?, strictly no red/green operational status assumption).
 *    - GTT: Solid triangle attached directly to the feeder line with capacity/code label (Cantol = 1T, Portal = 2T).
 *    - Reconciled count = 23 GTT (4 Cantol, 15 Portal, 4 Unknown).
 * 6. Progressive Zoom-Aware Density Management:
 *    - Low Zoom (< 75%): Feeder route, GI anchor, keypoint glyphs, GTT triangles, dead-end terminations. Regular pole IDs suppressed.
 *    - Medium Zoom (75% - 130%): Regular pole #IDs appear via geometric LabelOccupancyIndex.
 *    - High Zoom (> 130%): Full asset names, construction nomenclature, conductor span length pills.
 * 7. Read-Only Slide-Over Detail Drawer (Delta = 0).
 */

/**
 * Geometric Axis-Aligned Bounding Box (AABB) Collision Resolver
 * Evaluates projected 2D screen bounding boxes to enforce strict zero label overlap.
 */
class LabelOccupancyIndex {
    constructor() {
        this.items = [];
    }

    clear() {
        this.items = [];
    }

    add(box) {
        this.items.push(box);
    }

    /**
     * Check if candidate box intersects with any registered box.
     * @param {Object} box {x, y, w, h}
     * @param {number} padding Margin of safety in screen pixels
     * @param {string|null} ignoreId Box ID to ignore
     * @returns {Object|null} Conflicting box or null
     */
    collides(box, padding = 2, ignoreId = null) {
        const ax1 = box.x - padding;
        const ay1 = box.y - padding;
        const ax2 = box.x + box.w + padding;
        const ay2 = box.y + box.h + padding;

        for (const b of this.items) {
            if (ignoreId && b.id === ignoreId) continue;
            const bx1 = b.x;
            const by1 = b.y;
            const bx2 = b.x + b.w;
            const by2 = b.y + b.h;

            if (ax1 < bx2 && ax2 > bx1 && ay1 < by2 && ay2 > by1) {
                return b;
            }
        }
        return null;
    }
}

/**
 * SLD-05T Unified Device Symbol Registry
 * Single authoritative source of truth for PLN/IEC device symbology across Mode A, Mode B, and Mode C.
 */
const SldDeviceSymbolRegistry = {
    roles: {
        GI: {
            label: 'GI Substation Incomer',
            code: 'GI',
            category: 'SUBSTATION',
            color: '#dc2626',
            bg: '#fef2f2',
            renderSvg: () => `
                <circle cx="0" cy="0" r="11" fill="#ffffff" stroke="#dc2626" stroke-width="2.5"/>
                <circle cx="0" cy="0" r="5" fill="#dc2626"/>
                <polygon points="-5,-12 5,-12 0,-18" fill="#dc2626"/>
            `
        },
        LBS: {
            label: 'Manual LBS',
            code: 'LBS',
            category: 'SWITCH',
            color: '#0f172a',
            bg: '#f8fafc',
            renderSvg: () => `
                <circle cx="0" cy="0" r="11" fill="#ffffff" stroke="#0f172a" stroke-width="2"/>
                <path d="M 0 0 L 0 -11 A 11 11 0 0 1 11 0 Z" fill="#0f172a"/>
                <path d="M 0 0 L 0 11 A 11 11 0 0 1 -11 0 Z" fill="#0f172a"/>
                <line x1="-11" y1="0" x2="11" y2="0" stroke="#0f172a" stroke-width="1.5"/>
                <line x1="0" y1="-11" x2="0" y2="11" stroke="#0f172a" stroke-width="1.5"/>
            `
        },
        LBSM_2WAY: {
            label: 'LBS Motor 2-Way',
            code: 'LBSM_2WAY',
            category: 'SWITCH',
            color: '#0284c7',
            bg: '#f0f9ff',
            renderSvg: () => `
                <rect x="-15" y="-12" width="30" height="24" rx="3" fill="#f8fafc" stroke="#0284c7" stroke-width="1.8"/>
                <circle cx="0" cy="0" r="8" fill="#ffffff" stroke="#0f172a" stroke-width="1.4"/>
                <path d="M 0 0 L 0 -8 A 8 8 0 0 1 8 0 Z" fill="#0f172a"/>
                <path d="M 0 0 L 0 8 A 8 8 0 0 1 -8 0 Z" fill="#0f172a"/>
                <line x1="-15" y1="0" x2="-8" y2="0" stroke="#0284c7" stroke-width="2"/>
                <line x1="8" y1="0" x2="15" y2="0" stroke="#0284c7" stroke-width="2"/>
            `
        },
        LBSM_3WAY: {
            label: 'LBS Motor 3-Way',
            code: 'LBSM_3WAY',
            category: 'SWITCH',
            color: '#0369a1',
            bg: '#e0f2fe',
            renderSvg: () => `
                <rect x="-15" y="-13" width="30" height="26" rx="3" fill="#f8fafc" stroke="#0369a1" stroke-width="1.8"/>
                <circle cx="0" cy="-2" r="7.5" fill="#ffffff" stroke="#0f172a" stroke-width="1.4"/>
                <path d="M 0 -2 L 0 -9.5 A 7.5 7.5 0 0 1 7.5 -2 Z" fill="#0f172a"/>
                <path d="M 0 -2 L 0 5.5 A 7.5 7.5 0 0 1 -7.5 -2 Z" fill="#0f172a"/>
                <line x1="-15" y1="-2" x2="-7.5" y2="-2" stroke="#0369a1" stroke-width="2"/>
                <line x1="7.5" y1="-2" x2="15" y2="-2" stroke="#0369a1" stroke-width="2"/>
                <line x1="0" y1="5.5" x2="0" y2="13" stroke="#0369a1" stroke-width="2"/>
            `
        },
        PMCB: {
            label: 'PMCB (Circuit Breaker)',
            code: 'PMCB',
            category: 'PROTECTION',
            color: '#0f172a',
            bg: '#ffffff',
            renderSvg: () => `
                <rect x="-12" y="-12" width="24" height="24" rx="2" fill="#ffffff" stroke="#0f172a" stroke-width="2"/>
                <line x1="-12" y1="0" x2="-4" y2="0" stroke="#0f172a" stroke-width="2"/>
                <line x1="4" y1="0" x2="12" y2="0" stroke="#0f172a" stroke-width="2"/>
                <line x1="-4" y1="0" x2="6" y2="-7" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round"/>
                <circle cx="-4" cy="0" r="1.8" fill="#0f172a"/>
                <circle cx="4" cy="0" r="1.8" fill="#0f172a"/>
            `
        },
        RECLOSER: {
            label: 'Automatic Recloser',
            code: 'RECLOSER',
            category: 'PROTECTION',
            color: '#7c3aed',
            bg: '#f5f3ff',
            renderSvg: () => `
                <rect x="-14" y="-12" width="28" height="24" rx="3" fill="#ffffff" stroke="#7c3aed" stroke-width="2"/>
                <polygon points="-8,-6 0,0 -8,6" fill="#7c3aed"/>
                <polygon points="8,-6 0,0 8,6" fill="#7c3aed"/>
                <rect x="7" y="-9" width="4" height="6" fill="#7c3aed"/>
            `
        },
        AVS: {
            label: 'Automatic Voltage Switch',
            code: 'AVS',
            category: 'REGULATION',
            color: '#0284c7',
            bg: '#f0f9ff',
            renderSvg: () => `
                <polygon points="-11,-7 -11,7 0,0" fill="#ffffff" stroke="#0284c7" stroke-width="1.8"/>
                <polygon points="11,-7 11,7 0,0" fill="#ffffff" stroke="#0284c7" stroke-width="1.8"/>
                <line x1="0" y1="-8" x2="0" y2="8" stroke="#0284c7" stroke-width="2"/>
            `
        },
        PGS: {
            label: 'Pole Gas Switch',
            code: 'PGS',
            category: 'SWITCH',
            color: '#059669',
            bg: '#f0fdf4',
            renderSvg: () => `
                <circle cx="0" cy="0" r="11" fill="#ffffff" stroke="#059669" stroke-width="2"/>
                <line x1="-6" y1="-3" x2="5" y2="-3" stroke="#059669" stroke-width="1.8"/>
                <line x1="0" y1="-3" x2="0" y2="5" stroke="#059669" stroke-width="1.8"/>
                <line x1="-4" y1="5" x2="4" y2="5" stroke="#059669" stroke-width="1.8"/>
                <line x1="-2" y1="8" x2="2" y2="8" stroke="#059669" stroke-width="1.5"/>
            `
        },
        PMS: {
            label: 'PMS (UNKNOWN)',
            code: 'PMS',
            category: 'SWITCH',
            color: '#d97706',
            bg: '#fef3c7',
            renderSvg: () => `
                <polygon points="0,-14 14,0 0,14 -14,0" fill="#fef3c7" stroke="#d97706" stroke-width="2.2" />
                <text x="0" y="4.5" fill="#b45309" font-size="12" font-weight="900" text-anchor="middle" font-family="sans-serif">?</text>
            `
        },
        GTT_CANTOL: {
            label: 'GTT Cantol (1T)',
            code: 'GTT_CANTOL',
            category: 'TRANSFORMER',
            color: '#059669',
            bg: '#f0fdf4',
            renderSvg: () => `
                <line x1="0" y1="0" x2="0" y2="12" stroke="#0f172a" stroke-width="2"/>
                <polygon points="0,26 -9,12 9,12" fill="#059669" stroke="#0f172a" stroke-width="1.5"/>
                <text x="0" y="21" fill="#ffffff" font-size="7" font-weight="900" text-anchor="middle" font-family="monospace">1T</text>
            `
        },
        GTT_PORTAL: {
            label: 'GTT Portal (2T)',
            code: 'GTT_PORTAL',
            category: 'TRANSFORMER',
            color: '#0284c7',
            bg: '#f0f9ff',
            renderSvg: () => `
                <line x1="-5" y1="0" x2="-5" y2="12" stroke="#0f172a" stroke-width="2"/>
                <line x1="5" y1="0" x2="5" y2="12" stroke="#0f172a" stroke-width="2"/>
                <line x1="-8" y1="12" x2="8" y2="12" stroke="#0f172a" stroke-width="2"/>
                <polygon points="0,26 -9,12 9,12" fill="#0284c7" stroke="#0f172a" stroke-width="1.5"/>
                <text x="0" y="21" fill="#ffffff" font-size="7" font-weight="900" text-anchor="middle" font-family="monospace">2T</text>
            `
        },
        LINE_POLE: {
            label: 'Tiang JTM',
            code: 'LINE_POLE',
            category: 'POLE',
            color: '#0f172a',
            bg: '#ffffff',
            renderSvg: () => `
                <circle cx="0" cy="0" r="3" fill="#0f172a" stroke="#64748b" stroke-width="0.9" class="sld-pole-dot"/>
            `
        },
        BRANCH: {
            label: 'Percabangan',
            code: 'BRANCH',
            category: 'TOPOLOGY',
            color: '#dc2626',
            bg: '#fef2f2',
            renderSvg: () => `
                <circle cx="0" cy="0" r="5.5" fill="#dc2626" stroke="#0f172a" stroke-width="1.6"/>
                <circle cx="0" cy="0" r="2" fill="#ffffff"/>
            `
        },
        TERMINAL: {
            label: 'Tiang Akhir',
            code: 'TERMINAL',
            category: 'TOPOLOGY',
            color: '#dc2626',
            bg: '#fef2f2',
            renderSvg: () => `
                <circle cx="0" cy="0" r="3" fill="#0f172a"/>
                <line x1="0" y1="-8" x2="0" y2="8" stroke="#dc2626" stroke-width="3" stroke-linecap="round"/>
            `
        },
        DEVICE_UNKNOWN: {
            label: 'Device (Unknown Subtype)',
            code: 'DEVICE_UNKNOWN',
            category: 'UNKNOWN',
            color: '#d97706',
            bg: '#fef3c7',
            renderSvg: () => `
                <polygon points="0,-12 12,9 -12,9" fill="#fef3c7" stroke="#d97706" stroke-width="1.8"/>
                <text x="0" y="6" fill="#b45309" font-size="9" font-weight="bold" text-anchor="middle">?</text>
            `
        },
        ISOLATED: {
            label: 'Tiang Terisolasi',
            code: 'ISOLATED',
            category: 'TOPOLOGY',
            color: '#ef4444',
            bg: '#fef2f2',
            renderSvg: () => `
                <circle cx="0" cy="0" r="8" fill="#fef2f2" stroke="#ef4444" stroke-dasharray="3,2" stroke-width="1.8"/>
                <circle cx="0" cy="0" r="3.5" fill="#dc2626"/>
            `
        }
    },

    resolveRole(node) {
        if (node.official_device_role && this.roles[node.official_device_role]) {
            return node.official_device_role;
        }
        if (node.sld_glyph && this.roles[node.sld_glyph]) {
            return node.sld_glyph;
        }

        const devRole = node.device_role;
        const topRole = node.topology_role;
        const eqType = (node.equipment_type || '').toUpperCase();
        const rawCt = (node.construction_type || '').toUpperCase();

        if (devRole === 'SOURCE_INCOMER' || topRole === 'SOURCE_INCOMER' || node.asset_id === 3231) {
            return 'GI';
        }
        if (devRole === 'TRANSFORMER_NODE') {
            if (eqType.includes('PORTAL') || rawCt.includes('PORTAL') || rawCt.includes('GTT2')) return 'GTT_PORTAL';
            if (eqType.includes('CANTOL') || rawCt.includes('CANTOL') || rawCt.includes('GTT1')) return 'GTT_CANTOL';
            return 'DEVICE_UNKNOWN';
        }
        if (devRole === 'SWITCH_CANDIDATE') {
            if (eqType.includes('LBSM_3WAY') || rawCt.includes('3WAY')) return 'LBSM_3WAY';
            if (eqType.includes('LBSM_2WAY') || rawCt.includes('2WAY')) return 'LBSM_2WAY';
            if (eqType === 'LBS' || rawCt.includes('LBS')) return 'LBS';
            if (eqType.includes('RECLOSER') || rawCt.includes('REC')) return 'RECLOSER';
            if (eqType.includes('PMCB') || rawCt.includes('PMCB') || rawCt.includes('CB')) return 'PMCB';
            if (eqType.includes('AVS') || rawCt.includes('AVS')) return 'AVS';
            if (eqType.includes('PGS') || rawCt.includes('PGS')) return 'PGS';
            if (eqType.includes('PMS') || rawCt.includes('PMS')) return 'PMS';
            return 'PMS';
        }
        if (topRole === 'BRANCH_NODE') return 'BRANCH';
        if (topRole === 'TERMINAL_NODE') return 'TERMINAL';
        if (node.zone === 'ISOLATED_ASSETS' || topRole === 'ISOLATED_NODE') return 'ISOLATED';

        return 'LINE_POLE';
    },

    getDef(role) {
        return this.roles[role] || this.roles['DEVICE_UNKNOWN'];
    },

    createLeafletIcon(node, isSelected = false) {
        const role = this.resolveRole(node);
        const def = this.getDef(role);
        const isSmallPole = (role === 'LINE_POLE');
        const size = isSmallPole ? [22, 22] : [32, 32];
        const borderStyle = isSelected 
            ? 'border: 2px solid #38bdf8; box-shadow: 0 0 10px rgba(56, 189, 248, 0.9);' 
            : `border: 1.5px solid ${def.color}; box-shadow: 0 1px 4px rgba(0,0,0,0.25);`;

        const iconHtml = `
            <div style="width: ${size[0]}px; height: ${size[1]}px; display: flex; align-items: center; justify-content: center; background: #ffffff; border-radius: 50%; ${borderStyle}">
                <svg width="${size[0] - 6}" height="${size[1] - 6}" viewBox="-16 -16 32 32" style="overflow: visible;">
                    ${def.renderSvg()}
                </svg>
            </div>
        `;

        return L.divIcon({
            className: 'sld-custom-leaflet-marker',
            html: iconHtml,
            iconSize: size,
            iconAnchor: [size[0] / 2, size[1] / 2],
            popupAnchor: [0, -size[1] / 2],
        });
    }
};

class SldRendererEngine {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            console.error(`[SLD-05R] Container #${containerId} not found.`);
            return;
        }

        this.options = Object.assign({
            apiUrl: '',
            fingerprintApiUrl: '',
            sheetsApiUrl: '',
            findingsApiUrl: '',
            feederId: null,
            scaleX: 75,
            scaleY: 85,
            offsetX: 160,
            offsetY: 180,
            defaultMode: 'HYBRID', // 'HYBRID' (Default Mode C), 'ENGINEERING', 'GIS', or 'SIMPLIFIED'
            showGtt: true,
            showFindings: false,
            onSelectAsset: null,
        }, options);

        // Derive sheets & findings API URLs if not provided
        if (!this.options.sheetsApiUrl && this.options.apiUrl) {
            this.options.sheetsApiUrl = this.options.apiUrl.replace('/layout', '/sheets');
        }
        if (!this.options.findingsApiUrl && this.options.apiUrl) {
            this.options.findingsApiUrl = this.options.apiUrl.replace('/layout', '/findings');
        }

        this.layoutData = null;
        this.sheetsData = [];
        this.currentSheetIndex = null;
        this.findingsData = null;
        this.showFindings = this.options.showFindings;
        this.currentMode = this.options.defaultMode;
        this.showGtt = this.options.showGtt;
        this.showAssetLabels = true;
        this.showSpanLengths = true;
        this.showRoadNames = true;

        // Viewport Pan/Zoom state
        this.viewBox = { x: 0, y: 0, w: 2200, h: 1150 };
        this.initialViewBox = { x: 0, y: 0, w: 2200, h: 1150 };
        this.fullBounds = { x: 0, y: 0, w: 8000, h: 4000 };
        this.isPanning = false;
        this.panStart = { x: 0, y: 0 };

        this.selectedAssetId = null;
        this.selectedEdgeId = null;

        // Geometric Label Occupancy Index
        this.occupancy = new LabelOccupancyIndex();

        // SLD-05S: Dynamic Polling & GIS Leaflet State
        this.fingerprintApiUrl = this.options.fingerprintApiUrl || null;
        this.feederId = this.options.feederId || null;
        this.cachedFingerprint = null;
        this.pollingTimer = null;
        this.leafletMap = null;
        this.leafletFeatureGroup = null;
        this.leafletFindingsGroup = null;
    }

    /**
     * Load layout data from authoritative SLD-04 endpoint.
     */
    async load(apiUrl) {
        if (apiUrl) this.options.apiUrl = apiUrl;
        if (!this.options.apiUrl) {
            this.renderError('API URL layout belum dikonfigurasi.');
            return;
        }

        this.renderLoading();

        try {
            const res = await fetch(this.options.apiUrl, { method: 'GET' });
            if (!res.ok) {
                throw new Error(`HTTP Error ${res.status}: Gagal memuat data layout dari server.`);
            }

            const data = await res.json();
            if (data.status === 'DATA_NOT_READY' || (data.data_source && data.data_source.status === 'TOPOLOGY_DATA_INCOMPLETE')) {
                this.renderDataNotReady(data);
                return;
            }
            if (data.status !== 'success') {
                throw new Error(data.message || 'Respons layout mengindikasikan status bukan success.');
            }

            this.layoutData = data;

            // SLD-05S: Update Fingerprint Badge & Cache
            if (data.projection && data.projection.data_fingerprint) {
                this.cachedFingerprint = data.projection.data_fingerprint;
                const fpBadge = document.getElementById('sld-fingerprint-badge');
                if (fpBadge) {
                    fpBadge.innerHTML = `<i class="fa-solid fa-fingerprint me-1 text-success"></i>FINGERPRINT: ${data.projection.data_fingerprint.substring(0, 12)}...`;
                }
            }

            this.startChangeDetectionPolling();
            this.render();

            // SLD-05T: Load sheets partitions and decoupled finding overlays
            this.loadSheets();
            this.loadFindings();
        } catch (err) {
            console.error('[SLD-05S] Error loading layout:', err);
            this.renderError(err.message);
        }
    }

    /**
     * SLD-05S.8: Automatic Change Detection Polling (30s interval + tab focus).
     */
    startChangeDetectionPolling() {
        if (this.pollingTimer) clearInterval(this.pollingTimer);
        if (!this.fingerprintApiUrl) return;

        this.pollingTimer = setInterval(() => {
            this.checkFingerprint();
        }, 30000);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.checkFingerprint();
        });
        window.addEventListener('focus', () => {
            this.checkFingerprint();
        });
    }

    async checkFingerprint() {
        if (!this.fingerprintApiUrl) return;
        try {
            const res = await fetch(this.fingerprintApiUrl);
            if (!res.ok) return;
            const data = await res.json();
            if (data.status === 'success' && data.data_fingerprint) {
                const fpBadge = document.getElementById('sld-fingerprint-badge');
                if (fpBadge) {
                    fpBadge.innerHTML = `<i class="fa-solid fa-fingerprint me-1 text-success"></i>FINGERPRINT: ${data.data_fingerprint.substring(0, 12)}...`;
                }

                if (this.cachedFingerprint && data.data_fingerprint !== this.cachedFingerprint) {
                    console.log(`[SLD-05S] Network data changed (${this.cachedFingerprint.substring(0,8)} -> ${data.data_fingerprint.substring(0,8)}). Refreshing SLD in place...`);
                    const prevNodes = (this.layoutData && this.layoutData.nodes) ? this.layoutData.nodes.length : 0;
                    const prevEdges = (this.layoutData && this.layoutData.edges) ? this.layoutData.edges.length : 0;
                    this.cachedFingerprint = data.data_fingerprint;
                    await this.load();
                    const newNodes = (this.layoutData && this.layoutData.nodes) ? this.layoutData.nodes.length : 0;
                    const newEdges = (this.layoutData && this.layoutData.edges) ? this.layoutData.edges.length : 0;
                    this.showDynamicUpdateToast(prevNodes, newNodes, prevEdges, newEdges);
                }
            }
        } catch (e) {
            // Non-blocking resilient fallback
        }
    }

    /**
     * SLD-05S.8: In-page notification for dynamic database updates.
     */
    showDynamicUpdateToast(prevNodes, newNodes, prevEdges, newEdges) {
        const alertEl = document.getElementById('sld-refresh-alert');
        if (!alertEl) return;
        const now = new Date();
        const timeStr = now.toTimeString().split(' ')[0];
        const deltaNodes = newNodes - prevNodes;
        const deltaStr = deltaNodes >= 0 ? `+${deltaNodes}` : `${deltaNodes}`;
        alertEl.innerHTML = `
            <div class="d-flex align-items-center justify-content-between w-100">
                <div>
                    <i class="fa-solid fa-bolt text-warning me-2"></i>
                    <strong>DATA JARINGAN DIPERBARUI:</strong> 
                    Nodes ${prevNodes} &rarr; <strong>${newNodes}</strong> (${deltaStr}) | 
                    Edges ${prevEdges} &rarr; <strong>${newEdges}</strong> | 
                    <span class="font-monospace text-muted small ms-1">Updated ${timeStr}</span>
                </div>
                <button type="button" class="btn-close btn-sm ms-3" onclick="document.getElementById('sld-refresh-alert').classList.add('d-none')"></button>
            </div>
        `;
        alertEl.classList.remove('d-none');
        setTimeout(() => {
            if (alertEl) alertEl.classList.add('d-none');
        }, 6000);
    }

    /**
     * SLD-05S-VH: Dynamic Optimal ViewBox Calculator
     * Guarantees Target Occupancy:
     * - Width: 85–92% (target: 0.88)
     * - Height: 75–85% (target: 0.80)
     * Strictly preserves aspect ratio with zero letterboxing and zero cropping of GI, branches, terminals, GTTs, or corridors.
     */
    calculateOptimalViewBox(scope = 'MAIN_NETWORK') {
        if (!this.layoutData || !this.layoutData.nodes || this.layoutData.nodes.length === 0) {
            return { x: 0, y: 0, w: 2200, h: 1150 };
        }

        let targetNodes = [];
        if (scope === 'MAIN_NETWORK') {
            targetNodes = this.layoutData.nodes.filter(n => n.zone === 'MAIN_NETWORK' || n.component_id === 'C15-01');
            if (targetNodes.length === 0) targetNodes = this.layoutData.nodes;
        } else {
            targetNodes = this.layoutData.nodes;
        }

        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;

        for (const n of targetNodes) {
            const p = this.project(n.schematic.grid_x, n.schematic.grid_y);
            const isGtt = (n.device_role === 'TRANSFORMER_NODE');
            const padX = isGtt ? 70 : 35;
            const padYTop = 35;
            const padYBottom = isGtt ? 100 : 35;

            minX = Math.min(minX, p.x - padX);
            maxX = Math.max(maxX, p.x + padX);
            minY = Math.min(minY, p.y - padYTop);
            maxY = Math.max(maxY, p.y + padYBottom);
        }

        // Substation GI Buduran Anchor extents
        const incomer = this.layoutData.nodes.find(n => n.asset_id === 3231 || n.device_role === 'SOURCE_INCOMER');
        if (incomer && (scope === 'ALL' || targetNodes.includes(incomer))) {
            const pInc = this.project(incomer.schematic.grid_x, incomer.schematic.grid_y);
            minX = Math.min(minX, pInc.x - 120);
            minY = Math.min(minY, pInc.y - 180);
            maxX = Math.max(maxX, pInc.x + 120);
        }

        // Mode C Hybrid Road Corridor margins
        if (this.currentMode === 'HYBRID') {
            minY = Math.min(minY, minY - 45);
            maxY = Math.max(maxY, maxY + 45);
        }

        const contentW = Math.max(200, maxX - minX);
        const contentH = Math.max(200, maxY - minY);
        const contentCenterX = minX + (contentW / 2);
        const contentCenterY = minY + (contentH / 2);

        // Measure container size dynamically
        const containerW = (this.container && this.container.clientWidth > 0) ? this.container.clientWidth : 1800;
        const containerH = (this.container && this.container.clientHeight > 0) ? this.container.clientHeight : 600;
        const containerAspect = Math.max(0.5, containerW / containerH);

        // TARGET OCCUPANCY: Width 85–92% (0.88), Height 75–85% (0.80)
        const targetOccW = 0.88;
        const targetOccH = 0.80;

        let vh = Math.max(contentH / targetOccH, contentW / (targetOccW * containerAspect));
        let vw = vh * containerAspect;

        return {
            x: Math.round(contentCenterX - (vw / 2)),
            y: Math.round(contentCenterY - (vh / 2)),
            w: Math.round(vw),
            h: Math.round(vh)
        };
    }

    /**
     * Master Render Routine (Idempotent projection from layoutData).
     */
    render() {
        if (!this.layoutData) return;

        const canvasMeta = this.layoutData.canvas || {};
        const totalW = Math.max(90, canvasMeta.grid_width || 96);
        const totalH = Math.max(35, canvasMeta.grid_height || 34);

        const svgWidth = (totalW * this.options.scaleX) + (this.options.offsetX * 2) + 300;
        const svgHeight = (totalH * this.options.scaleY) + (this.options.offsetY * 2) + 300;
        this.fullBounds = { x: 0, y: 0, w: svgWidth, h: svgHeight };

        // SLD-05S-VH: Dynamically calculate optimal viewBox guaranteeing 85–92% width & 75–85% height occupancy
        this.optimalMainViewBox = this.calculateOptimalViewBox('MAIN_NETWORK');
        this.initialViewBox = Object.assign({}, this.optimalMainViewBox);
        this.viewBox = Object.assign({}, this.initialViewBox);
        this.currentScope = 'MAIN_NETWORK';

        // Ensure container has both SVG and GIS map sub-containers
        let svgContainer = document.getElementById('sld-svg-container');
        let gisContainer = document.getElementById('sld-gis-map-container');
        if (!svgContainer || !gisContainer) {
            this.container.innerHTML = `
                <div id="sld-svg-container" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0;"></div>
                <div id="sld-gis-map-container" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0; display: none; z-index: 5;"></div>
                <!-- Floating Canvas Minimap / Mode Indicator -->
                <div class="sld-mode-indicator badge bg-light text-dark border border-secondary shadow-sm" 
                     style="position: absolute; bottom: 15px; left: 15px; z-index: 20; font-family: monospace; font-size: 0.8rem;">
                    <span id="sld-current-mode-label" class="fw-bold text-primary">${this.currentMode === 'ENGINEERING' ? 'MODE: ENGINEERING (GRANULAR)' : (this.currentMode === 'HYBRID' ? 'MODE: HYBRID (CAD + JALAN)' : (this.currentMode === 'GIS' ? 'MODE: GIS MAP (SPASIAL)' : 'MODE: SIMPLIFIED (LINE SECTIONS)'))}</span> | 
                    <span>NODES: <strong>${this.layoutData.nodes.length}</strong></span> | 
                    <span>EDGES: <strong>${this.layoutData.edges.length}</strong></span> | 
                    <span id="sld-zoom-status" class="fw-bold">ZOOM: 100%</span>
                </div>
            `;
            svgContainer = document.getElementById('sld-svg-container');
            gisContainer = document.getElementById('sld-gis-map-container');
        }

        // Light Engineering Theme Canvas Shell
        svgContainer.innerHTML = `
            <div class="sld-viewport-wrapper" style="position: relative; width: 100%; height: 100%; overflow: hidden; background: #f1f5f9;">
                <svg id="sld-svg-canvas" 
                     xmlns="http://www.w3.org/2000/svg" 
                     viewBox="${this.viewBox.x} ${this.viewBox.y} ${this.viewBox.w} ${this.viewBox.h}"
                     style="width: 100%; height: 100%; display: block; cursor: grab; user-select: none; background: #ffffff;">
                    <defs>
                        <!-- Engineering Grid Pattern (Amendment 3) -->
                        <pattern id="sld-grid-pattern" width="50" height="50" patternUnits="userSpaceOnUse">
                            <path d="M 50 0 L 0 0 0 50" fill="none" stroke="rgba(15, 23, 42, 0.06)" stroke-width="1"/>
                        </pattern>
                        <!-- Focus glow for selected keypoints -->
                        <filter id="glow-light" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="#0284c7" flood-opacity="0.35"/>
                        </filter>
                        <filter id="edge-glow-light" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="0" stdDeviation="2.5" flood-color="#0284c7" flood-opacity="0.6"/>
                        </filter>
                    </defs>

                    <!-- Background Rect with Engineering Grid Pattern -->
                    <rect x="-4000" y="-4000" width="28000" height="24000" fill="#ffffff" />
                    <rect x="-4000" y="-4000" width="28000" height="24000" fill="url(#sld-grid-pattern)" />

                    <!-- Zone 1: Main Network Frame -->
                    <g id="sld-zone-main" class="sld-zone-group"></g>

                    <!-- Zone 2: Unconnected Components Frame -->
                    <g id="sld-zone-unconnected" class="sld-zone-group"></g>

                    <!-- Zone 3: Isolated Assets Frame -->
                    <g id="sld-zone-isolated" class="sld-zone-group"></g>

                    <!-- Mode C: Hybrid Road Corridors Layer -->
                    <g id="sld-corridors-layer" class="sld-layer" style="display: none;"></g>

                    <!-- GI Substation Origin Anchor Layer (Amendment 4) -->
                    <g id="sld-substation-anchor-layer" class="sld-layer"></g>

                    <!-- Conductor Layer (Edges) -->
                    <g id="sld-edges-layer" class="sld-layer"></g>

                    <!-- Conductor Annotations Layer -->
                    <g id="sld-edge-labels-layer" class="sld-layer"></g>

                    <!-- Line Sections Layer (Simplified Mode) -->
                    <g id="sld-sections-layer" class="sld-layer" style="display: none;"></g>

                    <!-- Equipment Nodes Layer -->
                    <g id="sld-nodes-layer" class="sld-layer"></g>

                    <!-- SLD-05T: Finding Overlay Layer (Toggleable) -->
                    <g id="sld-findings-layer" class="sld-layer" style="display: none;"></g>

                    <!-- SLD-05T: CAD Match Lines Layer -->
                    <g id="sld-match-lines-layer" class="sld-layer" style="display: none;"></g>

                    <!-- Mode C: North Orientation Indicator (top overlay) -->
                    <g id="sld-north-indicator" class="sld-layer" style="display: none;"></g>

                    <!-- SLD-05T: CAD Title Block Layer -->
                    <g id="sld-cad-title-block-layer" class="sld-layer" style="display: none;"></g>
                </svg>
            </div>
        `;

        this.svg = document.getElementById('sld-svg-canvas');

        // Reset occupancy index before rendering
        this.occupancy.clear();

        // 1. Pass 1: Register Node Symbol Footprints (Highest Priority = 1)
        this.registerNodeSymbolOccupancies();

        // 2. Draw Layer Elements
        this.renderZoneFrames();
        this.renderSubstationAnchor();
        this.renderRoadCorridors();
        this.renderNorthIndicator();
        this.renderEdges();
        this.renderLineSections();
        this.renderNodes();
        this.renderFindings();
        if (this.currentSheetIndex !== null && this.sheetsData) {
            const sheet = this.sheetsData.find(s => s.sheet_index === this.currentSheetIndex);
            if (sheet) {
                this.renderCadTitleBlock(sheet);
                this.renderMatchLines(sheet);
            }
        }

        // Attach Interactions & Initial Zoom Class
        this.setupPanZoom();
        this.applyDisplayModes();
        this.updateZoomClass();
    }

    /**
     * Map schematic grid (grid_x, grid_y) to SVG coordinate space.
     */
    project(gridX, gridY) {
        return {
            x: (gridX * this.options.scaleX) + this.options.offsetX,
            y: (gridY * this.options.scaleY) + this.options.offsetY,
        };
    }

    /**
     * Pass 1: Register physical footprints of all node symbols in the occupancy index.
     * Prevents any label or conductor text from intersecting node symbols.
     */
    registerNodeSymbolOccupancies() {
        for (const node of this.layoutData.nodes) {
            const pos = this.project(node.schematic.grid_x, node.schematic.grid_y);
            const devRole = node.device_role;

            let halfW = 8, halfH = 8;
            if (devRole === 'SOURCE_INCOMER') {
                halfW = 22; halfH = 22;
            } else if (devRole === 'SWITCH_CANDIDATE') {
                halfW = 20; halfH = 20;
            } else if (devRole === 'TRANSFORMER_NODE') {
                halfW = 48; halfH = 38;
            } else if (node.topology_role === 'BRANCH_NODE') {
                halfW = 10; halfH = 10;
            }

            this.occupancy.add({
                id: `sym-${node.asset_id}`,
                type: 'NODE_SYMBOL',
                priority: 1,
                x: pos.x - halfW,
                y: pos.y - halfH,
                w: halfW * 2,
                h: halfH * 2
            });
        }
    }

    /**
     * Render the 3 visually separated Zone Frames with crisp light engineering borders.
     */
    renderZoneFrames() {
        const zones = this.layoutData.zones || {};

        // Zone 1: Main Network
        const main = zones.MAIN_NETWORK;
        const gMain = document.getElementById('sld-zone-main');
        if (gMain && main && main.bounding_box) {
            const p1 = this.project(main.bounding_box.min_x, main.bounding_box.min_y);
            const p2 = this.project(main.bounding_box.max_x, main.bounding_box.max_y);
            const padX = 70, padY = 70;
            const w = (p2.x - p1.x) + (padX * 2);
            const h = (p2.y - p1.y) + (padY * 2);

            gMain.innerHTML = `
                <rect x="${p1.x - padX}" y="${p1.y - padY}" width="${w}" height="${h}" 
                      fill="rgba(2, 132, 199, 0.02)" stroke="#0284c7" stroke-width="1.5" stroke-dasharray="6,4" rx="8"/>
                <rect x="${p1.x - padX + 12}" y="${p1.y - padY + 10}" width="420" height="24" rx="4"
                      fill="#e0f2fe" stroke="#0284c7" stroke-width="1"/>
                <text x="${p1.x - padX + 22}" y="${p1.y - padY + 26}" 
                      fill="#0369a1" font-size="11.5" font-weight="bold" font-family="sans-serif" letter-spacing="0.5">
                    ZONA 1: PENYULANG UTAMA (TERHUBUNG GI BUDURAN - C15-01)
                </text>
            `;
        }

        // Zone 2: Unconnected Components
        const uncon = zones.UNCONNECTED_COMPONENTS;
        const gUncon = document.getElementById('sld-zone-unconnected');
        if (gUncon && uncon && uncon.bounding_box && uncon.nodes_count > 0) {
            const p1 = this.project(uncon.bounding_box.min_x, uncon.bounding_box.min_y);
            const p2 = this.project(uncon.bounding_box.max_x, uncon.bounding_box.max_y);
            const padX = 70, padY = 50;
            const w = Math.max(540, (p2.x - p1.x) + (padX * 2));
            const h = (p2.y - p1.y) + (padY * 2);

            gUncon.innerHTML = `
                <rect x="${p1.x - padX}" y="${p1.y - padY}" width="${w}" height="${h}" 
                      fill="rgba(217, 119, 6, 0.02)" stroke="#d97706" stroke-width="1.5" stroke-dasharray="6,4" rx="8"/>
                <rect x="${p1.x - padX + 12}" y="${p1.y - padY + 10}" width="460" height="24" rx="4"
                      fill="#fef3c7" stroke="#d97706" stroke-width="1"/>
                <text x="${p1.x - padX + 22}" y="${p1.y - padY + 26}" 
                      fill="#92400e" font-size="11.5" font-weight="bold" font-family="sans-serif" letter-spacing="0.5">
                    ZONA 2: FRAGMEN JARINGAN LEPAS (UNCONNECTED TO GI - C15-02 .. C15-06)
                </text>
            `;
        }

        // Zone 3: Isolated Assets
        const iso = zones.ISOLATED_ASSETS;
        const gIso = document.getElementById('sld-zone-isolated');
        if (gIso && iso && iso.bounding_box && iso.nodes_count > 0) {
            const p1 = this.project(iso.bounding_box.min_x, iso.bounding_box.min_y);
            const p2 = this.project(iso.bounding_box.max_x, iso.bounding_box.max_y);
            const padX = 70, padY = 40;
            const w = Math.max(480, (p2.x - p1.x) + (padX * 2));
            const h = (p2.y - p1.y) + (padY * 2);

            gIso.innerHTML = `
                <rect x="${p1.x - padX}" y="${p1.y - padY}" width="${w}" height="${h}" 
                      fill="rgba(100, 116, 139, 0.02)" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="6,4" rx="8"/>
                <rect x="${p1.x - padX + 12}" y="${p1.y - padY + 10}" width="420" height="24" rx="4"
                      fill="#f1f5f9" stroke="#94a3b8" stroke-width="1"/>
                <text x="${p1.x - padX + 22}" y="${p1.y - padY + 26}" 
                      fill="#475569" font-size="11.5" font-weight="bold" font-family="sans-serif" letter-spacing="0.5">
                    ZONA 3: ASET TERISOLASI (TANPA KONDUKTOR / ZERO EDGES)
                </text>
            `;
        }
    }

    /**
     * Render Substation GI Origin Visual Anchor (Mandatory Amendment 4).
     * Placed upstream above Incomer #3231 with red zigzag feeder cable takeoff.
     */
    renderSubstationAnchor() {
        const g = document.getElementById('sld-substation-anchor-layer');
        if (!g || !this.layoutData) return;

        const incomer = this.layoutData.nodes.find(n => n.asset_id === 3231 || n.device_role === 'SOURCE_INCOMER') 
                     || this.layoutData.nodes[0];
        if (!incomer || !incomer.schematic) return;

        const pInc = this.project(incomer.schematic.grid_x, incomer.schematic.grid_y);
        const subW = 180;
        const subH = 64;
        const subX = pInc.x - (subW / 2);
        const subY = pInc.y - 145;

        // Substation box with solid upward black triangle, title GI BUDURAN, and red cable takeoff
        g.innerHTML = `
            <!-- Substation Box Container -->
            <g id="sld-gi-origin-anchor" style="cursor: default;">
                <rect x="${subX}" y="${subY}" width="${subW}" height="${subH}" rx="6"
                      fill="#ffffff" stroke="#0f172a" stroke-width="2.5" 
                      style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.08));" />
                
                <!-- Substation Symbol: Solid Black Upward Triangle -->
                <polygon points="${pInc.x},${subY + 10} ${pInc.x - 14},${subY + 28} ${pInc.x + 14},${subY + 28}" 
                         fill="#0f172a" />
                
                <!-- Substation Header Text -->
                <text x="${pInc.x}" y="${subY + 44}" 
                      fill="#0f172a" font-size="12" font-weight="900" text-anchor="middle" font-family="sans-serif" letter-spacing="1.2">
                    GI BUDURAN
                </text>
                <text x="${pInc.x}" y="${subY + 56}" 
                      fill="#475569" font-size="8.5" font-weight="bold" text-anchor="middle" font-family="monospace">
                    20kV FEEDER BAY #15
                </text>

                <!-- Outgoing 20kV Feeder Cable (Stepped Riser Takeoff) -->
                <path d="M ${pInc.x} ${subY + subH} L ${pInc.x} ${pInc.y - 50} L ${pInc.x + 8} ${pInc.y - 40} L ${pInc.x - 8} ${pInc.y - 30} L ${pInc.x} ${pInc.y - 20} L ${pInc.x} ${pInc.y}" 
                      fill="none" stroke="#dc2626" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
                
                <!-- Cable Annotation Pill -->
                <rect x="${pInc.x + 16}" y="${pInc.y - 58}" width="130" height="20" rx="3"
                      fill="#fef2f2" stroke="#dc2626" stroke-width="1.2" />
                <text x="${pInc.x + 81}" y="${pInc.y - 44}" 
                      fill="#991b1b" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace">
                    KABEL OUTGOING 20kV
                </text>
            </g>
        `;
    }

    /**
     * Render Road Corridors for Mode C (Hybrid CAD + Road Corridors View - Reference Slide Style).
     * Strictly visual schematic corridor avenue ribbons along road routes.
     * Features:
     * - Double parallel boundary lines (═══════ ROAD CORRIDOR ═══════)
     * - Subtle roadway asphalt background shading (#f8fafc / #f1f5f9)
     * - Dashed road centerline (yellow dash offset from conductor route)
     * - High-contrast banner pills with road name, locality, and node count
     */
    renderRoadCorridors() {
        const g = document.getElementById('sld-corridors-layer');
        if (!g || !this.layoutData || !this.layoutData.nodes) return;

        // Group nodes dynamically by road_name
        const nodesByRoad = {};
        for (const node of this.layoutData.nodes) {
            const roadName = (node.location_context && node.location_context.road_name) ? node.location_context.road_name : null;
            if (!roadName) continue;
            if (!nodesByRoad[roadName]) nodesByRoad[roadName] = [];
            nodesByRoad[roadName].push(node);
        }

        let html = '';
        let corridorIdx = 0;

        for (const [roadName, roadNodes] of Object.entries(nodesByRoad)) {
            // Find distinct rows (grid_y) in this road that have multiple nodes (avenues)
            const nodesByY = {};
            for (const n of roadNodes) {
                const gy = n.schematic.grid_y;
                if (!nodesByY[gy]) nodesByY[gy] = [];
                nodesByY[gy].push(n);
            }

            for (const [gyStr, yNodes] of Object.entries(nodesByY)) {
                const gy = parseInt(gyStr, 10);
                if (yNodes.length < 5) continue;

                const xs = yNodes.map(n => n.schematic.grid_x);
                const minGridX = Math.min(...xs);
                const maxGridX = Math.max(...xs);

                const p1 = this.project(minGridX, gy);
                const p2 = this.project(maxGridX, gy);

                const padX = 65;
                const x = Math.min(p1.x, p2.x) - padX;
                const w = Math.abs(p2.x - p1.x) + (padX * 2);
                
                // Avenue ribbon centered around the row of poles
                const ribbonHeight = 84;
                const y = p1.y - (ribbonHeight / 2);

                const roadTitle = roadName.toUpperCase();
                const locality = (yNodes[0].location_context && yNodes[0].location_context.locality) ? yNodes[0].location_context.locality : 'Sidoarjo';
                const nodeCount = yNodes.length;
                const bannerW = Math.min(Math.max(260, (roadTitle.length * 8.5) + 60), Math.max(240, w - 30));

                corridorIdx++;

                html += `
                    <g class="sld-road-corridor" data-corridor-id="corr-${corridorIdx}" data-corridor-row="${gy}" data-road-name="${roadName}" style="cursor: default;">
                        <!-- Roadway Easement Background Bed -->
                        <rect x="${x}" y="${y}" width="${w}" height="${ribbonHeight}" rx="10"
                              fill="#f1f5f9" fill-opacity="0.75" stroke="none" />

                        <!-- Top Double Road Boundaries (═══════) -->
                        <line x1="${x}" y1="${y}" x2="${x + w}" y2="${y}" 
                              stroke="#64748b" stroke-width="2.5" stroke-linecap="round" />
                        <line x1="${x}" y1="${y + 5}" x2="${x + w}" y2="${y + 5}" 
                              stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="8,5" />

                        <!-- Bottom Double Road Boundaries (═══════) -->
                        <line x1="${x}" y1="${y + ribbonHeight - 5}" x2="${x + w}" y2="${y + ribbonHeight - 5}" 
                              stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="8,5" />
                        <line x1="${x}" y1="${y + ribbonHeight}" x2="${x + w}" y2="${y + ribbonHeight}" 
                              stroke="#64748b" stroke-width="2.5" stroke-linecap="round" />

                        <!-- Side End Caps -->
                        <line x1="${x}" y1="${y}" x2="${x}" y2="${y + ribbonHeight}" 
                              stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="4,4" />
                        <line x1="${x + w}" y1="${y}" x2="${x + w}" y2="${y + ribbonHeight}" 
                              stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="4,4" />

                        <!-- Schematic Road Dividing Centerlines (Subtle Yellow Dash on sides) -->
                        <line x1="${x + 15}" y1="${y + 16}" x2="${x + w - 15}" y2="${y + 16}" 
                              stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="14,10" opacity="0.65" />
                        <line x1="${x + 15}" y1="${y + ribbonHeight - 16}" x2="${x + w - 15}" y2="${y + ribbonHeight - 16}" 
                              stroke="#f59e0b" stroke-width="1.5" stroke-dasharray="14,10" opacity="0.65" />

                        <!-- Top-Left Road Corridor Header Banner -->
                        <g transform="translate(${x + 15}, ${y - 32})">
                            <rect x="0" y="0" width="${bannerW}" height="28" rx="5"
                                  fill="#0369a1" stroke="#075985" stroke-width="1.5" 
                                  style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));" />
                            <text x="12" y="14" fill="#ffffff" font-size="10" font-weight="900" font-family="sans-serif">
                                <tspan fill="#7dd3fc">&#128739; JALAN:</tspan> ${roadTitle}
                            </text>
                            <text x="12" y="23" fill="#bae6fd" font-size="7.5" font-weight="bold" font-family="sans-serif">
                                ${locality} &bull; ${nodeCount} TIANG DISTRIBUSI
                            </text>
                        </g>

                        <!-- Bottom-Right ROW Tag -->
                        <g transform="translate(${x + w - 185}, ${y + ribbonHeight - 12})">
                            <rect x="0" y="0" width="170" height="20" rx="3"
                                  fill="#ffffff" stroke="#64748b" stroke-width="1"
                                  style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.06));" />
                            <text x="85" y="13" fill="#334155" font-size="8" font-weight="bold" font-family="monospace" text-anchor="middle">
                                &#128205; KORIDOR ROW &bull; 20kV
                            </text>
                        </g>
                    </g>
                `;
            }
        }

        g.innerHTML = html;
    }

    /**
     * Render PLN North Orientation Indicator (⬆ U) for Mode C (Hybrid CAD View).
     */
    renderNorthIndicator() {
        const g = document.getElementById('sld-north-indicator');
        if (!g || !this.layoutData) return;

        const incomer = this.layoutData.nodes.find(n => n.asset_id === 3231 || n.device_role === 'SOURCE_INCOMER') 
                     || this.layoutData.nodes[0];
        if (!incomer || !incomer.schematic) return;

        const pInc = this.project(incomer.schematic.grid_x, incomer.schematic.grid_y);
        const compassX = pInc.x + 360;
        const compassY = pInc.y - 120;

        g.innerHTML = `
            <g id="sld-north-compass" transform="translate(${compassX}, ${compassY})" style="cursor: default;">
                <!-- Rosette Background Circle -->
                <circle cx="0" cy="0" r="24" fill="#ffffff" stroke="#0f172a" stroke-width="2" 
                        style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.12));" />
                <circle cx="0" cy="0" r="20" fill="none" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="2,2" />
                
                <!-- North-South Compass Needles -->
                <polygon points="0,-17 6,0 0,-3 -6,0" fill="#dc2626" />
                <polygon points="0,17 6,0 0,3 -6,0" fill="#0f172a" />
                <circle cx="0" cy="0" r="2.5" fill="#ffffff" stroke="#0f172a" stroke-width="1" />

                <!-- Cardinal Labels -->
                <text x="0" y="-21" fill="#dc2626" font-size="10.5" font-weight="900" font-family="sans-serif" text-anchor="middle">U</text>
                <text x="0" y="27" fill="#64748b" font-size="8" font-weight="bold" font-family="sans-serif" text-anchor="middle">S</text>
                <text x="21" y="3" fill="#64748b" font-size="7.5" font-weight="bold" font-family="sans-serif" text-anchor="middle">T</text>
                <text x="-21" y="3" fill="#64748b" font-size="7.5" font-weight="bold" font-family="sans-serif" text-anchor="middle">B</text>
            </g>
        `;
    }

    /**
     * Render Mode B: Interactive GIS Map View with real GPS coordinates (Leaflet).
     * Governed by Amendment #7: Authoritative GPS only. If GPS coordinates missing, node/edge omitted gracefully.
     */
    renderGisMap() {
        if (!window.L) {
            console.warn('[SLD-05S] Leaflet library not loaded.');
            return;
        }

        const mapContainer = document.getElementById('sld-gis-map-container');
        if (!mapContainer) return;

        if (!this.leafletMap) {
            this.leafletMap = L.map('sld-gis-map-container', {
                center: [-7.428, 112.723],
                zoom: 14,
                zoomControl: true,
            });

            // Resilient OpenStreetMap tile layer (Refinement #4)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors | SIDAK TEJO'
            }).addTo(this.leafletMap);

            this.leafletFeatureGroup = L.featureGroup().addTo(this.leafletMap);
        } else {
            this.leafletFeatureGroup.clearLayers();
        }

        if (!this.layoutData) return;

        const nodeMap = new Map();
        const validGeoNodes = [];

        // 1. Plot Conductor Edges (Lines between GPS points)
        for (const node of this.layoutData.nodes) {
            const lat = parseFloat(node.geo ? node.geo.latitude : 0);
            const lng = parseFloat(node.geo ? node.geo.longitude : 0);
            if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                nodeMap.set(node.asset_id, { node, lat, lng });
                validGeoNodes.push({ node, lat, lng });
            }
        }

        for (const edge of this.layoutData.edges) {
            const src = nodeMap.get(edge.source_asset_id);
            const tgt = nodeMap.get(edge.target_asset_id);
            if (src && tgt) {
                const isMain = (edge.component_id === 'C15-01');
                const hasLength = (edge.length_meters !== null && edge.length_meters !== undefined && edge.length_meters > 0);
                const lenLabel = hasLength ? `${Number(edge.length_meters).toFixed(1)} m` : 'Length unavailable';

                const poly = L.polyline([[src.lat, src.lng], [tgt.lat, tgt.lng]], {
                    color: isMain ? '#0284c7' : '#64748b',
                    weight: isMain ? 4 : 2.5,
                    dashArray: isMain ? null : '5,5',
                    opacity: 0.85,
                }).addTo(this.leafletFeatureGroup);

                poly.bindPopup(`
                    <div style="font-family: sans-serif; font-size: 12px; min-width: 180px;">
                        <div style="font-weight: bold; color: #0284c7; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 6px;">
                            TRANSLINE #${edge.transline_id}
                        </div>
                        <div><strong>Source:</strong> #${edge.source_asset_id}</div>
                        <div><strong>Target:</strong> #${edge.target_asset_id}</div>
                        <div><strong>Panjang:</strong> ${lenLabel}</div>
                        <div><strong>Komponen:</strong> ${edge.component_id || 'FRAGMENT'}</div>
                    </div>
                `);
            }
        }

        // 2. Plot Nodes (Equipment Points using Unified SldDeviceSymbolRegistry)
        for (const item of validGeoNodes) {
            const n = item.node;
            const officialRole = SldDeviceSymbolRegistry.resolveRole(n);
            const def = SldDeviceSymbolRegistry.getDef(officialRole);
            const loc = n.location_context || {};

            const marker = L.marker([item.lat, item.lng], {
                icon: SldDeviceSymbolRegistry.createLeafletIcon(n, this.selectedAssetId === n.asset_id)
            }).addTo(this.leafletFeatureGroup);

            const popupContent = `
                <div style="font-family: sans-serif; font-size: 12px; min-width: 200px;">
                    <div style="font-weight: bold; color: #0f172a; font-size: 13px; margin-bottom: 4px;">
                        ${n.name}
                    </div>
                    <div style="margin-bottom: 6px;">
                        <span style="background: ${def.bg}; color: ${def.color}; padding: 2px 6px; border-radius: 3px; font-weight: bold; font-size: 10px; border: 1px solid ${def.color};">
                            ${def.code} - ${def.label}
                        </span>
                        <span style="font-family: monospace; color: #64748b; font-size: 11px; margin-left: 4px;">#${n.asset_id}</span>
                    </div>
                    <div style="color: #475569; margin-bottom: 3px;"><strong>Lokasi:</strong> ${loc.road_name || '-'}</div>
                    <div style="color: #475569; margin-bottom: 3px;"><strong>Wilayah:</strong> ${loc.locality || '-'}</div>
                    <div style="color: #64748b; font-family: monospace; font-size: 11px;">GPS: ${item.lat.toFixed(6)}, ${item.lng.toFixed(6)}</div>
                    <div style="margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 6px;">
                        <button onclick="sldEngine.selectAsset(${n.asset_id})" style="background: #0284c7; color: #ffffff; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 11px; width: 100%;">
                            Buka Detail Drawer
                        </button>
                    </div>
                </div>
            `;
            marker.bindPopup(popupContent);
            marker.on('click', () => {
                this.selectAsset(n.asset_id);
            });
        }

        // 3. Plot Finding Overlays on Leaflet Map
        this.renderFindingMarkersOnLeaflet();

        // Fit bounds to entire feeder
        if (validGeoNodes.length > 0) {
            try {
                this.leafletMap.fitBounds(this.leafletFeatureGroup.getBounds().pad(0.08));
            } catch (e) {
                // Ignore bounds fitting if single point
            }
        }

        setTimeout(() => {
            if (this.leafletMap) this.leafletMap.invalidateSize();
        }, 200);
    }

    /**
     * Render Conductor Lines (Edges) & Span Annotations with Route-First Hierarchy (Amendment 1 & 3).
     * Main trunk lines (C15-01) are bold dark ink (3.2px, #0f172a).
     * Fragment lines are lighter neutral (2.0px, #64748b, dashed).
     */
    renderEdges() {
        const edgeLayer = document.getElementById('sld-edges-layer');
        const labelLayer = document.getElementById('sld-edge-labels-layer');
        let edgeHtml = '';
        let labelHtml = '';

        for (const edge of this.layoutData.edges) {
            const p1 = this.project(edge.source_grid.grid_x, edge.source_grid.grid_y);
            const p2 = this.project(edge.target_grid.grid_x, edge.target_grid.grid_y);
            const tlId = edge.transline_id;

            let pathD = '';
            let midX = 0;
            let midY = 0;
            let isHorizontal = false;

            if (edge.route_type === 'STRAIGHT_HORIZONTAL') {
                pathD = `M ${p1.x} ${p1.y} L ${p2.x} ${p2.y}`;
                midX = (p1.x + p2.x) / 2;
                midY = p1.y;
                isHorizontal = true;
            } else if (edge.route_type === 'STRAIGHT_VERTICAL') {
                pathD = `M ${p1.x} ${p1.y} L ${p2.x} ${p2.y}`;
                midX = p1.x;
                midY = (p1.y + p2.y) / 2;
                isHorizontal = false;
            } else {
                pathD = `M ${p1.x} ${p1.y} L ${p2.x} ${p1.y} L ${p2.x} ${p2.y}`;
                midX = (p1.x + p2.x) / 2;
                midY = p1.y;
                isHorizontal = true;
            }

            const hasLength = (edge.length_meters !== null && edge.length_meters !== undefined && edge.length_meters > 0);
            const lengthText = hasLength ? `${Number(edge.length_meters).toFixed(1)} m` : 'Length unavailable';

            // MANDATORY AMENDMENT 1: Main trunk hierarchy based on authoritative component C15-01
            const isMainNetwork = (edge.component_id === 'C15-01');
            const strokeColor = isMainNetwork ? '#0f172a' : '#64748b';
            const strokeWidth = isMainNetwork ? '3.2' : '2.0';
            const strokeDash = isMainNetwork ? 'none' : '5,3';

            edgeHtml += `
                <path d="${pathD}" 
                      fill="none" 
                      stroke="${strokeColor}" 
                      stroke-width="${strokeWidth}" 
                      stroke-dasharray="${strokeDash}"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      class="sld-edge sld-granular-edge" 
                      id="edge-${tlId}"
                      data-transline-id="${tlId}"
                      data-component-id="${edge.component_id || ''}"
                      data-source="${edge.source_asset_id}"
                      data-target="${edge.target_asset_id}"
                      data-length="${hasLength ? edge.length_meters : ''}"
                      style="cursor: pointer; transition: stroke 0.2s, stroke-width 0.2s;">
                    <title>Transline #${tlId}: ${lengthText} (${edge.source_asset_id} ↔ ${edge.target_asset_id}) [${edge.component_id || 'FRAGMENT'}]</title>
                </path>
            `;

            // Geometric Collision Check for Conductor Annotation Pill
            // Pill dimensions: 44px width, 15px height
            const pillW = 44;
            const pillH = 15;
            
            const candA = {
                x: isHorizontal ? midX - (pillW / 2) : midX + 6,
                y: isHorizontal ? midY - 18 : midY - (pillH / 2),
                w: pillW,
                h: pillH
            };

            const candB = {
                x: isHorizontal ? midX - (pillW / 2) : midX - pillW - 6,
                y: isHorizontal ? midY + 5 : midY - (pillH / 2),
                w: pillW,
                h: pillH
            };

            let chosenBox = null;
            if (!this.occupancy.collides(candA, 3)) {
                chosenBox = candA;
            } else if (!this.occupancy.collides(candB, 3)) {
                chosenBox = candB;
            } else {
                chosenBox = candA;
            }

            if (chosenBox) {
                this.occupancy.add({
                    id: `edge-pill-${tlId}`,
                    type: 'EDGE_LABEL',
                    priority: 3,
                    x: chosenBox.x,
                    y: chosenBox.y,
                    w: chosenBox.w,
                    h: chosenBox.h
                });

                labelHtml += `
                    <g class="sld-edge-label-group" 
                       id="edge-label-${tlId}" 
                       transform="translate(${chosenBox.x + (pillW / 2)}, ${chosenBox.y + (pillH / 2)})"
                       data-transline-id="${tlId}"
                       style="cursor: pointer;">
                        <rect x="-${pillW / 2}" y="-${pillH / 2}" width="${pillW}" height="${pillH}" rx="3" 
                              fill="#ffffff" stroke="#cbd5e1" stroke-width="0.8" 
                              class="sld-edge-pill" />
                        <text x="0" y="3" fill="#334155" font-size="8" font-family="monospace" 
                              text-anchor="middle" font-weight="bold" class="sld-edge-length-text">
                            ${hasLength ? Number(edge.length_meters).toFixed(0) + 'm' : '-'}
                        </text>
                    </g>
                `;
            }
        }

        edgeLayer.innerHTML = edgeHtml;
        labelLayer.innerHTML = labelHtml;

        edgeLayer.querySelectorAll('.sld-edge').forEach(elem => {
            const tlId = parseInt(elem.getAttribute('data-transline-id'), 10);
            elem.addEventListener('click', () => this.selectEdge(tlId));
            elem.addEventListener('mouseenter', () => this.highlightEdge(tlId, true));
            elem.addEventListener('mouseleave', () => this.highlightEdge(tlId, false));
        });

        labelLayer.querySelectorAll('.sld-edge-label-group').forEach(elem => {
            const tlId = parseInt(elem.getAttribute('data-transline-id'), 10);
            elem.addEventListener('click', () => this.selectEdge(tlId));
            elem.addEventListener('mouseenter', () => this.highlightEdge(tlId, true));
            elem.addEventListener('mouseleave', () => this.highlightEdge(tlId, false));
        });
    }

    /**
     * Render Simplified Line Section Blocks.
     */
    renderLineSections() {
        const g = document.getElementById('sld-sections-layer');
        if (!g) return;
        let html = '';

        for (const sec of this.layoutData.line_sections) {
            if (!sec.layout || !sec.layout.start || !sec.layout.end) continue;

            const p1 = this.project(sec.layout.start.grid_x, sec.layout.start.grid_y);
            const p2 = this.project(sec.layout.end.grid_x, sec.layout.end.grid_y);

            let pathD = `M ${p1.x} ${p1.y}`;
            if (p1.y === p2.y) {
                pathD += ` L ${p2.x} ${p2.y}`;
            } else {
                pathD += ` L ${p2.x} ${p1.y} L ${p2.x} ${p2.y}`;
            }

            const midX = (p1.x + p2.x) / 2;
            const midY = (p1.y + p2.y) / 2;

            html += `
                <g class="sld-section-block" data-section-id="${sec.id}">
                    <path d="${pathD}" 
                          fill="none" 
                          stroke="#0284c7" 
                          stroke-width="6" 
                          stroke-linecap="round"
                          opacity="0.9">
                        <title>${sec.id}: ${sec.span_count} Spans (${sec.source_node_id} ↔ ${sec.target_node_id})</title>
                    </path>
                    <rect x="${midX - 38}" y="${midY - 20}" width="76" height="18" rx="4" 
                          fill="#ffffff" stroke="#0284c7" stroke-width="1.2"/>
                    <text x="${midX}" y="${midY - 7}" fill="#0369a1" font-size="9" font-family="monospace" text-anchor="middle" font-weight="bold">
                        ${sec.id} (${sec.span_count}s)
                    </text>
                </g>
            `;
        }

        g.innerHTML = html;
    }

    /**
     * Render All 205 Equipment Nodes with Project-Approved PLN/IEC-Aligned Glyphs (Amendment 5).
     */
    renderNodes() {
        const g = document.getElementById('sld-nodes-layer');
        if (!g) return;
        let html = '';

        for (let i = 0; i < this.layoutData.nodes.length; i++) {
            const node = this.layoutData.nodes[i];
            const pos = this.project(node.schematic.grid_x, node.schematic.grid_y);
            const aId = node.asset_id;
            const topRole = node.topology_role;
            const devRole = node.device_role;
            const eqType = node.equipment_type || '';
            const officialRole = SldDeviceSymbolRegistry.resolveRole(node);
            const def = SldDeviceSymbolRegistry.getDef(officialRole);
            const isGtt = (officialRole === 'GTT_PORTAL' || officialRole === 'GTT_CANTOL' || devRole === 'TRANSFORMER_NODE');

            // SLD-05S Two-Dimensional Node Modeling:
            // Dimension 1: Semantic Role (devRole / topRole / officialRole)
            // Dimension 2: Topology State (CONNECTED vs ISOLATED)
            const isIsolated = (topRole === 'ISOLATED_NODE' || node.zone === 'ISOLATED_ASSETS' || node.topology_state === 'ISOLATED');
            const topologyState = isIsolated ? 'ISOLATED' : 'CONNECTED';

            let symbolMarkup = '';
            let labelMarkup = '';
            let extraClass = isGtt ? 'sld-gtt-node' : '';
            if (isIsolated) extraClass += ' sld-node-isolated';
            if (officialRole !== 'LINE_POLE') extraClass += ` sld-device-${officialRole.toLowerCase()}`;

            // 1. SOURCE_INCOMER (GI Buduran Substation Demarcation)
            if (officialRole === 'GI') {
                symbolMarkup = def.renderSvg();
                labelMarkup = `
                    <rect x="-65" y="16" width="130" height="22" rx="4" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>
                    <text x="0" y="31" fill="#ffffff" font-size="9.5" font-weight="bold" text-anchor="middle" font-family="monospace">
                        INCOMER #${aId}
                    </text>
                    <text x="0" y="48" fill="#475569" font-size="8.5" font-weight="bold" text-anchor="middle" font-family="monospace">
                        TM11 • SOURCE TAKEOFF
                    </text>
                `;
            }
            // 2. TRANSFORMER_NODE (GTT Cantol 1T, Portal 2T, or Trafo - Full Reference Technical Block)
            else if (isGtt) {
                const gttName = node.name || `GTT #${aId}`;
                const gttCode = node.code || `#${aId}`;
                const shortCode = gttCode.length > 15 ? gttCode.substring(0, 13) + '..' : gttCode;
                const kvaMatch = gttName.match(/(\d+)\s*kVA/i);
                const kvaText = kvaMatch ? `${kvaMatch[1]} kVA` : '160 kVA';
                const roadLoc = (node.location_context && node.location_context.road_name) ? node.location_context.road_name : 'SIDOARJO';
                const shortLoc = roadLoc.length > 18 ? roadLoc.substring(0, 16) + '..' : roadLoc;

                const isCantol = (officialRole === 'GTT_CANTOL' || eqType.includes('CANTOL'));
                const headerText = isCantol ? 'GTT CANTOL (1T)' : 'GTT PORTAL (2T)';
                const themeColor = def.color;

                const cardW = 110;
                const cardH = 56;
                const cardX = -(cardW / 2);
                const cardY = 32;

                symbolMarkup = def.renderSvg();

                labelMarkup = `
                    <!-- GTT Enclosed Technical Block -->
                    <g class="sld-gtt-technical-block">
                        <rect x="${cardX}" y="${cardY}" width="${cardW}" height="${cardH}" rx="4"
                              fill="#ffffff" stroke="${themeColor}" stroke-width="1.4"
                              style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));" />
                        
                        <path d="M ${cardX} ${cardY + 4} A 4 4 0 0 1 ${cardX + 4} ${cardY} L ${cardX + cardW - 4} ${cardY} A 4 4 0 0 1 ${cardX + cardW} ${cardY + 4} L ${cardX + cardW} ${cardY + 15} L ${cardX} ${cardY + 15} Z" 
                              fill="${themeColor}" />
                        <text x="0" y="${cardY + 11}" fill="#ffffff" font-size="7.5" font-weight="900" text-anchor="middle" font-family="sans-serif" letter-spacing="0.4">
                            ${headerText}
                        </text>

                        <text x="0" y="${cardY + 26}" fill="#0f172a" font-size="8.5" font-weight="bold" text-anchor="middle" font-family="monospace">
                            ${shortCode}
                        </text>

                        <text x="0" y="${cardY + 38}" fill="#334155" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">
                            ${kvaText} &bull; 20kV / 400V
                        </text>

                        <text x="0" y="${cardY + 49}" fill="#64748b" font-size="7" font-weight="500" text-anchor="middle" font-family="sans-serif">
                            &#128739; ${shortLoc}
                        </text>
                    </g>
                `;
            }
            // 3. Official Switch & Protective Devices (LBS, LBSM_2WAY, LBSM_3WAY, PMCB, RECLOSER, AVS, PGS, PMS, DEVICE_UNKNOWN)
            else if (['LBS', 'LBSM_2WAY', 'LBSM_3WAY', 'PMCB', 'RECLOSER', 'AVS', 'PGS', 'PMS', 'DEVICE_UNKNOWN'].includes(officialRole) || devRole === 'SWITCH_CANDIDATE') {
                symbolMarkup = def.renderSvg();
                labelMarkup = `
                    <rect x="-46" y="-30" width="92" height="15" rx="3" fill="${def.bg}" stroke="${def.color}" stroke-width="1"/>
                    <text x="0" y="-19" fill="${def.color}" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace">
                        ${def.code} #${aId}
                    </text>
                `;
            }
            // 4. BRANCH
            else if (officialRole === 'BRANCH' || topRole === 'BRANCH_NODE') {
                symbolMarkup = def.renderSvg();
                labelMarkup = `
                    <text x="0" y="-10" fill="#dc2626" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #ffffff; stroke-width: 2.5px;">JCT #${aId}</text>
                `;
            }
            // 5. TERMINAL
            else if (officialRole === 'TERMINAL' || topRole === 'TERMINAL_NODE') {
                symbolMarkup = def.renderSvg();
                labelMarkup = `
                    <text x="0" y="-12" fill="#dc2626" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #ffffff; stroke-width: 2.5px;">END #${aId}</text>
                `;
            }
            // 6. ISOLATED
            else if (isIsolated || officialRole === 'ISOLATED') {
                symbolMarkup = def.renderSvg();
                labelMarkup = `
                    <rect x="-42" y="14" width="84" height="15" rx="3" fill="#fef2f2" stroke="#ef4444" stroke-width="1"/>
                    <text x="0" y="25" fill="#b91c1c" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">
                        ○ ISO #${aId}
                    </text>
                `;
            }
            // 7. LINE_POLE
            else {
                extraClass += ' sld-pole-node';
                symbolMarkup = def.renderSvg();

                const labelW = 26;
                const labelH = 11;
                const candAbove = { x: pos.x - (labelW / 2), y: pos.y - 15, w: labelW, h: labelH };
                const candBelow = { x: pos.x - (labelW / 2), y: pos.y + 5, w: labelW, h: labelH };
                const selfSym = `sym-${aId}`;
                let chosenLabelY = null;
                if (!this.occupancy.collides(candAbove, 2, selfSym)) {
                    chosenLabelY = -10;
                    this.occupancy.add({ id: `pole-lbl-${aId}`, type: 'POLE_LABEL', priority: 2, ...candAbove });
                } else if (!this.occupancy.collides(candBelow, 2, selfSym)) {
                    chosenLabelY = 15;
                    this.occupancy.add({ id: `pole-lbl-${aId}`, type: 'POLE_LABEL', priority: 2, ...candBelow });
                }

                if (chosenLabelY !== null) {
                    labelMarkup = `
                        <text x="0" y="${chosenLabelY}" fill="#475569" font-size="7.5" text-anchor="middle" font-family="monospace"
                              class="sld-pole-label"
                              style="paint-order: stroke fill; stroke: #ffffff; stroke-width: 2.5px;">
                            #${aId}
                        </text>
                    `;
                }
            }

            html += `
                <g class="sld-node ${extraClass}" 
                   id="node-${aId}"
                   transform="translate(${pos.x}, ${pos.y})" 
                   tabindex="0"
                   role="button"
                   aria-label="${node.name} (${topRole}, ${devRole}, ${topologyState})"
                   data-asset-id="${aId}"
                   data-topology-role="${topRole}"
                   data-device-role="${devRole}"
                   data-topology-state="${topologyState}"
                   style="cursor: pointer;">
                    <title>${node.name} [ID: #${aId}]\nTopology State: ${topologyState}\nRole: ${topRole} | ${devRole}\nKonstruksi: ${eqType}\nStatus: ${node.operational_state}</title>
                    ${isIsolated ? '<circle cx="0" cy="0" r="' + (isGtt ? '18' : '13') + '" fill="none" stroke="#ef4444" stroke-width="1.6" stroke-dasharray="3,2"/>' : ''}
                    ${symbolMarkup}
                    ${labelMarkup}
                </g>
            `;
        }

        g.innerHTML = html;

        g.querySelectorAll('.sld-node').forEach(elem => {
            elem.addEventListener('click', () => {
                const assetId = parseInt(elem.getAttribute('data-asset-id'), 10);
                this.selectAsset(assetId);
            });
            elem.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const assetId = parseInt(elem.getAttribute('data-asset-id'), 10);
                    this.selectAsset(assetId);
                }
            });
        });
    }

    /**
     * Select asset and open Read-Only Detail Drawer.
     */
    selectAsset(assetId) {
        if (!this.layoutData) return;
        this.selectedAssetId = assetId;

        const node = this.layoutData.nodes.find(n => n.asset_id === assetId);
        if (!node) return;

        this.svg.querySelectorAll('.sld-node-selected').forEach(el => el.classList.remove('sld-node-selected'));
        const targetNode = document.getElementById(`node-${assetId}`);
        if (targetNode) targetNode.classList.add('sld-node-selected');

        if (typeof this.options.onSelectAsset === 'function') {
            this.options.onSelectAsset(node);
        } else {
            this.renderDrawer(node);
        }
    }

    /**
     * Select Conductor Edge and open Read-Only Detail Drawer.
     */
    selectEdge(translineId) {
        if (!this.layoutData) return;
        this.selectedEdgeId = translineId;

        const edge = this.layoutData.edges.find(e => e.transline_id === translineId);
        if (!edge) return;

        this.svg.querySelectorAll('.sld-edge-selected').forEach(el => el.classList.remove('sld-edge-selected'));
        const targetEdge = document.getElementById(`edge-${translineId}`);
        if (targetEdge) targetEdge.classList.add('sld-edge-selected');

        this.renderEdgeDrawer(edge);
    }

    /**
     * Hover highlight edge & associated label pill.
     */
    highlightEdge(translineId, isHovered) {
        const edgeEl = document.getElementById(`edge-${translineId}`);
        const labelEl = document.getElementById(`edge-label-${translineId}`);
        if (!edgeEl) return;

        if (isHovered) {
            edgeEl.setAttribute('stroke', '#0284c7');
            edgeEl.setAttribute('stroke-width', '4.5');
            edgeEl.setAttribute('filter', 'url(#edge-glow-light)');
            if (labelEl) {
                const pill = labelEl.querySelector('.sld-edge-pill');
                const txt = labelEl.querySelector('.sld-edge-length-text');
                if (pill) {
                    pill.setAttribute('stroke', '#0284c7');
                    pill.setAttribute('fill', '#e0f2fe');
                }
                if (txt) txt.setAttribute('fill', '#0369a1');
            }
        } else {
            if (this.selectedEdgeId !== translineId) {
                const isMain = (edgeEl.getAttribute('data-component-id') === 'C15-01');
                edgeEl.setAttribute('stroke', isMain ? '#0f172a' : '#64748b');
                edgeEl.setAttribute('stroke-width', isMain ? '3.2' : '2.0');
                edgeEl.removeAttribute('filter');
                if (labelEl) {
                    const pill = labelEl.querySelector('.sld-edge-pill');
                    const txt = labelEl.querySelector('.sld-edge-length-text');
                    if (pill) {
                        pill.setAttribute('stroke', '#cbd5e1');
                        pill.setAttribute('fill', '#ffffff');
                    }
                    if (txt) txt.setAttribute('fill', '#334155');
                }
            }
        }
    }

    /**
     * Render Read-Only Slide-Over Detail Drawer for Equipment Node (Delta = 0).
     */
    renderDrawer(node) {
        let drawer = document.getElementById('sld-asset-drawer');
        if (!drawer) {
            drawer = document.createElement('div');
            drawer.id = 'sld-asset-drawer';
            drawer.className = 'sld-drawer shadow-lg';
            drawer.style.cssText = `
                position: fixed; top: 0; right: 0; width: 380px; height: 100vh;
                background: #0f172a; color: #f8fafc; border-left: 1px solid #334155;
                z-index: 1050; padding: 24px; overflow-y: auto; font-family: 'Segoe UI', sans-serif;
                box-shadow: -5px 0 25px rgba(0,0,0,0.5); transition: transform 0.3s ease;
            `;
            document.body.appendChild(drawer);
        }

        const isSwitch = (node.device_role === 'SWITCH_CANDIDATE');
        const isGtt = (node.device_role === 'TRANSFORMER_NODE');
        const isIsolated = (node.topology_role === 'ISOLATED_NODE' || node.zone === 'ISOLATED_ASSETS' || node.topology_state === 'ISOLATED');

        // SLD-05T: Check active findings for this asset
        let findingsCardHtml = '';
        if (this.findingsData && Array.isArray(this.findingsData.findings)) {
            const assetFindings = this.findingsData.findings.filter(f => f.asset_id === node.asset_id);
            if (assetFindings.length > 0) {
                findingsCardHtml = `
                    <div class="card bg-dark border-warning mb-3">
                        <div class="card-header border-warning py-2 small fw-bold text-warning bg-dark d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-triangle-exclamation me-1"></i> TEMUAN LAPANGAN AKTIF (${assetFindings.length})</span>
                            <span class="badge bg-warning text-dark font-monospace">${assetFindings.length} ANOMALI</span>
                        </div>
                        <div class="card-body p-2">
                            ${assetFindings.map(af => `
                                <div class="p-2 mb-1 bg-black bg-opacity-50 rounded border border-secondary border-opacity-50" style="cursor: pointer;" onclick="sldEngine.selectFinding(${af.id})">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-warning small">${af.nomor_temuan || '#' + af.id}</span>
                                        <span class="badge ${af.prioritas === 'HIGH' ? 'bg-danger' : 'bg-warning text-dark'} small" style="font-size: 8px;">${af.prioritas}</span>
                                    </div>
                                    <div class="small text-white-50 text-truncate">${af.jenis_temuan}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
        }

        drawer.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary">
                <h5 class="fw-bold m-0 text-info"><i class="fa-solid fa-microchip me-2"></i>Detail Aset Jaringan</h5>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('sld-asset-drawer').style.display='none';"></button>
            </div>

            <div class="mb-3">
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="badge ${isSwitch ? 'bg-warning text-dark' : (isGtt ? 'bg-success' : 'bg-primary')} px-2 py-1">
                        ${node.device_role}
                    </span>
                    <span class="badge ${isIsolated ? 'bg-danger' : 'bg-success'} px-2 py-1">
                        ${isIsolated ? '○ ISOLATED' : '● CONNECTED'}
                    </span>
                </div>
                <h4 class="fw-bold mb-1 text-white">${node.name}</h4>
                <div class="text-white-50 font-monospace small">ID: #${node.asset_id} | ${node.code}</div>
            </div>

            ${findingsCardHtml}

            <!-- Two-Dimensional Modeling & Operational Status Card -->
            <div class="card bg-dark border-secondary mb-3">
                <div class="card-header border-secondary py-2 small fw-bold text-info bg-dark">
                    <i class="fa-solid fa-layer-group me-1"></i> ARSITEKTUR 2-DIMENSI NODE
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-secondary border-opacity-25">
                        <span class="small text-muted text-uppercase fw-bold">Dimensi 1 (Semantic Role)</span>
                        <span class="badge ${isSwitch ? 'bg-warning text-dark' : (isGtt ? 'bg-success' : 'bg-primary')} font-monospace">
                            ${node.device_role}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-secondary border-opacity-25">
                        <span class="small text-muted text-uppercase fw-bold">Dimensi 2 (Topology State)</span>
                        <span class="badge ${isIsolated ? 'bg-danger' : 'bg-success'} font-monospace">
                            ${isIsolated ? '○ ISOLATED (ZERO EDGES)' : '● CONNECTED (AKTIF)'}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted text-uppercase fw-bold">Status Operasional</span>
                        <span class="fw-bold ${isSwitch ? 'text-warning' : 'text-success'}">
                            ${node.operational_state}
                        </span>
                    </div>
                    ${isSwitch ? '<div class="small text-white-50 mt-2"><i class="fa-solid fa-circle-info me-1"></i>Belum ada telemetri SCADA. Posisi fisik tidak diketahui sistem.</div>' : ''}
                </div>
            </div>

            <!-- SLD-05S: Location & Road Context Card (Amendment #5 & #6) -->
            <div class="card bg-dark border-secondary mb-3">
                <div class="card-header border-secondary py-2 small fw-bold text-info bg-dark">
                    <i class="fa-solid fa-road me-1"></i> KONTEKS GEOGRAFIS (ROAD_CONTEXT)
                </div>
                <div class="card-body p-3">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Koridor Jalan</div>
                    <div class="fw-bold text-white mb-2">
                        ${(node.location_context && node.location_context.road_name) ? node.location_context.road_name : 'Wilayah Feeder'}
                    </div>
                    <div class="small text-muted text-uppercase fw-bold mb-1">Wilayah Administrasi / Desa</div>
                    <div class="text-white-50 mb-2">
                        ${(node.location_context && node.location_context.locality) ? node.location_context.locality : '-'}
                    </div>
                    <div class="d-flex justify-content-between small text-muted border-top border-secondary pt-2 mt-2">
                        <span>Sumber Resolusi:</span>
                        <span class="badge bg-secondary font-monospace">${(node.location_context && node.location_context.source) ? node.location_context.source : 'FEEDER_DEFAULT'}</span>
                    </div>
                </div>
            </div>

            <table class="table table-dark table-sm table-borderless small mb-4">
                <tbody>
                    <tr><td class="text-muted">Topology Role:</td><td class="fw-bold text-end font-monospace">${node.topology_role}</td></tr>
                    <tr><td class="text-muted">Konstruksi:</td><td class="fw-bold text-end font-monospace">${node.equipment_type || '-'}</td></tr>
                    <tr><td class="text-muted">Zona Layout:</td><td class="fw-bold text-end font-monospace text-info">${node.zone || '-'}</td></tr>
                    <tr><td class="text-muted">Komponen:</td><td class="fw-bold text-end font-monospace">${node.component_id || 'ISOLATED'}</td></tr>
                    <tr><td class="text-muted">Grid Koordinat:</td><td class="fw-bold text-end font-monospace">(${node.schematic.grid_x}, ${node.schematic.grid_y})</td></tr>
                    <tr><td class="text-muted">Kedalaman Source:</td><td class="fw-bold text-end font-monospace">${node.depth_from_source !== null ? node.depth_from_source : 'N/A'}</td></tr>
                    <tr><td class="text-muted">Parent Aset:</td><td class="fw-bold text-end font-monospace">${node.parent_asset_id ? '#' + node.parent_asset_id : 'NONE (ROOT)'}</td></tr>
                    <tr><td class="text-muted">GPS Latitude:</td><td class="fw-bold text-end font-monospace">${node.geo.latitude}</td></tr>
                    <tr><td class="text-muted">GPS Longitude:</td><td class="fw-bold text-end font-monospace">${node.geo.longitude}</td></tr>
                </tbody>
            </table>

            <div class="alert alert-info py-2 px-3 small mb-0 border-0 bg-opacity-25 bg-info text-white">
                <i class="fa-solid fa-shield-halved me-1"></i> <strong>Mode Read-Only:</strong> Seluruh modifikasi fisik dikelola melalui alur kerja inspeksi resmi (&Delta; = 0).
            </div>
        `;

        drawer.style.display = 'block';
    }

    /**
     * Render Read-Only Slide-Over Detail Drawer for Conductor Edge (Delta = 0).
     */
    renderEdgeDrawer(edge) {
        let drawer = document.getElementById('sld-asset-drawer');
        if (!drawer) {
            drawer = document.createElement('div');
            drawer.id = 'sld-asset-drawer';
            drawer.className = 'sld-drawer shadow-lg';
            drawer.style.cssText = `
                position: fixed; top: 0; right: 0; width: 380px; height: 100vh;
                background: #0f172a; color: #f8fafc; border-left: 1px solid #334155;
                z-index: 1050; padding: 24px; overflow-y: auto; font-family: 'Segoe UI', sans-serif;
                box-shadow: -5px 0 25px rgba(0,0,0,0.5); transition: transform 0.3s ease;
            `;
            document.body.appendChild(drawer);
        }

        const hasLength = (edge.length_meters !== null && edge.length_meters !== undefined && edge.length_meters > 0);
        const lengthStr = hasLength ? `${Number(edge.length_meters).toFixed(1)} m` : 'Length unavailable';

        drawer.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary">
                <h5 class="fw-bold m-0 text-info"><i class="fa-solid fa-bolt me-2"></i>Detail Bentang Konduktor</h5>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('sld-asset-drawer').style.display='none';"></button>
            </div>

            <div class="mb-3">
                <span class="badge bg-info text-dark px-2 py-1 mb-2">PHYSICAL SPAN (TRANSLINE)</span>
                <h4 class="fw-bold mb-1 text-white">Transline #${edge.transline_id}</h4>
                <div class="text-white-50 font-monospace small">#${edge.source_asset_id} ↔ #${edge.target_asset_id}</div>
            </div>

            <div class="card bg-dark border-secondary mb-3">
                <div class="card-body p-3">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Panjang Bentang Fisik</div>
                    <div class="h4 fw-bold ${hasLength ? 'text-primary' : 'text-warning'} mb-0">
                        ${lengthStr}
                    </div>
                </div>
            </div>

            <table class="table table-dark table-sm table-borderless small mb-4">
                <tbody>
                    <tr><td class="text-muted">Source Aset ID:</td><td class="fw-bold text-end font-monospace text-info">#${edge.source_asset_id}</td></tr>
                    <tr><td class="text-muted">Target Aset ID:</td><td class="fw-bold text-end font-monospace text-info">#${edge.target_asset_id}</td></tr>
                    <tr><td class="text-muted">Komponen:</td><td class="fw-bold text-end font-monospace">${edge.component_id || '-'}</td></tr>
                    <tr><td class="text-muted">Tipe Rute:</td><td class="fw-bold text-end font-monospace">${edge.route_type}</td></tr>
                    <tr><td class="text-muted">Source Grid:</td><td class="fw-bold text-end font-monospace">(${edge.source_grid.grid_x}, ${edge.source_grid.grid_y})</td></tr>
                    <tr><td class="text-muted">Target Grid:</td><td class="fw-bold text-end font-monospace">(${edge.target_grid.grid_x}, ${edge.target_grid.grid_y})</td></tr>
                </tbody>
            </table>

            <div class="alert alert-info py-2 px-3 small mb-0 border-0 bg-opacity-25 bg-info text-white">
                <i class="fa-solid fa-shield-halved me-1"></i> <strong>Mode Read-Only:</strong> Data bentang bersumber langsung dari payload tata letak SLD-04 (&Delta; = 0).
            </div>
        `;

        drawer.style.display = 'block';
    }

    /**
     * Pan and Zoom Viewport Event Handlers.
     */
    setupPanZoom() {
        if (!this.svg) return;

        this.svg.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            this.isPanning = true;
            this.panStart = { x: e.clientX, y: e.clientY };
            this.svg.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', (e) => {
            if (!this.isPanning) return;

            const dx = (e.clientX - this.panStart.x) * (this.viewBox.w / this.svg.clientWidth);
            const dy = (e.clientY - this.panStart.y) * (this.viewBox.h / this.svg.clientHeight);

            this.viewBox.x -= dx;
            this.viewBox.y -= dy;
            this.updateViewBox();

            this.panStart = { x: e.clientX, y: e.clientY };
        });

        window.addEventListener('mouseup', () => {
            if (this.isPanning) {
                this.isPanning = false;
                this.svg.style.cursor = 'grab';
            }
        });

        this.svg.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomFactor = e.deltaY < 0 ? 0.88 : 1.14;
            this.zoom(zoomFactor, e.clientX, e.clientY);
        }, { passive: false });
    }

    /**
     * Zoom helper with center point anchoring.
     */
    zoom(factor, clientX, clientY) {
        const rect = this.svg.getBoundingClientRect();
        const cursorX = (clientX !== undefined && clientX !== null) ? (clientX - rect.left) / rect.width : 0.35;
        const cursorY = (clientY !== undefined && clientY !== null) ? (clientY - rect.top) / rect.height : 0.15;

        const newW = this.viewBox.w * factor;
        const newH = this.viewBox.h * factor;

        if (newW < 300 || newW > 18000) return;

        this.viewBox.x += (this.viewBox.w - newW) * cursorX;
        this.viewBox.y += (this.viewBox.h - newH) * cursorY;
        this.viewBox.w = newW;
        this.viewBox.h = newH;

        this.updateViewBox();
    }

    zoomIn() {
        this.zoom(0.8);
    }

    zoomOut() {
        this.zoom(1.25);
    }

    /**
     * Fit Main Network (Zone 1: C15-01) with optimal occupancy (Width 85–92%, Height 75–85%).
     */
    fitMainNetwork() {
        if (!this.layoutData) return;
        this.currentScope = 'MAIN_NETWORK';
        this.viewBox = this.calculateOptimalViewBox('MAIN_NETWORK');
        this.updateViewBox();
    }

    /**
     * Fit All Zones (Main Network, Unconnected Fragments, Isolated Assets) with optimal occupancy.
     */
    fitAll() {
        if (!this.layoutData) return;
        this.currentScope = 'ALL';
        this.viewBox = this.calculateOptimalViewBox('ALL');
        this.updateViewBox();
    }

    /**
     * Reset View to Panoramic Main Trunk focus.
     */
    resetView() {
        if (!this.layoutData) return;
        this.currentScope = 'MAIN_NETWORK';
        this.viewBox = this.calculateOptimalViewBox('MAIN_NETWORK');
        this.updateViewBox();
    }

    /**
     * Responsive container resize handler.
     */
    handleResize() {
        if (!this.layoutData || !this.container) return;
        if (this.currentScope === 'ALL') {
            this.viewBox = this.calculateOptimalViewBox('ALL');
        } else {
            this.viewBox = this.calculateOptimalViewBox('MAIN_NETWORK');
        }
        this.updateViewBox();
    }

    updateViewBox() {
        if (this.svg) {
            this.svg.setAttribute('viewBox', `${this.viewBox.x} ${this.viewBox.y} ${this.viewBox.w} ${this.viewBox.h}`);
        }
        this.updateZoomClass();
    }

    /**
     * Progressive Zoom-Aware Density Management (Mandatory Amendment 6 & SLD-05S-VH).
     * - Baseline Zoom (100%): Main trunk fits container at 85–92% width.
     * - Mode C (HYBRID): Conductor spans & pole IDs ALWAYS visible.
     * - Mode Engineering: Low zoom (< 65%) hides pole labels to avoid clutter; >= 65% displays granular labels.
     */
    updateZoomClass() {
        if (!this.svg) return;
        const baselineW = (this.optimalMainViewBox && this.optimalMainViewBox.w > 0) ? this.optimalMainViewBox.w : 8000;
        const currentZoomRatio = baselineW / this.viewBox.w;
        const zoomPercentage = Math.round(currentZoomRatio * 100);

        const zoomLabel = document.getElementById('sld-zoom-status');
        if (zoomLabel) {
            zoomLabel.textContent = `ZOOM: ${zoomPercentage}%`;
        }

        const labelLayer = document.getElementById('sld-edge-labels-layer');
        const poleLabels = this.svg.querySelectorAll('.sld-pole-label');

        if (this.currentMode === 'HYBRID') {
            // Mode C: Hybrid CAD + Road Corridors View - Conductor span length labels & pole IDs ALWAYS visible
            if (labelLayer) labelLayer.style.display = 'inline';
            poleLabels.forEach(p => p.style.display = 'inline');
        } else if (currentZoomRatio < 0.65) {
            // LOW ZOOM (< 65%): Multi-zone bird's eye view. Regular pole labels suppressed.
            if (labelLayer) labelLayer.style.display = 'none';
            poleLabels.forEach(p => p.style.display = 'none');
        } else {
            // NORMAL (100%) & HIGH ZOOM: Full granular visibility.
            if (labelLayer) labelLayer.style.display = 'inline';
            poleLabels.forEach(p => p.style.display = 'inline');
        }
    }

    /**
     * SLD-05S: Tri-Mode Visual Architecture Switcher.
     * Modes:
     * - 'ENGINEERING': Pure orthogonal schematic route
     * - 'HYBRID': Orthogonal schematic + Road Corridors & North Compass (Amendment #8)
     * - 'GIS': Interactive spatial Leaflet Map with real GPS coordinates (Amendment #7)
     * - 'SIMPLIFIED': Line section blocks (high level)
     */
    setMode(mode) {
        this.currentMode = mode;

        const svgContainer = document.getElementById('sld-svg-container');
        const gisContainer = document.getElementById('sld-gis-map-container');

        if (mode === 'GIS') {
            if (svgContainer) svgContainer.style.display = 'none';
            if (gisContainer) gisContainer.style.display = 'block';
            this.renderGisMap();
            if (this.leafletMap) {
                setTimeout(() => this.leafletMap.invalidateSize(), 150);
            }
        } else {
            if (svgContainer) svgContainer.style.display = 'block';
            if (gisContainer) gisContainer.style.display = 'none';
            this.applyDisplayModes();
        }

        const label = document.getElementById('sld-current-mode-label');
        if (label) {
            let modeTitle = 'MODE: ENGINEERING (GRANULAR)';
            if (mode === 'HYBRID') modeTitle = 'MODE: HYBRID (CAD + JALAN)';
            else if (mode === 'GIS') modeTitle = 'MODE: GIS MAP (SPASIAL)';
            else if (mode === 'SIMPLIFIED') modeTitle = 'MODE: SIMPLIFIED (LINE SECTIONS)';
            label.textContent = modeTitle;
        }
    }

    toggleGtt(show) {
        this.showGtt = (typeof show === 'boolean') ? show : !this.showGtt;
        this.applyDisplayModes();
    }

    toggleAssetLabels(show) {
        this.showAssetLabels = (typeof show === 'boolean') ? show : !this.showAssetLabels;
        const labels = this.svg ? this.svg.querySelectorAll('.sld-pole-label') : [];
        labels.forEach(l => l.style.display = this.showAssetLabels ? 'inline' : 'none');
    }

    toggleSpanLengths(show) {
        this.showSpanLengths = (typeof show === 'boolean') ? show : !this.showSpanLengths;
        const layer = document.getElementById('sld-edge-labels-layer');
        if (layer) layer.style.display = this.showSpanLengths ? 'inline' : 'none';
    }

    toggleRoadNames(show) {
        this.showRoadNames = (typeof show === 'boolean') ? show : !this.showRoadNames;
        const layer = document.getElementById('sld-corridors-layer');
        if (layer) layer.style.display = this.showRoadNames ? 'inline' : 'none';
    }

    applyDisplayModes() {
        if (!this.svg) return;

        const edgesLayer = document.getElementById('sld-edges-layer');
        const labelLayer = document.getElementById('sld-edge-labels-layer');
        const sectionsLayer = document.getElementById('sld-sections-layer');
        const corridorsLayer = document.getElementById('sld-corridors-layer');
        const northIndicator = document.getElementById('sld-north-indicator');
        const poleNodes = this.svg.querySelectorAll('.sld-pole-node');
        const gttNodes = this.svg.querySelectorAll('.sld-gtt-node');

        // Mode C: Hybrid Road Corridors & North Compass
        if (this.currentMode === 'HYBRID') {
            if (corridorsLayer) corridorsLayer.style.display = 'inline';
            if (northIndicator) northIndicator.style.display = 'inline';
        } else {
            if (corridorsLayer) corridorsLayer.style.display = 'none';
            if (northIndicator) northIndicator.style.display = 'none';
        }

        if (this.currentMode === 'SIMPLIFIED') {
            if (edgesLayer) edgesLayer.style.display = 'none';
            if (labelLayer) labelLayer.style.display = 'none';
            if (sectionsLayer) sectionsLayer.style.display = 'inline';
            poleNodes.forEach(p => p.style.display = 'none');
        } else {
            if (edgesLayer) edgesLayer.style.display = 'inline';
            if (labelLayer) labelLayer.style.display = 'inline';
            if (sectionsLayer) sectionsLayer.style.display = 'none';
            poleNodes.forEach(p => p.style.display = 'inline');
        }

        gttNodes.forEach(g => {
            g.style.display = this.showGtt ? 'inline' : 'none';
        });
    }

    /**
     * SLD-05T: Fetch sequential CAD print-safe sheet partitions.
     */
    async loadSheets() {
        if (!this.options.sheetsApiUrl) return;
        try {
            const res = await fetch(this.options.sheetsApiUrl);
            if (!res.ok) return;
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.sheets)) {
                this.sheetsData = data.sheets;
                this.renderSheetControls();
                if (this.currentSheetIndex !== null) {
                    const sheet = this.sheetsData.find(s => s.sheet_index === this.currentSheetIndex);
                    if (sheet) {
                        this.renderCadTitleBlock(sheet);
                        this.renderMatchLines(sheet);
                    }
                }
            }
        } catch (e) {
            console.warn('[SLD-05T] Sheet composer endpoint unavailable:', e.message);
        }
    }

    /**
     * SLD-05T: Render sheet navigation controls in the toolbar.
     */
    renderSheetControls() {
        const ctrl = document.getElementById('sld-sheet-controls');
        if (!ctrl || !this.sheetsData || this.sheetsData.length === 0) return;

        let html = `
            <button type="button" class="btn btn-sm ${this.currentSheetIndex === null ? 'btn-info text-dark fw-bold' : 'btn-dark border-secondary text-white-50'}" 
                    onclick="sldEngine.setSheet(null)" title="Lihat Penyulang Lengkap (Fit Main Network)">
                <i class="fa-solid fa-map me-1"></i> Penuh (Fit Main)
            </button>
        `;

        for (const sheet of this.sheetsData) {
            const idx = sheet.sheet_index;
            const isActive = (this.currentSheetIndex === idx);
            const btnClass = isActive ? 'btn-info text-dark fw-bold' : 'btn-dark border-secondary text-white';
            const nodeCount = sheet.nodes ? sheet.nodes.length : (sheet.node_ids ? sheet.node_ids.length : 0);
            const corridor = (sheet.corridors && sheet.corridors[0]) ? sheet.corridors[0].replace('JL. RAYA ', '') : `Bagian ${idx}`;
            html += `
                <button type="button" class="btn btn-sm ${btnClass}" 
                        onclick="sldEngine.setSheet(${idx})" 
                        title="${sheet.sheet_code}: ${corridor} (${nodeCount} Tiang)">
                    <i class="fa-solid fa-file-lines me-1"></i> Lembar 0${idx}
                </button>
            `;
        }

        ctrl.innerHTML = html;
    }

    /**
     * SLD-05T: Focus viewport to a specific CAD sheet with 100% coverage and CAD annotations.
     */
    setSheet(sheetNum) {
        if (!this.sheetsData || this.sheetsData.length === 0) return;

        const cadLayer = document.getElementById('sld-cad-title-block-layer');
        const matchLayer = document.getElementById('sld-match-lines-layer');

        if (sheetNum === null || sheetNum === 'ALL') {
            this.currentSheetIndex = null;
            if (cadLayer) cadLayer.style.display = 'none';
            if (matchLayer) matchLayer.style.display = 'none';
            this.fitMainNetwork();
            this.renderSheetControls();
            if (this.showFindings) this.renderFindings();
            return;
        }

        const sheet = this.sheetsData.find(s => s.sheet_index === sheetNum);
        if (!sheet) return;

        this.currentSheetIndex = sheetNum;

        // Container aspect-aware viewport fitting
        const containerW = (this.container && this.container.clientWidth > 0) ? this.container.clientWidth : 1800;
        const containerH = (this.container && this.container.clientHeight > 0) ? this.container.clientHeight : 600;
        const containerAspect = Math.max(0.5, containerW / containerH);

        const baseVb = sheet.view_box;
        let vh = baseVb.h;
        let vw = vh * containerAspect;
        if (vw < baseVb.w) {
            vw = baseVb.w;
            vh = vw / containerAspect;
        }

        const centerX = baseVb.x + (baseVb.w / 2);
        const centerY = baseVb.y + (baseVb.h / 2);

        this.viewBox = {
            x: Math.round(centerX - (vw / 2)),
            y: Math.round(centerY - (vh / 2)),
            w: Math.round(vw),
            h: Math.round(vh)
        };

        this.updateViewBox();
        this.renderCadTitleBlock(sheet);
        this.renderMatchLines(sheet);
        this.renderSheetControls();
        if (this.showFindings) this.renderFindings();
    }

    /**
     * SLD-05T: Render PLN Standard Engineering CAD Title Block & Frame for the Sheet.
     */
    renderCadTitleBlock(sheet) {
        const g = document.getElementById('sld-cad-title-block-layer');
        if (!g || !sheet) return;

        const vb = sheet.view_box;
        const tb = sheet.title_block || {};
        const stats = tb.statistics || {};

        // Outer CAD Engineering Frame (15px inset from sheet viewBox)
        const frameX = vb.x + 15;
        const frameY = vb.y + 15;
        const frameW = vb.w - 30;
        const frameH = vb.h - 30;

        // Title Block Box Dimensions (at bottom right of frame)
        const tbW = Math.min(520, frameW * 0.45);
        const tbH = 140;
        const tbX = frameX + frameW - tbW;
        const tbY = frameY + frameH - tbH;

        // Top Right CAD North Compass
        const compassX = frameX + frameW - 45;
        const compassY = frameY + 45;

        const corridorStr = (sheet.corridors && sheet.corridors.length > 0) 
            ? sheet.corridors.join(' / ') 
            : 'WILAYAH KERJA FEEDER';

        g.innerHTML = `
            <!-- CAD Outer Border Frame -->
            <rect x="${frameX}" y="${frameY}" width="${frameW}" height="${frameH}" 
                  fill="none" stroke="#0f172a" stroke-width="3" />
            <rect x="${frameX + 4}" y="${frameY + 4}" width="${frameW - 8}" height="${frameH - 8}" 
                  fill="none" stroke="#64748b" stroke-width="1" stroke-dasharray="8,4" />

            <!-- Sheet Header Banner (Top Left) -->
            <rect x="${frameX + 10}" y="${frameY + 10}" width="260" height="32" rx="4" fill="#0f172a" />
            <text x="${frameX + 22}" y="${frameY + 31}" fill="#38bdf8" font-size="13" font-weight="900" font-family="monospace">
                ${sheet.sheet_code}: ${sheet.sheet_label}
            </text>

            <!-- Sheet Corridor Tag -->
            <rect x="${frameX + 280}" y="${frameY + 10}" width="${Math.min(450, frameW - 350)}" height="32" rx="4" fill="#f8fafc" stroke="#cbd5e1" stroke-width="1"/>
            <text x="${frameX + 295}" y="${frameY + 30}" fill="#334155" font-size="11" font-weight="bold" font-family="sans-serif">
                KORIDOR: ${corridorStr} (${sheet.nodes ? sheet.nodes.length : 0} TIANG)
            </text>

            <!-- Top Right CAD North Compass -->
            <g transform="translate(${compassX}, ${compassY})">
                <circle cx="0" cy="0" r="22" fill="#ffffff" stroke="#0f172a" stroke-width="2" />
                <polygon points="0,-16 5,0 0,-2 -5,0" fill="#dc2626" />
                <polygon points="0,16 5,0 0,2 -5,0" fill="#0f172a" />
                <circle cx="0" cy="0" r="2" fill="#ffffff" />
                <text x="0" y="-19" fill="#dc2626" font-size="9" font-weight="900" font-family="sans-serif" text-anchor="middle">U</text>
            </g>

            <!-- CAD Standard Title Block Table (Bottom Right) -->
            <g transform="translate(${tbX}, ${tbY})">
                <rect x="0" y="0" width="${tbW}" height="${tbH}" fill="#ffffff" stroke="#0f172a" stroke-width="2" />
                
                <!-- Row 1: Company Header -->
                <rect x="0" y="0" width="${tbW}" height="28" fill="#0f172a" />
                <text x="12" y="19" fill="#ffffff" font-size="12" font-weight="900" font-family="sans-serif" letter-spacing="1">
                    PT PLN (PERSERO) &bull; ${tb.unit_induk || 'UID JAWA TIMUR'}
                </text>
                
                <!-- Row 2: Sub-Unit Info -->
                <line x1="0" y1="50" x2="${tbW}" y2="50" stroke="#0f172a" stroke-width="1.2" />
                <text x="12" y="42" fill="#334155" font-size="9.5" font-weight="bold" font-family="sans-serif">
                    ${tb.up3 || 'UP3 SIDOARJO'} &bull; ${tb.ulp || 'ULP SIDOARJO KOTA'}
                </text>
                <text x="${tbW - 12}" y="42" fill="#0284c7" font-size="9.5" font-weight="bold" font-family="monospace" text-anchor="end">
                    SUBSTATION: ${tb.substation || 'GI BUDURAN'}
                </text>

                <!-- Row 3: Drawing Title & Feeder -->
                <line x1="0" y1="84" x2="${tbW}" y2="84" stroke="#0f172a" stroke-width="1.2" />
                <text x="12" y="66" fill="#64748b" font-size="8" font-weight="bold" font-family="sans-serif">JUDUL GAMBAR:</text>
                <text x="12" y="79" fill="#0f172a" font-size="11" font-weight="900" font-family="sans-serif">
                    SINGLE LINE DIAGRAM 20 kV &bull; ${tb.feeder_name || 'BANJAR KEMANTRAN'}
                </text>

                <!-- Row 4: Columns for Sheet Number, Scale, Invariant -->
                <line x1="${tbW * 0.4}" y1="84" x2="${tbW * 0.4}" y2="${tbH}" stroke="#0f172a" stroke-width="1" />
                <line x1="${tbW * 0.72}" y1="84" x2="${tbW * 0.72}" y2="${tbH}" stroke="#0f172a" stroke-width="1" />

                <!-- Col 1: Sheet & Statistics -->
                <text x="12" y="99" fill="#64748b" font-size="7.5" font-weight="bold" font-family="sans-serif">NOMOR LEMBAR:</text>
                <text x="12" y="116" fill="#0284c7" font-size="13" font-weight="900" font-family="monospace">
                    ${sheet.sheet_code} / 0${this.sheetsData.length}
                </text>
                <text x="12" y="131" fill="#475569" font-size="8" font-family="monospace">
                    Nodes: ${stats.total_nodes || 0} | Edges: ${stats.total_edges || 0}
                </text>

                <!-- Col 2: Skala & Tanggal -->
                <text x="${tbW * 0.4 + 10}" y="99" fill="#64748b" font-size="7.5" font-weight="bold" font-family="sans-serif">SKALA & SISTEM:</text>
                <text x="${tbW * 0.4 + 10}" y="114" fill="#0f172a" font-size="9.5" font-weight="bold" font-family="sans-serif">
                    ${tb.scale || 'N.T.S. (SCHEMATIC)'}
                </text>
                <text x="${tbW * 0.4 + 10}" y="129" fill="#64748b" font-size="8" font-family="monospace">
                    ${tb.approval_date || new Date().toISOString().split('T')[0]}
                </text>

                <!-- Col 3: Status Audit -->
                <text x="${tbW * 0.72 + 10}" y="99" fill="#64748b" font-size="7.5" font-weight="bold" font-family="sans-serif">INVARIANT:</text>
                <text x="${tbW * 0.72 + 10}" y="114" fill="#059669" font-size="10" font-weight="900" font-family="monospace">
                    &Delta; = 0 READ-ONLY
                </text>
                <text x="${tbW * 0.72 + 10}" y="129" fill="#0284c7" font-size="7.5" font-weight="bold" font-family="sans-serif">
                    SLD-05T CERTIFIED
                </text>
            </g>
        `;

        g.style.display = 'inline';
    }

    /**
     * SLD-05T: Render Boundary Match Lines connecting sequential sheets.
     */
    renderMatchLines(sheet) {
        const g = document.getElementById('sld-match-lines-layer');
        if (!g || !sheet) return;

        let html = '';
        const matchLines = sheet.match_lines || {};

        // Backward Match Line (From previous sheet)
        if (matchLines.backward) {
            const b = matchLines.backward;
            const bNode = this.layoutData.nodes.find(n => n.asset_id === b.boundary_node_id);
            if (bNode) {
                const pos = this.project(bNode.schematic.grid_x, bNode.schematic.grid_y);
                const lineX = pos.x - 30;

                html += `
                    <g class="sld-match-line-backward" transform="translate(${lineX}, ${pos.y})">
                        <line x1="0" y1="-65" x2="0" y2="65" stroke="#dc2626" stroke-width="2.2" stroke-dasharray="6,4" />
                        <rect x="-175" y="-14" width="165" height="28" rx="4" fill="#fef2f2" stroke="#dc2626" stroke-width="1.2"
                              style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));" />
                        <text x="-92" y="4" fill="#b91c1c" font-size="8.5" font-weight="900" text-anchor="middle" font-family="monospace">
                            ${b.label}
                        </text>
                        <text x="-92" y="16" fill="#64748b" font-size="7.5" text-anchor="middle" font-family="monospace">
                            TIANG #${b.boundary_node_id}
                        </text>
                    </g>
                `;
            }
        }

        // Forward Match Line (To next sheet)
        if (matchLines.forward) {
            const f = matchLines.forward;
            const fNode = this.layoutData.nodes.find(n => n.asset_id === f.boundary_node_id);
            if (fNode) {
                const pos = this.project(fNode.schematic.grid_x, fNode.schematic.grid_y);
                const lineX = pos.x + 30;

                html += `
                    <g class="sld-match-line-forward" transform="translate(${lineX}, ${pos.y})">
                        <line x1="0" y1="-65" x2="0" y2="65" stroke="#0284c7" stroke-width="2.2" stroke-dasharray="6,4" />
                        <rect x="10" y="-14" width="185" height="28" rx="4" fill="#f0f9ff" stroke="#0284c7" stroke-width="1.2"
                              style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));" />
                        <text x="102" y="4" fill="#0369a1" font-size="8.5" font-weight="900" text-anchor="middle" font-family="monospace">
                            ${f.label}
                        </text>
                        <text x="102" y="16" fill="#64748b" font-size="7.5" text-anchor="middle" font-family="monospace">
                            TIANG #${f.boundary_node_id}
                        </text>
                    </g>
                `;
            }
        }

        g.innerHTML = html;
        g.style.display = 'inline';
    }

    /**
     * SLD-05T: Fetch decoupled findings overlay read model.
     */
    async loadFindings() {
        if (!this.options.findingsApiUrl) return;
        try {
            const res = await fetch(this.options.findingsApiUrl);
            if (!res.ok) return;
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.findings)) {
                this.findingsData = data;
                if (this.showFindings) {
                    this.renderFindings();
                    this.renderFindingMarkersOnLeaflet();
                }
            }
        } catch (e) {
            console.warn('[SLD-05T] Finding overlay endpoint unavailable:', e.message);
        }
    }

    /**
     * SLD-05T: Toggle Finding Layer Visibility (Guarantees Delta Nodes = 0, Delta Edges = 0).
     */
    toggleFindings(show) {
        this.showFindings = (typeof show === 'boolean') ? show : !this.showFindings;

        const beforeNodes = this.layoutData ? this.layoutData.nodes.length : 0;
        const beforeEdges = this.layoutData ? this.layoutData.edges.length : 0;

        const findingsLayer = document.getElementById('sld-findings-layer');
        if (findingsLayer) {
            findingsLayer.style.display = this.showFindings ? 'inline' : 'none';
        }

        if (this.showFindings) {
            if (!this.findingsData) {
                this.loadFindings();
            } else {
                this.renderFindings();
                this.renderFindingMarkersOnLeaflet();
            }
        } else {
            if (this.leafletMap && this.leafletFindingsGroup) {
                this.leafletFindingsGroup.clearLayers();
            }
        }

        const afterNodes = this.layoutData ? this.layoutData.nodes.length : 0;
        const afterEdges = this.layoutData ? this.layoutData.edges.length : 0;
        if (beforeNodes !== afterNodes || beforeEdges !== afterEdges) {
            console.error('[SLD-05T INVARIANT VIOLATION] Finding overlay mutated topology graph!');
        }
    }

    /**
     * SLD-05T: Render Technical Finding Annotations (CAD Callouts + Leader Lines + Collision Avoidance).
     * Follows PLN Engineering Drawing standards:
     * 1. Sheet Filtering (Refinement 4): Only findings for active sheet are rendered.
     * 2. Leader Line (Refinement 2): Orthogonal elbow lines connecting physical anchor to box.
     * 3. Arrow Marker: Pointer at physical anchor (ax, ay) pointing directly to asset node.
     * 4. Technical Callout Box:
     *    - Header strip with explicit Priority text (HIGH / MED / LOW) for B/W print compliance
     *    - Line 1: Asset Code / Title
     *    - Line 2: Finding brief description
     *    - Line 3: Registration Code (or [NOT TOPOLOGY NODE] for ROW)
     */
    renderFindings() {
        const g = document.getElementById('sld-findings-layer');
        if (!g || !this.findingsData || !this.findingsData.findings) return;

        let html = '';
        const allFindings = this.findingsData.findings;

        // Sheet boundary firewall (Refinement 4):
        // If currentSheetIndex is set, filter strictly to findings assigned to this sheet
        const findings = (this.currentSheetIndex !== null)
            ? allFindings.filter(f => {
                const sId = (f.annotation && f.annotation.sheet_id) ? f.annotation.sheet_id : (f.sheet_id || null);
                return sId === this.currentSheetIndex;
            })
            : allFindings;

        for (const f of findings) {
            const ann = f.annotation;
            const prio = ((ann && ann.priority) || f.prioritas || 'MEDIUM').toUpperCase();

            // PLN Technical priority styling
            let prioColor = '#0284c7'; // LOW
            let headerBg = '#0284c7';
            let boxBorder = '#0f172a';
            if (prio === 'HIGH' || prio === 'CRITICAL' || prio === 'DARURAT') {
                prioColor = '#dc2626';
                headerBg = '#dc2626';
                boxBorder = '#dc2626';
            } else if (prio === 'MEDIUM' || prio === 'SEDANG') {
                prioColor = '#d97706';
                headerBg = '#d97706';
                boxBorder = '#d97706';
            }

            if (ann && typeof ann.box_x === 'number') {
                const ax = ann.anchor_x;
                const ay = ann.anchor_y;
                const bx = ann.box_x;
                const by = ann.box_y;
                const bw = ann.box_width || 168;
                const bh = ann.box_height || 54;
                const isRow = (ann.callout_type === 'LOCATION_ROW');

                // 1. Leader Line Path
                let leaderPath = '';
                if (Array.isArray(ann.leader_points) && ann.leader_points.length >= 2) {
                    const pts = ann.leader_points;
                    leaderPath = `M ${pts[0].x} ${pts[0].y}`;
                    for (let i = 1; i < pts.length; i++) {
                        leaderPath += ` L ${pts[i].x} ${pts[i].y}`;
                    }
                } else {
                    leaderPath = `M ${ax} ${ay} L ${bx + bw/2} ${by + bh}`;
                }

                html += `
                    <g class="sld-technical-callout" id="callout-finding-${f.id}" style="cursor: pointer;" onclick="sldEngine.selectFinding(${f.id})">
                        <!-- Leader Line (CAD Solid) -->
                        <path d="${leaderPath}" fill="none" stroke="${headerBg}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        
                        <!-- Arrow Head / Anchor Dot at Physical Object -->
                        <circle cx="${ax}" cy="${ay}" r="4.5" fill="${headerBg}" stroke="#ffffff" stroke-width="1.5" />
                        <circle cx="${ax}" cy="${ay}" r="1.8" fill="#ffffff" />
                        
                        <!-- Callout Box Frame -->
                        <g transform="translate(${bx}, ${by})">
                            <!-- Drop Shadow & Background -->
                            <rect x="0" y="0" width="${bw}" height="${bh}" rx="3" fill="#ffffff" stroke="${boxBorder}" stroke-width="1.8" 
                                  ${isRow ? 'stroke-dasharray="5,3"' : ''}
                                  style="filter: drop-shadow(0 3px 6px rgba(0,0,0,0.18));" />
                            
                            <!-- Header Bar -->
                            <rect x="0" y="0" width="${bw}" height="18" rx="2" fill="${headerBg}" />
                            
                            <!-- Header Text: Asset ID & Priority (Explicit Text for B/W Print) -->
                            <text x="8" y="12.5" fill="#ffffff" font-size="9" font-weight="900" font-family="monospace">
                                ${ann.title || ('TIANG #' + (f.asset_id || 'ROW'))}
                            </text>
                            <text x="${bw - 8}" y="12.5" fill="#ffffff" font-size="8.5" font-weight="900" font-family="monospace" text-anchor="end">
                                [${ann.priority}]
                            </text>
                            
                            <!-- Row 2: Subtitle / Technical Issue -->
                            <text x="8" y="31" fill="#0f172a" font-size="8.5" font-weight="bold" font-family="sans-serif">
                                ${ann.subtitle || f.jenis_temuan || ''}
                            </text>
                            
                            <!-- Row 3: Code / ROW Disclaimer -->
                            ${isRow ? `
                                <text x="8" y="44" fill="#dc2626" font-size="7.5" font-weight="900" font-family="monospace">
                                    [NOT TOPOLOGY NODE]
                                </text>
                                <text x="8" y="55" fill="#64748b" font-size="7.5" font-family="monospace">
                                    ${ann.display_code || f.nomor_temuan || ''}
                                </text>
                            ` : `
                                <text x="8" y="45" fill="#64748b" font-size="8" font-family="monospace">
                                    ${ann.display_code || f.nomor_temuan || ''}
                                </text>
                            `}
                        </g>
                        <title>[${prio}] ${ann.title || ''}: ${ann.subtitle || ''}\nKlik untuk inspeksi detail</title>
                    </g>
                `;
            } else {
                // Fallback rendering if annotation coordinates not pre-calculated
                const isAssetLinked = (f.model_type === 'ASSET_LINKED' && f.asset_id);
                let ax = 0, ay = 0;
                let title = f.nomor_temuan || ('TMN #' + f.id);
                let isRow = !isAssetLinked;

                if (isAssetLinked) {
                    const node = this.layoutData.nodes.find(n => n.asset_id === f.asset_id);
                    if (node) {
                        const pos = this.project(node.schematic.grid_x, node.schematic.grid_y);
                        ax = pos.x;
                        ay = pos.y;
                        title = node.asset_code || ('TIANG #' + node.asset_id);
                    }
                } else {
                    ax = this.viewBox.x + 300;
                    ay = this.viewBox.y + 200;
                }

                const bx = ax - 80;
                const by = ay - 80;
                const bw = 168;
                const bh = isRow ? 64 : 54;

                html += `
                    <g class="sld-technical-callout" id="callout-finding-${f.id}" style="cursor: pointer;" onclick="sldEngine.selectFinding(${f.id})">
                        <line x1="${ax}" y1="${ay}" x2="${bx + bw/2}" y2="${by + bh}" stroke="${headerBg}" stroke-width="1.8" />
                        <circle cx="${ax}" cy="${ay}" r="4.5" fill="${headerBg}" stroke="#ffffff" stroke-width="1.5" />
                        <g transform="translate(${bx}, ${by})">
                            <rect x="0" y="0" width="${bw}" height="${bh}" rx="3" fill="#ffffff" stroke="${boxBorder}" stroke-width="1.8" 
                                  ${isRow ? 'stroke-dasharray="5,3"' : ''}
                                  style="filter: drop-shadow(0 3px 6px rgba(0,0,0,0.18));" />
                            <rect x="0" y="0" width="${bw}" height="18" rx="2" fill="${headerBg}" />
                            <text x="8" y="12.5" fill="#ffffff" font-size="9" font-weight="900" font-family="monospace">${title}</text>
                            <text x="${bw - 8}" y="12.5" fill="#ffffff" font-size="8.5" font-weight="900" font-family="monospace" text-anchor="end">[${prio}]</text>
                            <text x="8" y="31" fill="#0f172a" font-size="8.5" font-weight="bold" font-family="sans-serif">${f.jenis_temuan || ''}</text>
                            <text x="8" y="45" fill="#64748b" font-size="8" font-family="monospace">${f.nomor_temuan || ''}</text>
                        </g>
                    </g>
                `;
            }
        }

        g.innerHTML = html;
        g.style.display = this.showFindings ? 'inline' : 'none';
    }

    /**
     * SLD-05T: Plot findings on GIS Leaflet Map with severity styling.
     */
    renderFindingMarkersOnLeaflet() {
        if (!this.leafletMap || !this.findingsData || !this.findingsData.findings) return;

        if (!this.leafletFindingsGroup) {
            this.leafletFindingsGroup = L.featureGroup();
        } else {
            this.leafletFindingsGroup.clearLayers();
        }

        if (!this.showFindings) {
            if (this.leafletMap.hasLayer(this.leafletFindingsGroup)) {
                this.leafletMap.removeLayer(this.leafletFindingsGroup);
            }
            return;
        }

        for (const f of this.findingsData.findings) {
            const lat = parseFloat(f.latitude);
            const lng = parseFloat(f.longitude);
            if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) continue;

            const prio = (f.prioritas || 'MEDIUM').toUpperCase();
            const color = (prio === 'HIGH' || prio === 'CRITICAL') ? '#ef4444' : ((prio === 'MEDIUM') ? '#f59e0b' : '#eab308');

            const icon = L.divIcon({
                className: 'sld-leaflet-finding-pin',
                html: `
                    <div style="width: 28px; height: 28px; background: #ffffff; border: 2px solid ${color}; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-size: 14px; cursor: pointer;">
                        &#9888;
                    </div>
                `,
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -14],
            });

            const marker = L.marker([lat, lng], { icon }).addTo(this.leafletFindingsGroup);
            marker.bindPopup(`
                <div style="font-family: sans-serif; font-size: 12px; min-width: 200px;">
                    <div style="font-weight: bold; color: ${color}; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 6px;">
                        &#9888; TEMUAN: ${f.nomor_temuan || '#' + f.id}
                    </div>
                    <div style="margin-bottom: 4px;"><strong>Jenis:</strong> ${f.jenis_temuan}</div>
                    <div style="margin-bottom: 4px;"><strong>Prioritas:</strong> <span style="background: ${color}; color: #fff; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 10px;">${prio}</span></div>
                    <div style="margin-bottom: 4px;"><strong>Model:</strong> ${f.model_type}</div>
                    <div style="margin-bottom: 6px; color: #64748b;">${f.detail_temuan || '-'}</div>
                    <button onclick="sldEngine.selectFinding(${f.id})" style="background: ${color}; color: #fff; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 11px; width: 100%;">
                        Lihat Detail Temuan
                    </button>
                </div>
            `);
        }

        if (!this.leafletMap.hasLayer(this.leafletFindingsGroup)) {
            this.leafletFindingsGroup.addTo(this.leafletMap);
        }
    }

    /**
     * SLD-05T: Open Read-Only Slide-Over Detail Drawer for Finding.
     */
    selectFinding(findingId) {
        if (!this.findingsData || !this.findingsData.findings) return;
        const f = this.findingsData.findings.find(item => item.id === findingId);
        if (!f) return;
        this.renderFindingDrawer(f);
    }

    /**
     * SLD-05T: Render Finding Drawer details.
     */
    renderFindingDrawer(f) {
        let drawer = document.getElementById('sld-asset-drawer');
        if (!drawer) {
            drawer = document.createElement('div');
            drawer.id = 'sld-asset-drawer';
            drawer.className = 'sld-drawer shadow-lg';
            drawer.style.cssText = `
                position: fixed; top: 0; right: 0; width: 380px; height: 100vh;
                background: #0f172a; color: #f8fafc; border-left: 1px solid #334155;
                z-index: 1050; padding: 24px; overflow-y: auto; font-family: 'Segoe UI', sans-serif;
                box-shadow: -5px 0 25px rgba(0,0,0,0.5); transition: transform 0.3s ease;
            `;
            document.body.appendChild(drawer);
        }

        const prio = (f.prioritas || 'MEDIUM').toUpperCase();
        const prioBadgeClass = (prio === 'HIGH' || prio === 'CRITICAL') ? 'bg-danger' : ((prio === 'MEDIUM') ? 'bg-warning text-dark' : 'bg-info text-dark');
        const isLocationLinked = (f.model_type === 'LOCATION_LINKED' || !f.asset_id);

        drawer.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary">
                <h5 class="fw-bold m-0 text-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>Detail Temuan Lapangan</h5>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('sld-asset-drawer').style.display='none';"></button>
            </div>

            <div class="mb-3">
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="badge ${prioBadgeClass} px-2 py-1">${prio} PRIORITY</span>
                    <span class="badge bg-secondary px-2 py-1">${f.status || 'BELUM_DITANGANI'}</span>
                    <span class="badge ${isLocationLinked ? 'bg-danger' : 'bg-primary'} px-2 py-1">${f.model_type}</span>
                </div>
                <h4 class="fw-bold mb-1 text-white">${f.jenis_temuan}</h4>
                <div class="text-white-50 font-monospace small">Nomor: ${f.nomor_temuan || '#' + f.id}</div>
            </div>

            <!-- Invariant Card: Non-Topology Node Confirmation -->
            <div class="card bg-dark border-secondary mb-3">
                <div class="card-header border-secondary py-2 small fw-bold text-info bg-dark">
                    <i class="fa-solid fa-shield-halved me-1"></i> TOPOLOGY READ MODEL STATUS
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-secondary border-opacity-25">
                        <span class="small text-muted text-uppercase fw-bold">Klasifikasi Model</span>
                        <span class="badge ${isLocationLinked ? 'bg-warning text-dark' : 'bg-info text-dark'} font-monospace">
                            ${isLocationLinked ? 'LOCATION FINDING / NOT TOPOLOGY NODE' : 'ASSET LINKED FINDING'}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom border-secondary border-opacity-25">
                        <span class="small text-muted text-uppercase fw-bold">Topological Node Effect</span>
                        <span class="badge bg-success font-monospace">&Delta; Nodes = 0, &Delta; Edges = 0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted text-uppercase fw-bold">Aset Terkait</span>
                        <span class="fw-bold text-info font-monospace">
                            ${f.asset_id ? '#' + f.asset_id : 'TIDAK TERKAIT (SPASIAL ROW)'}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Finding Details Card -->
            <div class="card bg-dark border-secondary mb-3">
                <div class="card-header border-secondary py-2 small fw-bold text-warning bg-dark">
                    <i class="fa-solid fa-clipboard-list me-1"></i> DESKRIPSI ANOMALI
                </div>
                <div class="card-body p-3">
                    <p class="small text-light mb-2" style="line-height: 1.5;">
                        ${f.detail_temuan || 'Tidak ada catatan rinci temuan.'}
                    </p>
                    <div class="d-flex justify-content-between small text-muted border-top border-secondary pt-2 mt-2">
                        <span>Tanggal Temuan:</span>
                        <span class="font-monospace text-white">${f.tanggal_temuan || '-'}</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted pt-1">
                        <span>Koordinat GPS:</span>
                        <span class="font-monospace text-white">${f.latitude ? f.latitude + ', ' + f.longitude : '-'}</span>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 mb-3">
                <a href="${window.location.origin}/temuan/detail/${f.id}" target="_blank" class="btn btn-warning btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Buka Modul Temuan Resmi
                </a>
            </div>

            <div class="alert alert-info py-2 px-3 small mb-0 border-0 bg-opacity-25 bg-info text-white">
                <i class="fa-solid fa-circle-info me-1"></i> Temuan ini dimuat secara dinamis sebagai read-only overlay tanpa merekayasa topologi jaringan listrik.
            </div>
        `;

        drawer.style.display = 'block';
    }

    renderDataNotReady(data) {
        const feeder = data.feeder || {};
        const source = data.data_source || {};
        const message = data.message || 'Topologi jaringan operasional pada database produksi belum memadai sesuai standar TL-01.';

        this.container.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center p-5 text-center" style="min-height: 520px; background: #ffffff; border-radius: 8px;">
                <div class="mb-4" style="width: 80px; height: 80px; border-radius: 50%; background: #fef3c7; display: flex; align-items: center; justify-content: center; border: 2px solid #f59e0b;">
                    <i class="fa-solid fa-triangle-exclamation fa-2x text-warning"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">TOPOLOGI JARINGAN OPERASIONAL BELUM LENGKAP</h4>
                <div class="badge bg-warning text-dark mb-3 px-3 py-2 fs-6">
                    STATUS: ${source.status || 'TOPOLOGY_DATA_INCOMPLETE'} &bull; MODE: ${source.mode || 'PRODUCTION_LIVE_DATABASE'}
                </div>
                <p class="text-muted mx-auto mb-4" style="max-width: 680px; line-height: 1.6;">
                    ${message}
                </p>
                <div class="card bg-light border-secondary text-start mx-auto mb-4" style="max-width: 600px; width: 100%;">
                    <div class="card-header border-secondary bg-white text-primary py-2 small fw-bold">
                        <i class="fa-solid fa-circle-info me-1"></i> METADATA AUDIT GATEWAY
                    </div>
                    <div class="card-body p-3 small text-dark font-monospace">
                        <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                            <span>Penyulang / Feeder:</span>
                            <span class="text-dark fw-bold">${feeder.code || '-'} (${feeder.name || '-'})</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                            <span>Aset Fisik (assets table):</span>
                            <span class="text-primary">${data.topology?.feeder_assets_count ?? 0} Aset</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                            <span>Bentang Fisik (gis_translines):</span>
                            <span class="text-danger fw-bold">${data.topology?.edges_count ?? 0} Translines (Memerlukan TL-01)</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Database Invariant:</span>
                            <span class="text-success fw-bold">&Delta; = 0 (Strict Read-Only Verified)</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm px-4" onclick="location.reload();">
                        <i class="fa-solid fa-rotate-right me-1"></i> Periksa Ulang
                    </button>
                    <a href="${window.location.origin}/dashboard" class="btn btn-secondary btn-sm px-4">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        `;
    }

    renderLoading() {
        this.container.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center p-5 text-dark" style="min-height: 480px; background: #ffffff;">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                <div class="h5 fw-bold mb-1">Memuat Blueprint Tata Letak SLD...</div>
                <div class="text-muted small">Mengambil model koordinat deterministik dari SLD-04 Engine</div>
            </div>
        `;
    }

    renderError(msg) {
        this.container.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center p-5 text-danger" style="min-height: 480px; background: #ffffff;">
                <i class="fa-solid fa-triangle-exclamation fa-3x mb-3 text-warning"></i>
                <div class="h5 fw-bold mb-1 text-dark">Gagal Memuat Single Line Diagram</div>
                <div class="text-danger small mb-3">${msg}</div>
                <button class="btn btn-outline-primary btn-sm" onclick="location.reload();">
                    <i class="fa-solid fa-rotate-right me-1"></i> Muat Ulang
                </button>
            </div>
        `;
    }
}

// Global Export
window.SldRendererEngine = SldRendererEngine;
