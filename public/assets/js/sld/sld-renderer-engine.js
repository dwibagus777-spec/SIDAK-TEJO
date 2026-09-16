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
            feederId: null,
            scaleX: 75,
            scaleY: 85,
            offsetX: 160,
            offsetY: 180,
            defaultMode: 'ENGINEERING', // 'ENGINEERING', 'HYBRID', 'GIS', or 'SIMPLIFIED'
            showGtt: true,
            onSelectAsset: null,
        }, options);

        this.layoutData = null;
        this.currentMode = this.options.defaultMode;
        this.showGtt = this.options.showGtt;

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
                    this.cachedFingerprint = data.data_fingerprint;
                    const alertEl = document.getElementById('sld-refresh-alert');
                    if (alertEl) alertEl.classList.remove('d-none');
                    await this.load();
                    setTimeout(() => {
                        if (alertEl) alertEl.classList.add('d-none');
                    }, 3500);
                }
            }
        } catch (e) {
            // Non-blocking resilient fallback
        }
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

        // Panoramic Initial Viewport: Centers on Main Trunk (GI -> Incomer -> Branches)
        const incomer = this.layoutData.nodes.find(n => n.device_role === 'SOURCE_INCOMER' || n.topology_role === 'SOURCE_INCOMER') 
                     || this.layoutData.nodes[0];
        
        if (incomer && incomer.schematic) {
            const rootPos = this.project(incomer.schematic.grid_x, incomer.schematic.grid_y);
            this.initialViewBox = {
                x: Math.max(0, rootPos.x - 120),
                y: Math.max(0, rootPos.y - 320),
                w: 2200,
                h: 1150
            };
        } else {
            this.initialViewBox = { x: 0, y: 0, w: 2200, h: 1150 };
        }

        this.viewBox = Object.assign({}, this.initialViewBox);

        // Light Engineering Theme Canvas Shell
        const targetBox = document.getElementById('sld-svg-container') || this.container;
        targetBox.innerHTML = `
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

                    <!-- Mode C: North Orientation Indicator (top overlay) -->
                    <g id="sld-north-indicator" class="sld-layer" style="display: none;"></g>
                </svg>

                <!-- Floating Canvas Minimap / Mode Indicator -->
                <div class="sld-mode-indicator badge bg-light text-dark border border-secondary shadow-sm" 
                     style="position: absolute; bottom: 15px; left: 15px; z-index: 10; font-family: monospace; font-size: 0.8rem;">
                    <span id="sld-current-mode-label" class="fw-bold text-primary">${this.currentMode === 'ENGINEERING' ? 'MODE: ENGINEERING (GRANULAR)' : (this.currentMode === 'HYBRID' ? 'MODE: HYBRID (CAD + JALAN)' : (this.currentMode === 'GIS' ? 'MODE: GIS MAP (SPASIAL)' : 'MODE: SIMPLIFIED (LINE SECTIONS)'))}</span> | 
                    <span>NODES: <strong>${this.layoutData.nodes.length}</strong></span> | 
                    <span>EDGES: <strong>${this.layoutData.edges.length}</strong></span> | 
                    <span id="sld-zoom-status" class="fw-bold">ZOOM: 100%</span>
                </div>
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
                halfW = 20; halfH = 26;
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
     * Render Road Corridors for Mode C (Hybrid CAD + Road Corridors View - Amendment #8).
     * Strictly visual schematic corridor boundaries with road names.
     */
    renderRoadCorridors() {
        const g = document.getElementById('sld-corridors-layer');
        if (!g || !this.layoutData || !this.layoutData.corridors) return;

        let html = '';
        for (const corr of this.layoutData.corridors) {
            const b = corr.bounds;
            if (!b) continue;

            const p1 = this.project(b.min_grid_x, b.min_grid_y);
            const p2 = this.project(b.max_grid_x, b.max_grid_y);

            const padX = 45;
            const padY = 40;
            const x = Math.min(p1.x, p2.x) - padX;
            const y = Math.min(p1.y, p2.y) - padY;
            const w = Math.abs(p2.x - p1.x) + (padX * 2);
            const h = Math.abs(p2.y - p1.y) + (padY * 2);

            const roadTitle = (corr.road_name || 'KORIDOR JALAN').toUpperCase();
            const localityTitle = corr.locality || '';
            const bannerText = `${roadTitle} • ${localityTitle}`;
            const bannerW = Math.min(Math.max(220, bannerText.length * 7.5 + 40), Math.max(220, w - 20));

            html += `
                <g class="sld-road-corridor" data-corridor-id="${corr.corridor_id}">
                    <!-- Outer Road Corridor Band / Double Guide Lines -->
                    <rect x="${x}" y="${y}" width="${w}" height="${h}" rx="10"
                          fill="#f8fafc" fill-opacity="0.5"
                          stroke="#cbd5e1" stroke-width="1.8" stroke-dasharray="6,4" />
                    <rect x="${x + 4}" y="${y + 4}" width="${Math.max(0, w - 8)}" height="${Math.max(0, h - 8)}" rx="8"
                          fill="none" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3,3" />

                    <!-- Road Corridor Header Banner Pill -->
                    <rect x="${x + 14}" y="${y - 12}" width="${bannerW}" height="22" rx="4"
                          fill="#0284c7" stroke="#0369a1" stroke-width="1" />
                    <text x="${x + 22}" y="${y + 2}" 
                          fill="#ffffff" font-size="9.5" font-weight="bold" font-family="sans-serif">
                        <tspan fill="#bae6fd">&#128739; JALAN:</tspan> ${roadTitle}
                    </text>
                </g>
            `;
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

        // 2. Plot Nodes (Equipment Points)
        for (const item of validGeoNodes) {
            const n = item.node;
            const devRole = n.device_role;
            const topRole = n.topology_role;
            const loc = n.location_context || {};

            let markerColor = '#334155';
            let radius = 5;

            if (devRole === 'SOURCE_INCOMER' || topRole === 'SOURCE_INCOMER') {
                markerColor = '#dc2626';
                radius = 9;
            } else if (devRole === 'SWITCH_CANDIDATE') {
                markerColor = '#d97706';
                radius = 8;
            } else if (devRole === 'TRANSFORMER_NODE') {
                markerColor = '#059669';
                radius = 7;
            } else if (topRole === 'BRANCH_NODE') {
                markerColor = '#e11d48';
                radius = 6;
            } else if (topRole === 'TERMINAL_NODE') {
                markerColor = '#9333ea';
                radius = 5;
            }

            const circle = L.circleMarker([item.lat, item.lng], {
                radius: radius,
                fillColor: markerColor,
                color: '#ffffff',
                weight: 1.5,
                opacity: 1,
                fillOpacity: 0.9,
            }).addTo(this.leafletFeatureGroup);

            const popupContent = `
                <div style="font-family: sans-serif; font-size: 12px; min-width: 200px;">
                    <div style="font-weight: bold; color: #0f172a; font-size: 13px; margin-bottom: 4px;">
                        ${n.name}
                    </div>
                    <div style="margin-bottom: 6px;">
                        <span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 3px; font-weight: bold; font-size: 10px;">
                            ${devRole}
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
            circle.bindPopup(popupContent);
            circle.on('click', () => {
                this.selectAsset(n.asset_id);
            });
        }

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
            const isGtt = (devRole === 'TRANSFORMER_NODE');

            let symbolMarkup = '';
            let labelMarkup = '';
            let extraClass = isGtt ? 'sld-gtt-node' : '';

            // 1. SOURCE_INCOMER (Riser Pole Demarcation directly under GI Buduran Takeoff)
            if (devRole === 'SOURCE_INCOMER' || topRole === 'SOURCE_INCOMER') {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="10" fill="#ffffff" stroke="#0f172a" stroke-width="3"/>
                    <circle cx="0" cy="0" r="4.5" fill="#0f172a"/>
                `;
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
            // 2. SWITCH_CANDIDATE (PMS - Neutral UNKNOWN state, strictly no green/red assumption)
            else if (devRole === 'SWITCH_CANDIDATE') {
                symbolMarkup = `
                    <polygon points="0,-16 16,0 0,16 -16,0" fill="#fef3c7" stroke="#d97706" stroke-width="2.2" />
                    <text x="0" y="4.5" fill="#b45309" font-size="12.5" font-weight="900" text-anchor="middle" font-family="sans-serif">?</text>
                `;
                labelMarkup = `
                    <rect x="-46" y="-30" width="92" height="15" rx="3" fill="#fef3c7" stroke="#d97706" stroke-width="1"/>
                    <text x="0" y="-19" fill="#92400e" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace">
                        PMS (UNKNOWN)
                    </text>
                    <text x="0" y="28" fill="#78350f" font-size="8.5" text-anchor="middle" font-family="monospace" font-weight="bold">
                        #${aId}
                    </text>
                `;
            }
            // 3. TRANSFORMER_NODE (GTT Cantol 1T, Portal 2T, or Unknown)
            else if (devRole === 'TRANSFORMER_NODE') {
                const gttName = node.name || `GTT #${aId}`;
                const shortName = gttName.length > 14 ? gttName.substring(0, 12) + '..' : gttName;

                if (eqType === 'GTT1_CANTOL') {
                    // Cantol (1 Tiang): 1 tap leg with downward solid triangle
                    symbolMarkup = `
                        <line x1="0" y1="0" x2="0" y2="14" stroke="#0f172a" stroke-width="2"/>
                        <polygon points="0,30 -10,14 10,14" fill="#059669" stroke="#047857" stroke-width="1.2"/>
                        <text x="0" y="23" fill="#ffffff" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">1T</text>
                    `;
                    labelMarkup = `
                        <rect x="-42" y="34" width="84" height="24" rx="3" fill="#f0fdf4" stroke="#059669" stroke-width="0.8"/>
                        <text x="0" y="44" fill="#065f46" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="sans-serif">${shortName}</text>
                        <text x="0" y="54" fill="#047857" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">GTT CANTOL</text>
                    `;
                } else if (eqType === 'GTT2_PORTAL') {
                    // Portal (2 Tiang): H-frame double support legs with downward solid triangle
                    symbolMarkup = `
                        <line x1="-6" y1="0" x2="-6" y2="14" stroke="#0f172a" stroke-width="2"/>
                        <line x1="6" y1="0" x2="6" y2="14" stroke="#0f172a" stroke-width="2"/>
                        <line x1="-9" y1="14" x2="9" y2="14" stroke="#0f172a" stroke-width="2"/>
                        <polygon points="0,30 -10,14 10,14" fill="#0284c7" stroke="#0369a1" stroke-width="1.2"/>
                        <text x="0" y="23" fill="#ffffff" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">2T</text>
                    `;
                    labelMarkup = `
                        <rect x="-42" y="34" width="84" height="24" rx="3" fill="#f0f9ff" stroke="#0284c7" stroke-width="0.8"/>
                        <text x="0" y="44" fill="#075985" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="sans-serif">${shortName}</text>
                        <text x="0" y="54" fill="#0369a1" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">GTT PORTAL</text>
                    `;
                } else {
                    // Generic GTT / Unknown Subtype
                    symbolMarkup = `
                        <line x1="0" y1="0" x2="0" y2="14" stroke="#0f172a" stroke-width="2"/>
                        <polygon points="0,30 -10,14 10,14" fill="#0d9488" stroke="#0f766e" stroke-width="1.2"/>
                        <text x="0" y="23" fill="#ffffff" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">GTT</text>
                    `;
                    labelMarkup = `
                        <rect x="-38" y="34" width="76" height="22" rx="3" fill="#f0fdfa" stroke="#0d9488" stroke-width="0.8"/>
                        <text x="0" y="44" fill="#115e59" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="sans-serif">${shortName}</text>
                        <text x="0" y="53" fill="#0f766e" font-size="7.5" font-weight="bold" text-anchor="middle" font-family="monospace">GTT TRAFO</text>
                    `;
                }
            }
            // 4. LBS / LBSM Keypoint (PLN/IEC Quartered Circle)
            else if (devRole === 'LBS' || eqType.includes('LBS')) {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="12" fill="#ffffff" stroke="#0f172a" stroke-width="2"/>
                    <path d="M 0 0 L 0 -12 A 12 12 0 0 1 12 0 Z" fill="#0f172a"/>
                    <path d="M 0 0 L 0 12 A 12 12 0 0 1 -12 0 Z" fill="#0f172a"/>
                    <line x1="-12" y1="0" x2="12" y2="0" stroke="#0f172a" stroke-width="1.5"/>
                    <line x1="0" y1="-12" x2="0" y2="12" stroke="#0f172a" stroke-width="1.5"/>
                `;
                labelMarkup = `
                    <rect x="-36" y="-28" width="72" height="14" rx="3" fill="#f8fafc" stroke="#0f172a" stroke-width="1"/>
                    <text x="0" y="-18" fill="#0f172a" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace">LBS #${aId}</text>
                `;
            }
            // 5. RECLOSER Keypoint (Hourglass / Bowtie in Enclosure)
            else if (devRole === 'RECLOSER' || eqType.includes('RECLOSER')) {
                symbolMarkup = `
                    <rect x="-14" y="-12" width="28" height="24" rx="3" fill="#ffffff" stroke="#0f172a" stroke-width="2"/>
                    <polygon points="-8,-6 0,0 -8,6" fill="#0f172a"/>
                    <polygon points="8,-6 0,0 8,6" fill="#0f172a"/>
                `;
                labelMarkup = `
                    <rect x="-36" y="-28" width="72" height="14" rx="3" fill="#f8fafc" stroke="#0f172a" stroke-width="1"/>
                    <text x="0" y="-18" fill="#0f172a" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace">REC #${aId}</text>
                `;
            }
            // 6. BRANCH_NODE (Structural Junction)
            else if (topRole === 'BRANCH_NODE') {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="5" fill="#dc2626" stroke="#0f172a" stroke-width="1.5"/>
                `;
                labelMarkup = `
                    <text x="0" y="-10" fill="#dc2626" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #ffffff; stroke-width: 2.5px;">JCT #${aId}</text>
                `;
            }
            // 7. TERMINAL_NODE (Dead-End termination with red crossbar)
            else if (topRole === 'TERMINAL_NODE') {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="3" fill="#0f172a"/>
                    <line x1="0" y1="-9" x2="0" y2="9" stroke="#dc2626" stroke-width="3" stroke-linecap="round"/>
                `;
                labelMarkup = `
                    <text x="0" y="-12" fill="#dc2626" font-size="8" font-weight="bold" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #ffffff; stroke-width: 2.5px;">END #${aId}</text>
                `;
            }
            // 8. ISOLATED_NODE
            else if (topRole === 'ISOLATED_NODE') {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="8" fill="#f1f5f9" stroke="#94a3b8" stroke-dasharray="3,2" stroke-width="1.5"/>
                    <circle cx="0" cy="0" r="3" fill="#64748b"/>
                `;
                labelMarkup = `
                    <text x="0" y="20" fill="#64748b" font-size="8" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #ffffff; stroke-width: 2px;">ISO #${aId}</text>
                `;
            }
            // 9. LINE_POLE (Route-first subtle pass-through pole)
            else {
                extraClass += ' sld-pole-node';
                symbolMarkup = `
                    <circle cx="0" cy="0" r="2.5" fill="#0f172a" stroke="#64748b" stroke-width="0.8" class="sld-pole-dot"/>
                `;

                // Calculate candidate bounding boxes for pole label (#ID, 26x11px)
                const labelW = 26;
                const labelH = 11;

                const candAbove = {
                    x: pos.x - (labelW / 2),
                    y: pos.y - 15,
                    w: labelW,
                    h: labelH
                };

                const candBelow = {
                    x: pos.x - (labelW / 2),
                    y: pos.y + 5,
                    w: labelW,
                    h: labelH
                };

                const selfSym = `sym-${aId}`;
                let chosenLabelY = null;
                if (!this.occupancy.collides(candAbove, 2, selfSym)) {
                    chosenLabelY = -10;
                    this.occupancy.add({ id: `pole-lbl-${aId}`, type: 'POLE_LABEL', priority: 2, ...candAbove });
                } else if (!this.occupancy.collides(candBelow, 2, selfSym)) {
                    chosenLabelY = 15;
                    this.occupancy.add({ id: `pole-lbl-${aId}`, type: 'POLE_LABEL', priority: 2, ...candBelow });
                } else {
                    chosenLabelY = null;
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
                   aria-label="${node.name} (${topRole}, ${devRole})"
                   data-asset-id="${aId}"
                   data-topology-role="${topRole}"
                   data-device-role="${devRole}"
                   style="cursor: pointer;">
                    <title>${node.name} [ID: #${aId}]\nRole: ${topRole} | ${devRole}\nKonstruksi: ${eqType}\nStatus: ${node.operational_state}</title>
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

        drawer.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary">
                <h5 class="fw-bold m-0 text-info"><i class="fa-solid fa-microchip me-2"></i>Detail Aset Jaringan</h5>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('sld-asset-drawer').style.display='none';"></button>
            </div>

            <div class="mb-3">
                <span class="badge ${isSwitch ? 'bg-warning text-dark' : (isGtt ? 'bg-success' : 'bg-primary')} px-2 py-1 mb-2">
                    ${node.device_role}
                </span>
                <h4 class="fw-bold mb-1 text-white">${node.name}</h4>
                <div class="text-white-50 font-monospace small">ID: #${node.asset_id} | ${node.code}</div>
            </div>

            <div class="card bg-dark border-secondary mb-3">
                <div class="card-body p-3">
                    <div class="small text-muted text-uppercase fw-bold mb-1">Status Operasional</div>
                    <div class="fw-bold ${isSwitch ? 'text-warning' : 'text-success'}">
                        ${node.operational_state}
                    </div>
                    ${isSwitch ? '<div class="small text-white-50 mt-1"><i class="fa-solid fa-circle-info me-1"></i>Belum ada telemetri SCADA. Posisi fisik tidak diketahui sistem.</div>' : ''}
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
     * Fit Main Network (Zone 1: C15-01).
     */
    fitMainNetwork() {
        if (!this.layoutData) return;
        const zones = this.layoutData.zones || {};
        const main = zones.MAIN_NETWORK;

        if (main && main.bounding_box) {
            const p1 = this.project(main.bounding_box.min_x, main.bounding_box.min_y);
            const p2 = this.project(main.bounding_box.max_x, main.bounding_box.max_y);
            const pad = 120;
            this.viewBox = {
                x: p1.x - pad,
                y: p1.y - pad - 60, // allow space for GI anchor
                w: (p2.x - p1.x) + (pad * 2),
                h: (p2.y - p1.y) + (pad * 2) + 60,
            };
            this.updateViewBox();
        } else {
            this.resetView();
        }
    }

    /**
     * Fit All Zones (Main Network, Unconnected Fragments, Isolated Assets).
     */
    fitAll() {
        if (!this.layoutData) return;
        this.viewBox = Object.assign({}, this.fullBounds);
        this.updateViewBox();
    }

    /**
     * Reset View to Panoramic Main Trunk focus.
     */
    resetView() {
        this.viewBox = Object.assign({}, this.initialViewBox);
        this.updateViewBox();
    }

    updateViewBox() {
        if (this.svg) {
            this.svg.setAttribute('viewBox', `${this.viewBox.x} ${this.viewBox.y} ${this.viewBox.w} ${this.viewBox.h}`);
        }
        this.updateZoomClass();
    }

    /**
     * Progressive Zoom-Aware Density Management (Mandatory Amendment 6).
     * - Low Zoom (< 75%): Feeder route, GI, main trunk, branches, keypoint glyphs, GTTs, line terminations. Regular pole #IDs hidden.
     * - Medium Zoom (75% - 130%): Pole #IDs appear via collision resolver.
     * - High Zoom (> 130%): Full asset names, construction nomenclature, conductor span length pills.
     */
    updateZoomClass() {
        if (!this.svg) return;
        const currentZoomRatio = 2200 / this.viewBox.w;
        const zoomPercentage = Math.round(currentZoomRatio * 100);

        const zoomLabel = document.getElementById('sld-zoom-status');
        if (zoomLabel) {
            zoomLabel.textContent = `ZOOM: ${zoomPercentage}%`;
        }

        const labelLayer = document.getElementById('sld-edge-labels-layer');
        const poleLabels = this.svg.querySelectorAll('.sld-pole-label');

        if (currentZoomRatio < 0.75) {
            // LOW ZOOM (< 75%): Operator traces high-level route. Pole numbers suppressed.
            if (labelLayer) labelLayer.style.display = 'none';
            poleLabels.forEach(p => p.style.display = 'none');
        } else if (currentZoomRatio < 1.30) {
            // MEDIUM ZOOM (75% - 130%): Pole IDs visible via occupancy index.
            if (labelLayer) labelLayer.style.display = 'inline';
            poleLabels.forEach(p => p.style.display = 'inline');
        } else {
            // HIGH ZOOM (> 130%): Maximum granular visibility.
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
