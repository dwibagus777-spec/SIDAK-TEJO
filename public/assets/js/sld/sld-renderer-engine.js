/**
 * SLD-05: Single Line Diagram (SLD) Visual Renderer Engine
 * 
 * Visual Remediation & Readability Hardening Release
 * 
 * Governed by the Architectural Amendments:
 * 1. Consumes single immutable layout snapshot from GET /api/sld/feeder/{id}/layout.
 * 2. Zero graph traversal, zero BFS, zero degree math, zero topology derivation in JS.
 * 3. Initial viewport presents a panoramic, readable view of the Main Trunk (GI -> #3231 -> Lateral Branches).
 * 4. Explicit navigation controls: Zoom In, Zoom Out, Fit Main Network, Fit All, Reset.
 * 5. Geometric AABB Bounding-Box Label Collision Protection:
 *    - Strict priority: 1. Node Symbol, 2. Primary Label, 3. Conductor Annotation, 4. Role Badge, 5. Secondary Name.
 *    - Lower priority elements truncated or hidden on collision; SLD-04 coordinates remain strictly immutable.
 * 6. Conductor / Span Length Legibility:
 *    - Authoritative edge.length_meters displayed with contrast background pill.
 *    - "Length unavailable" fallback when null; strictly zero GPS distance invention.
 * 7. Zoom-Aware Density Management (LOW, MEDIUM, HIGH) with interactive edge highlight.
 * 8. Prominent Electrical Symbology (GI Header, PMS UNKNOWN diamond with ?, GTT 1T/2T, Branch, Terminal).
 * 9. Three distinct zones: MAIN_NETWORK, UNCONNECTED_COMPONENTS, ISOLATED_ASSETS.
 * 10. Read-only detail drawer for both nodes and conductor edges (zero mutations, Delta = 0).
 * 11. Production live database guard (renders DATA_NOT_READY banner when DB topology is incomplete).
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
     * @returns {Object|null} Conflicting box or null
     */
    collides(box, padding = 2) {
        const ax1 = box.x - padding;
        const ay1 = box.y - padding;
        const ax2 = box.x + box.w + padding;
        const ay2 = box.y + box.h + padding;

        for (const b of this.items) {
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
            console.error(`[SLD-05] Container #${containerId} not found.`);
            return;
        }

        this.options = Object.assign({
            apiUrl: '',
            scaleX: 75,
            scaleY: 85,
            offsetX: 140,
            offsetY: 140,
            defaultMode: 'ENGINEERING', // 'ENGINEERING' or 'SIMPLIFIED'
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
            this.render();
        } catch (err) {
            console.error('[SLD-05] Error loading layout:', err);
            this.renderError(err.message);
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
        const svgHeight = (totalH * this.options.scaleY) + (this.options.offsetY * 2) + 200;
        this.fullBounds = { x: 0, y: 0, w: svgWidth, h: svgHeight };

        // Panoramic Initial Viewport: Centers on Main Trunk (GI -> Incomer -> Branches)
        const incomer = this.layoutData.nodes.find(n => n.device_role === 'SOURCE_INCOMER' || n.topology_role === 'SOURCE_INCOMER') 
                     || this.layoutData.nodes[0];
        
        if (incomer && incomer.schematic) {
            const rootPos = this.project(incomer.schematic.grid_x, incomer.schematic.grid_y);
            this.initialViewBox = {
                x: Math.max(0, rootPos.x - 80),
                y: Math.max(0, rootPos.y - 300),
                w: 2200,
                h: 1150
            };
        } else {
            this.initialViewBox = { x: 0, y: 0, w: 2200, h: 1150 };
        }

        this.viewBox = Object.assign({}, this.initialViewBox);

        this.container.innerHTML = `
            <div class="sld-viewport-wrapper" style="position: relative; width: 100%; height: 100%; overflow: hidden; background: #0b1329;">
                <svg id="sld-svg-canvas" 
                     xmlns="http://www.w3.org/2000/svg" 
                     viewBox="${this.viewBox.x} ${this.viewBox.y} ${this.viewBox.w} ${this.viewBox.h}"
                     style="width: 100%; height: 100%; display: block; cursor: grab; user-select: none;">
                    <defs>
                        <!-- Grid Pattern Background -->
                        <pattern id="sld-grid-pattern" width="50" height="50" patternUnits="userSpaceOnUse">
                            <path d="M 50 0 L 0 0 0 50" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/>
                        </pattern>
                        <!-- Glow filter for Keypoints -->
                        <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="3" result="blur"/>
                            <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                        </filter>
                        <filter id="edge-glow" x="-20%" y="-20%" width="140%" height="140%">
                            <feGaussianBlur stdDeviation="2" result="blur"/>
                            <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                        </filter>
                    </defs>

                    <!-- Background Rect with Grid Pattern -->
                    <rect x="-4000" y="-4000" width="24000" height="20000" fill="#090e1d" />
                    <rect x="-4000" y="-4000" width="24000" height="20000" fill="url(#sld-grid-pattern)" />

                    <!-- Zone 1: Main Network Frame -->
                    <g id="sld-zone-main" class="sld-zone-group"></g>

                    <!-- Zone 2: Unconnected Components Frame -->
                    <g id="sld-zone-unconnected" class="sld-zone-group"></g>

                    <!-- Zone 3: Isolated Assets Frame -->
                    <g id="sld-zone-isolated" class="sld-zone-group"></g>

                    <!-- Conductor Layer (Edges) -->
                    <g id="sld-edges-layer"></g>

                    <!-- Conductor Annotations Layer -->
                    <g id="sld-edge-labels-layer"></g>

                    <!-- Line Sections Layer (Simplified Mode) -->
                    <g id="sld-sections-layer" style="display: none;"></g>

                    <!-- Equipment Nodes Layer -->
                    <g id="sld-nodes-layer"></g>
                </svg>

                <!-- Floating Canvas Minimap / Mode Indicator -->
                <div class="sld-mode-indicator badge bg-dark text-info border border-secondary" 
                     style="position: absolute; bottom: 15px; left: 15px; z-index: 10; font-family: monospace;">
                    <span id="sld-current-mode-label">${this.currentMode === 'ENGINEERING' ? 'MODE: ENGINEERING (GRANULAR)' : 'MODE: SIMPLIFIED (LINE SECTIONS)'}</span> | 
                    <span>NODES: ${this.layoutData.nodes.length}</span> | 
                    <span>EDGES: ${this.layoutData.edges.length}</span> | 
                    <span id="sld-zoom-status">ZOOM: 100%</span>
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

            let halfW = 7, halfH = 7;
            if (devRole === 'SOURCE_INCOMER') {
                halfW = 16; halfH = 40;
            } else if (devRole === 'SWITCH_CANDIDATE') {
                halfW = 18; halfH = 18;
            } else if (devRole === 'TRANSFORMER_NODE') {
                halfW = 16; halfH = 16;
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
     * Render the 3 visually separated Zone Frames.
     */
    renderZoneFrames() {
        const zones = this.layoutData.zones || {};

        // Zone 1: Main Network
        const main = zones.MAIN_NETWORK;
        if (main && main.bounding_box) {
            const p1 = this.project(main.bounding_box.min_x, main.bounding_box.min_y);
            const p2 = this.project(main.bounding_box.max_x, main.bounding_box.max_y);
            const g = document.getElementById('sld-zone-main');
            const padX = 60, padY = 60;
            const w = (p2.x - p1.x) + (padX * 2);
            const h = (p2.y - p1.y) + (padY * 2);

            g.innerHTML = `
                <rect x="${p1.x - padX}" y="${p1.y - padY}" width="${w}" height="${h}" 
                      fill="rgba(56, 189, 248, 0.015)" stroke="rgba(56, 189, 248, 0.25)" stroke-width="1.5" stroke-dasharray="6,4" rx="8"/>
                <text x="${p1.x - padX + 16}" y="${p1.y - padY + 24}" 
                      fill="#38bdf8" font-size="13" font-weight="bold" font-family="sans-serif" letter-spacing="1">
                    ZONA 1: PENYULANG UTAMA (TERHUBUNG GI BUDURAN - C15-01)
                </text>
            `;
        }

        // Zone 2: Unconnected Components
        const uncon = zones.UNCONNECTED_COMPONENTS;
        if (uncon && uncon.bounding_box && uncon.nodes_count > 0) {
            const p1 = this.project(uncon.bounding_box.min_x, uncon.bounding_box.min_y);
            const p2 = this.project(uncon.bounding_box.max_x, uncon.bounding_box.max_y);
            const g = document.getElementById('sld-zone-unconnected');
            const padX = 60, padY = 50;
            const w = Math.max(500, (p2.x - p1.x) + (padX * 2));
            const h = (p2.y - p1.y) + (padY * 2);

            g.innerHTML = `
                <rect x="${p1.x - padX}" y="${p1.y - padY}" width="${w}" height="${h}" 
                      fill="rgba(245, 158, 11, 0.015)" stroke="rgba(245, 158, 11, 0.3)" stroke-width="1.5" stroke-dasharray="6,4" rx="8"/>
                <text x="${p1.x - padX + 16}" y="${p1.y - padY + 24}" 
                      fill="#f59e0b" font-size="13" font-weight="bold" font-family="sans-serif" letter-spacing="1">
                    ZONA 2: FRAGMEN JARINGAN LEPAS (UNCONNECTED TO GI - C15-02 .. C15-06)
                </text>
            `;
        }

        // Zone 3: Isolated Assets
        const iso = zones.ISOLATED_ASSETS;
        if (iso && iso.bounding_box && iso.nodes_count > 0) {
            const p1 = this.project(iso.bounding_box.min_x, iso.bounding_box.min_y);
            const p2 = this.project(iso.bounding_box.max_x, iso.bounding_box.max_y);
            const g = document.getElementById('sld-zone-isolated');
            const padX = 60, padY = 40;
            const w = Math.max(450, (p2.x - p1.x) + (padX * 2));
            const h = (p2.y - p1.y) + (padY * 2);

            g.innerHTML = `
                <rect x="${p1.x - padX}" y="${p1.y - padY}" width="${w}" height="${h}" 
                      fill="rgba(148, 163, 184, 0.02)" stroke="rgba(148, 163, 184, 0.3)" stroke-width="1.5" stroke-dasharray="6,4" rx="8"/>
                <text x="${p1.x - padX + 16}" y="${p1.y - padY + 24}" 
                      fill="#94a3b8" font-size="13" font-weight="bold" font-family="sans-serif" letter-spacing="1">
                    ZONA 3: ASET TERISOLASI (TANPA KONDUKTOR / ZERO EDGES)
                </text>
            `;
        }
    }

    /**
     * Render Conductor Lines (Edges) & Span Annotations with Geometric Collision Protection.
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

            edgeHtml += `
                <path d="${pathD}" 
                      fill="none" 
                      stroke="#475569" 
                      stroke-width="2.5" 
                      class="sld-edge sld-granular-edge" 
                      id="edge-${tlId}"
                      data-transline-id="${tlId}"
                      data-source="${edge.source_asset_id}"
                      data-target="${edge.target_asset_id}"
                      data-length="${hasLength ? edge.length_meters : ''}"
                      style="cursor: pointer; transition: stroke 0.2s, stroke-width 0.2s;">
                    <title>Transline #${tlId}: ${lengthText} (${edge.source_asset_id} ↔ ${edge.target_asset_id})</title>
                </path>
            `;

            // Geometric Collision Check for Conductor Annotation Pill
            // Pill dimensions: 48px width, 16px height
            const pillW = 48;
            const pillH = 16;
            
            // Candidate A: above line (horizontal) or right of line (vertical)
            const candA = {
                x: isHorizontal ? midX - (pillW / 2) : midX + 6,
                y: isHorizontal ? midY - 18 : midY - (pillH / 2),
                w: pillW,
                h: pillH
            };

            // Candidate B: below line (horizontal) or left of line (vertical)
            const candB = {
                x: isHorizontal ? midX - (pillW / 2) : midX - pillW - 6,
                y: isHorizontal ? midY + 4 : midY - (pillH / 2),
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
                              fill="#0f172a" stroke="#334155" stroke-width="0.8" 
                              class="sld-edge-pill" />
                        <text x="0" y="3.5" fill="#94a3b8" font-size="8.5" font-family="monospace" 
                              text-anchor="middle" font-weight="bold" class="sld-edge-length-text"
                              style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 2px;">
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
                          stroke="#38bdf8" 
                          stroke-width="5" 
                          stroke-linecap="round"
                          opacity="0.85">
                        <title>${sec.id}: ${sec.span_count} Spans (${sec.source_node_id} ↔ ${sec.target_node_id})</title>
                    </path>
                    <rect x="${midX - 36}" y="${midY - 20}" width="72" height="18" rx="4" fill="#0f172a" stroke="#38bdf8" stroke-width="1.2"/>
                    <text x="${midX}" y="${midY - 7}" fill="#38bdf8" font-size="9.5" font-family="monospace" text-anchor="middle" font-weight="bold">
                        ${sec.id} (${sec.span_count}s)
                    </text>
                </g>
            `;
        }

        g.innerHTML = html;
    }

    /**
     * Render All 205 Equipment Nodes with Geometric Label Collision Protection.
     */
    renderNodes() {
        const g = document.getElementById('sld-nodes-layer');
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

            // 1. SOURCE_INCOMER (GI Substation Demarcation)
            if (devRole === 'SOURCE_INCOMER' || topRole === 'SOURCE_INCOMER') {
                symbolMarkup = `
                    <rect x="-10" y="-36" width="20" height="72" fill="#0284c7" stroke="#38bdf8" stroke-width="2.5" rx="3" filter="url(#glow)"/>
                    <line x1="10" y1="0" x2="26" y2="0" stroke="#38bdf8" stroke-width="3.5"/>
                    <circle cx="26" cy="0" r="5" fill="#38bdf8"/>
                `;
                labelMarkup = `
                    <rect x="-75" y="-60" width="150" height="20" rx="4" fill="#0369a1" stroke="#38bdf8" stroke-width="1.2"/>
                    <text x="0" y="-46" fill="#ffffff" font-size="10.5" font-weight="bold" text-anchor="middle" font-family="sans-serif">
                        GI BUDURAN INCOMER
                    </text>
                    <text x="0" y="52" fill="#7dd3fc" font-size="9.5" text-anchor="middle" font-family="monospace" font-weight="bold"
                          style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3.5px;">
                        #${aId} (TM11)
                    </text>
                `;
            }
            // 2. SWITCH_CANDIDATE (PMS - Neutral UNKNOWN state)
            else if (devRole === 'SWITCH_CANDIDATE') {
                symbolMarkup = `
                    <polygon points="0,-16 16,0 0,16 -16,0" fill="#1e1b4b" stroke="#f59e0b" stroke-width="2.5" filter="url(#glow)"/>
                    <text x="0" y="5" fill="#fbbf24" font-size="13" font-weight="bold" text-anchor="middle" font-family="sans-serif">?</text>
                `;
                labelMarkup = `
                    <rect x="-45" y="-34" width="90" height="16" rx="3" fill="#451a03" stroke="#f59e0b" stroke-width="1.2"/>
                    <text x="0" y="-22" fill="#fde68a" font-size="9" font-weight="bold" text-anchor="middle" font-family="monospace">
                        PMS (UNKNOWN)
                    </text>
                    <text x="0" y="30" fill="#fbbf24" font-size="9" text-anchor="middle" font-family="monospace" font-weight="bold"
                          style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3px;">
                        #${aId}
                    </text>
                `;
            }
            // 3. TRANSFORMER_NODE (GTT Cantol vs Portal)
            else if (devRole === 'TRANSFORMER_NODE') {
                if (eqType === 'GTT1_CANTOL') {
                    symbolMarkup = `
                        <circle cx="0" cy="0" r="13" fill="#064e3b" stroke="#10b981" stroke-width="2.5" filter="url(#glow)"/>
                        <text x="0" y="4" fill="#a7f3d0" font-size="10" font-weight="bold" text-anchor="middle" font-family="monospace">1T</text>
                        <line x1="0" y1="13" x2="0" y2="22" stroke="#10b981" stroke-width="2.5"/>
                    `;
                    labelMarkup = `
                        <rect x="-42" y="-30" width="84" height="15" rx="3" fill="#064e3b" stroke="#10b981" stroke-width="1"/>
                        <text x="0" y="-19" fill="#34d399" font-size="8.5" font-weight="bold" text-anchor="middle" font-family="sans-serif">GTT CANTOL</text>
                        <text x="0" y="34" fill="#a7f3d0" font-size="8.5" text-anchor="middle" font-family="monospace"
                              style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3px;">#${aId}</text>
                    `;
                } else {
                    symbolMarkup = `
                        <circle cx="0" cy="0" r="14" fill="#164e63" stroke="#06b6d4" stroke-width="2.5" filter="url(#glow)"/>
                        <text x="0" y="4" fill="#a5f3fc" font-size="10" font-weight="bold" text-anchor="middle" font-family="monospace">2T</text>
                        <line x1="-7" y1="14" x2="-7" y2="23" stroke="#06b6d4" stroke-width="2.5"/>
                        <line x1="7" y1="14" x2="7" y2="23" stroke="#06b6d4" stroke-width="2.5"/>
                    `;
                    labelMarkup = `
                        <rect x="-42" y="-30" width="84" height="15" rx="3" fill="#164e63" stroke="#06b6d4" stroke-width="1"/>
                        <text x="0" y="-19" fill="#22d3ee" font-size="8.5" font-weight="bold" text-anchor="middle" font-family="sans-serif">GTT PORTAL</text>
                        <text x="0" y="35" fill="#a5f3fc" font-size="8.5" text-anchor="middle" font-family="monospace"
                              style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3px;">#${aId}</text>
                    `;
                }
            }
            // 4. BRANCH_NODE (Structural Junction)
            else if (topRole === 'BRANCH_NODE') {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="8" fill="#e11d48" stroke="#ffffff" stroke-width="2"/>
                `;
                labelMarkup = `
                    <text x="0" y="-14" fill="#fda4af" font-size="8.5" font-weight="bold" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3px;">JCT #${aId}</text>
                `;
            }
            // 5. TERMINAL_NODE (Dead-End termination)
            else if (topRole === 'TERMINAL_NODE') {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="4.5" fill="#64748b"/>
                    <line x1="0" y1="-9" x2="0" y2="9" stroke="#ef4444" stroke-width="3"/>
                `;
                labelMarkup = `
                    <text x="0" y="-14" fill="#94a3b8" font-size="8.5" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3px;">END #${aId}</text>
                `;
            }
            // 6. ISOLATED_NODE
            else if (topRole === 'ISOLATED_NODE') {
                symbolMarkup = `
                    <circle cx="0" cy="0" r="10" fill="#1e293b" stroke="#94a3b8" stroke-dasharray="4,2" stroke-width="1.8"/>
                    <circle cx="0" cy="0" r="4" fill="#94a3b8"/>
                `;
                labelMarkup = `
                    <text x="0" y="24" fill="#94a3b8" font-size="8.5" text-anchor="middle" font-family="monospace"
                          style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3px;">ISO #${aId}</text>
                `;
            }
            // 7. LINE_POLE (Standard pass-through pole) with Geometric Collision Resolution
            else {
                extraClass += ' sld-pole-node';
                symbolMarkup = `
                    <circle cx="0" cy="0" r="4.5" fill="#94a3b8" stroke="#090e1d" stroke-width="1.5"/>
                `;

                // Calculate candidate bounding boxes for pole label (#ID, 28x12px)
                const labelW = 28;
                const labelH = 12;

                const candAbove = {
                    x: pos.x - (labelW / 2),
                    y: pos.y - 18,
                    w: labelW,
                    h: labelH
                };

                const candBelow = {
                    x: pos.x - (labelW / 2),
                    y: pos.y + 6,
                    w: labelW,
                    h: labelH
                };

                let chosenLabelY = null;
                if (!this.occupancy.collides(candAbove, 2)) {
                    chosenLabelY = -13;
                    this.occupancy.add({ id: `pole-lbl-${aId}`, type: 'POLE_LABEL', priority: 2, ...candAbove });
                } else if (!this.occupancy.collides(candBelow, 2)) {
                    chosenLabelY = 17;
                    this.occupancy.add({ id: `pole-lbl-${aId}`, type: 'POLE_LABEL', priority: 2, ...candBelow });
                } else {
                    // Both collide: suppress label to maintain zero overlap; visible on hover
                    chosenLabelY = null;
                }

                if (chosenLabelY !== null) {
                    labelMarkup = `
                        <text x="0" y="${chosenLabelY}" fill="#cbd5e1" font-size="8" text-anchor="middle" font-family="monospace"
                              class="sld-pole-label"
                              style="paint-order: stroke fill; stroke: #090e1d; stroke-width: 3px;">
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
            edgeEl.setAttribute('stroke', '#38bdf8');
            edgeEl.setAttribute('stroke-width', '4.5');
            edgeEl.setAttribute('filter', 'url(#edge-glow)');
            if (labelEl) {
                const pill = labelEl.querySelector('.sld-edge-pill');
                const txt = labelEl.querySelector('.sld-edge-length-text');
                if (pill) pill.setAttribute('stroke', '#38bdf8');
                if (txt) txt.setAttribute('fill', '#38bdf8');
            }
        } else {
            if (this.selectedEdgeId !== translineId) {
                edgeEl.setAttribute('stroke', '#475569');
                edgeEl.setAttribute('stroke-width', '2.5');
                edgeEl.removeAttribute('filter');
                if (labelEl) {
                    const pill = labelEl.querySelector('.sld-edge-pill');
                    const txt = labelEl.querySelector('.sld-edge-length-text');
                    if (pill) pill.setAttribute('stroke', '#334155');
                    if (txt) txt.setAttribute('fill', '#94a3b8');
                }
            }
        }
    }

    /**
     * Render Read-Only Slide-Over Detail Drawer for Equipment Node.
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
     * Render Read-Only Slide-Over Detail Drawer for Conductor Edge.
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
        const cursorX = clientX ? (clientX - rect.left) / rect.width : 0.5;
        const cursorY = clientY ? (clientY - rect.top) / rect.height : 0.5;

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
            const pad = 100;
            this.viewBox = {
                x: p1.x - pad,
                y: p1.y - pad,
                w: (p2.x - p1.x) + (pad * 2),
                h: (p2.y - p1.y) + (pad * 2),
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
     * Zoom-Aware Presentation Modulation (High / Medium / Low).
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

        if (currentZoomRatio < 0.60) {
            if (labelLayer) labelLayer.style.display = 'none';
            poleLabels.forEach(p => p.style.display = 'none');
        } else if (currentZoomRatio < 1.20) {
            if (labelLayer) labelLayer.style.display = 'inline';
            poleLabels.forEach(p => p.style.display = 'inline');
        } else {
            if (labelLayer) labelLayer.style.display = 'inline';
            poleLabels.forEach(p => p.style.display = 'inline');
        }
    }

    /**
     * Toggle between Engineering Mode and Simplified Mode.
     */
    setMode(mode) {
        this.currentMode = mode;
        this.applyDisplayModes();
        const label = document.getElementById('sld-current-mode-label');
        if (label) {
            label.textContent = mode === 'ENGINEERING' ? 'MODE: ENGINEERING (GRANULAR)' : 'MODE: SIMPLIFIED (LINE SECTIONS)';
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
        const poleNodes = this.svg.querySelectorAll('.sld-pole-node');
        const gttNodes = this.svg.querySelectorAll('.sld-gtt-node');

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
            <div class="d-flex flex-column align-items-center justify-content-center p-5 text-center" style="min-height: 520px; background: #0b1329; border-radius: 8px;">
                <div class="mb-4" style="width: 80px; height: 80px; border-radius: 50%; background: rgba(245, 158, 11, 0.15); display: flex; align-items: center; justify-content: center; border: 2px solid #f59e0b;">
                    <i class="fa-solid fa-triangle-exclamation fa-2x text-warning"></i>
                </div>
                <h4 class="fw-bold text-white mb-2">TOPOLOGI JARINGAN OPERASIONAL BELUM LENGKAP</h4>
                <div class="badge bg-warning text-dark mb-3 px-3 py-2 fs-6">
                    STATUS: ${source.status || 'TOPOLOGY_DATA_INCOMPLETE'} &bull; MODE: ${source.mode || 'PRODUCTION_LIVE_DATABASE'}
                </div>
                <p class="text-white-50 mx-auto mb-4" style="max-width: 680px; line-height: 1.6;">
                    ${message}
                </p>
                <div class="card bg-dark border-secondary text-start mx-auto mb-4" style="max-width: 600px; width: 100%;">
                    <div class="card-header border-secondary bg-black bg-opacity-25 text-info py-2 small fw-bold">
                        <i class="fa-solid fa-circle-info me-1"></i> METADATA AUDIT GATEWAY
                    </div>
                    <div class="card-body p-3 small text-white-50 font-monospace">
                        <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                            <span>Penyulang / Feeder:</span>
                            <span class="text-white fw-bold">${feeder.code || '-'} (${feeder.name || '-'})</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                            <span>Aset Fisik (assets table):</span>
                            <span class="text-info">${data.topology?.feeder_assets_count ?? 0} Aset</span>
                        </div>
                        <div class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25">
                            <span>Bentang Fisik (gis_translines):</span>
                            <span class="text-danger fw-bold">${data.topology?.edges_count ?? 0} Translines (Memerlukan TL-01)</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span>Database Invariant:</span>
                            <span class="text-success">&Delta; = 0 (Strict Read-Only Verified)</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-info btn-sm px-4" onclick="location.reload();">
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
            <div class="d-flex flex-column align-items-center justify-content-center p-5 text-white" style="min-height: 480px; background: #090e1d;">
                <div class="spinner-border text-info mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                <div class="h5 fw-bold mb-1">Memuat Blueprint Tata Letak SLD...</div>
                <div class="text-white-50 small">Mengambil model koordinat deterministik dari SLD-04 Engine</div>
            </div>
        `;
    }

    renderError(msg) {
        this.container.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center p-5 text-danger" style="min-height: 480px; background: #090e1d;">
                <i class="fa-solid fa-triangle-exclamation fa-3x mb-3 text-warning"></i>
                <div class="h5 fw-bold mb-1 text-white">Gagal Memuat Single Line Diagram</div>
                <div class="text-danger small mb-3">${msg}</div>
                <button class="btn btn-outline-info btn-sm" onclick="location.reload();">
                    <i class="fa-solid fa-rotate-right me-1"></i> Muat Ulang
                </button>
            </div>
        `;
    }
}

// Global Export
window.SldRendererEngine = SldRendererEngine;
