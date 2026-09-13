<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Enterprise Monitoring Center PLN<?= $this->endSection() ?>
<?= $this->section('page_title') ?>SIDAK TEJO Enterprise Monitoring Center PLN<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Scoped Modern Bento Design System — SIDAK TEJO Enterprise */
    .emc-container, .sidak-bento-container {
        font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    /* Bento Cards Baseline */
    .sidak-bento-card, .emc-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    /* Section A: Welcome Banner */
    .sidak-bento-welcome {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15) !important;
    }

    /* Section B: Quick Action Bar */
    .sidak-bento-action-bar, .quick-action-bar-emc {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 24px;
    }

    .sidak-bento-action-pill, .quick-emc-btn {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 10px 18px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        font-size: 13px;
        color: #1e293b !important;
        text-decoration: none !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .sidak-bento-action-pill:hover, .quick-emc-btn:hover {
        transform: translateY(-2px);
        border-color: #00B5B8;
        color: #00B5B8 !important;
        box-shadow: 0 6px 16px rgba(0, 181, 184, 0.15);
    }

    /* Section C: KPI Bento Grid */
    .sidak-bento-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }

    @media (max-width: 1199.98px) {
        .sidak-bento-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 575.98px) {
        .sidak-bento-kpi-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
    }

    @media (max-width: 340px) {
        .sidak-bento-kpi-grid {
            grid-template-columns: 1fr;
        }
    }

    .sidak-bento-kpi-card {
        padding: 20px;
        border-radius: 18px;
        position: relative;
        overflow: hidden;
        text-decoration: none !important;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 130px;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
    }

    .sidak-bento-kpi-card:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08) !important;
    }

    .sidak-bento-val {
        font-size: 34px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -1px;
        font-feature-settings: "tnum";
        font-variant-numeric: tabular-nums;
    }

    .sidak-bento-lbl {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .sidak-bento-sub {
        font-size: 11px;
        font-weight: 600;
        margin-top: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Soft Pastel Executive Tints — Prototype Faithful */
    .sidak-bento-kpi-primary {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border: 1px solid #a7f3d0;
        color: #065f46;
    }
    .sidak-bento-kpi-primary .sidak-bento-val { color: #047857; }
    .sidak-bento-kpi-primary .sidak-bento-lbl { color: #065f46; }
    .sidak-bento-kpi-primary .sidak-bento-sub { color: #047857; }

    .sidak-bento-kpi-danger {
        background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
        border: 1px solid #fecdd3;
        color: #9f1239;
    }
    .sidak-bento-kpi-danger .sidak-bento-val { color: #be123c; }
    .sidak-bento-kpi-danger .sidak-bento-lbl { color: #9f1239; }
    .sidak-bento-kpi-danger .sidak-bento-sub { color: #be123c; }

    .sidak-bento-kpi-warning {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        border: 1px solid #fde68a;
        color: #92400e;
    }
    .sidak-bento-kpi-warning .sidak-bento-val { color: #b45309; }
    .sidak-bento-kpi-warning .sidak-bento-lbl { color: #92400e; }
    .sidak-bento-kpi-warning .sidak-bento-sub { color: #b45309; }

    .sidak-bento-kpi-info {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 1px solid #bae6fd;
        color: #075985;
    }
    .sidak-bento-kpi-info .sidak-bento-val { color: #0284c7; }
    .sidak-bento-kpi-info .sidak-bento-lbl { color: #075985; }
    .sidak-bento-kpi-info .sidak-bento-sub { color: #0284c7; }

    .sidak-bento-kpi-dark {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #cbd5e1;
        color: #334155;
    }
    .sidak-bento-kpi-dark .sidak-bento-val { color: #1e293b; }
    .sidak-bento-kpi-dark .sidak-bento-lbl { color: #334155; }
    .sidak-bento-kpi-dark .sidak-bento-sub { color: #475569; }

    .sidak-bento-kpi-cyan {
        background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
        border: 1px solid #99f6e4;
        color: #115e59;
    }
    .sidak-bento-kpi-cyan .sidak-bento-val { color: #0f766e; }
    .sidak-bento-kpi-cyan .sidak-bento-lbl { color: #115e59; }
    .sidak-bento-kpi-cyan .sidak-bento-sub { color: #0f766e; }

    .sidak-bento-kpi-success {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 1px solid #86efac;
        color: #14532d;
    }
    .sidak-bento-kpi-success .sidak-bento-val { color: #15803d; }
    .sidak-bento-kpi-success .sidak-bento-lbl { color: #14532d; }
    .sidak-bento-kpi-success .sidak-bento-sub { color: #15803d; }

    .sidak-bento-kpi-target {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #0f172a;
    }

    /* Section D: Mini GIS & Operational Feed */
    .sidak-bento-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .sidak-bento-feed-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }

    .sidak-bento-feed-item:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        transform: translateX(3px);
    }

    /* Section E: SLA Cards */
    .sidak-bento-sla-card {
        padding: 16px;
        border-radius: 14px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none !important;
        display: block;
        height: 100%;
    }

    .sidak-bento-sla-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
    }

    /* Section F: Executive CTA Card */
    .sidak-bento-cta-card {
        background: linear-gradient(135deg, #0c4a6e 0%, #075985 50%, #0369a1 100%);
        border: 1px solid rgba(56, 189, 248, 0.3);
        color: #ffffff;
        border-radius: 18px;
        padding: 24px;
        position: relative;
        overflow: hidden;
    }

    .sidak-bento-cta-btn {
        background: #ffffff;
        color: #0369a1 !important;
        font-weight: 700;
        font-size: 13px;
        border-radius: 12px;
        padding: 10px 20px;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none !important;
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .sidak-bento-cta-btn:hover {
        background: #f0f9ff;
        color: #0284c7 !important;
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.25);
    }

    /* Responsive Matrix Scaling */
    @media (max-width: 575.98px) {
        .sidak-bento-kpi-card {
            padding: 14px 12px;
            min-height: 105px;
            border-radius: 14px;
        }
        .sidak-bento-val {
            font-size: 22px;
        }
        .sidak-bento-lbl {
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .sidak-bento-sub {
            font-size: 10px;
        }
        .sidak-bento-action-pill {
            padding: 8px 12px;
            font-size: 12px;
            gap: 6px;
        }
        #emc-mini-map {
            height: 250px !important;
        }
    }

    @media (min-width: 768px) and (max-width: 1024px) {
        .sidak-bento-kpi-card {
            padding: 16px 14px;
            min-height: 120px;
        }
        .sidak-bento-val {
            font-size: 28px;
        }
        #emc-mini-map {
            height: 280px !important;
        }
    }

    /* Zero Horizontal Overflow Enforcer */
    html, body {
        overflow-x: hidden;
        max-width: 100vw;
    }

    .sidak-bento-container {
        max-width: 100%;
        overflow-x: hidden;
    }

    /* Phase 2E: Constellation Mission Control & Contextual Dashboard Scoped Styles */
    .constellation-canvas-card {
        background: #fcfcfd;
        background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px);
        background-size: 24px 24px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02);
        position: relative;
        overflow: hidden;
        min-height: 520px;
        display: flex;
        flex-direction: column;
    }

    .constellation-canvas-body {
        position: relative;
        flex: 1;
        min-height: 420px;
        overflow: hidden;
    }

    .constellation-svg-network {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1;
    }

    .constellation-svg-line {
        stroke: #cbd5e1;
        stroke-width: 1.5;
        stroke-dasharray: 4, 4;
        transition: stroke 0.3s ease, stroke-width 0.3s ease;
    }

    .constellation-central-node {
        position: absolute;
        z-index: 10;
        transform: translate(-50%, -50%);
        cursor: pointer;
        text-decoration: none !important;
    }

    .constellation-central-halo {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, rgba(16, 185, 129, 0.08) 55%, transparent 72%);
        transform: translate(-50%, -50%);
        pointer-events: none;
        animation: constellation-halo-pulse 3s infinite ease-in-out;
    }

    @keyframes constellation-halo-pulse {
        0%, 100% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 0.85;
        }
        50% {
            transform: translate(-50%, -50%) scale(1.15);
            opacity: 0.45;
        }
    }

    .constellation-central-ring {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #ffffff;
        border: 3px solid #10b981;
        box-shadow: 0 0 24px rgba(16, 185, 129, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 800;
        color: #047857;
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        z-index: 2;
    }

    .constellation-central-node:hover .constellation-central-ring {
        transform: scale(1.1);
        box-shadow: 0 0 32px rgba(16, 185, 129, 0.6);
        border-color: #059669;
    }

    .constellation-central-pill {
        position: absolute;
        left: 60px;
        top: 50%;
        transform: translateY(-50%);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 9999px;
        padding: 6px 14px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        text-decoration: none !important;
        transition: all 0.2s ease;
        z-index: 2;
    }

    .constellation-central-node:hover .constellation-central-pill {
        transform: translateY(-50%) translateX(2px);
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12);
        border-color: #10b981;
    }

    .constellation-node {
        position: absolute;
        transform: translate(-50%, -50%);
        z-index: 8;
        text-decoration: none !important;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .constellation-node:hover {
        transform: translate(-50%, -50%) scale(1.12);
        z-index: 12;
    }

    .constellation-node-aura {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 64px;
        height: 64px;
        border-radius: 50%;
        transform: translate(-50%, -50%);
        pointer-events: none;
        filter: blur(8px);
        opacity: 0.55;
        transition: opacity 0.2s ease;
    }

    .constellation-node:hover .constellation-node-aura {
        opacity: 0.9;
    }

    .constellation-node-disc {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #ffffff;
        border: 2px solid #cbd5e1;
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        position: relative;
        z-index: 2;
        transition: all 0.2s ease;
    }

    .constellation-node-badge {
        position: absolute;
        top: -6px;
        right: -8px;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 9999px;
        line-height: 1;
        z-index: 3;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    }

    .constellation-node-label {
        font-size: 11px;
        font-weight: 600;
        color: #475569;
        margin-top: 5px;
        white-space: nowrap;
        background: rgba(255, 255, 255, 0.85);
        padding: 2px 8px;
        border-radius: 6px;
        border: 1px solid rgba(226, 232, 240, 0.6);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .constellation-node-pill {
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 700;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        white-space: nowrap;
    }

    .contextual-dashboard-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02);
    }

    .contextual-kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .contextual-kpi-card {
        padding: 16px;
        border-radius: 16px;
        text-decoration: none !important;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 105px;
        transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s ease;
    }

    .contextual-kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }

    .contextual-kpi-val {
        font-size: 26px;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.5px;
    }

    .contextual-kpi-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-top: 8px;
    }

    .contextual-kpi-sub {
        font-size: 11px;
        opacity: 0.8;
    }

    .contextual-kpi-mint {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border: 1px solid #a7f3d0;
        color: #065f46 !important;
    }
    .contextual-kpi-mint .contextual-kpi-val { color: #047857; }

    .contextual-kpi-rose {
        background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
        border: 1px solid #fecdd3;
        color: #9f1239 !important;
    }
    .contextual-kpi-rose .contextual-kpi-val { color: #be123c; }

    .contextual-kpi-peach {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        border: 1px solid #fde68a;
        color: #92400e !important;
    }
    .contextual-kpi-peach .contextual-kpi-val { color: #b45309; }

    .contextual-kpi-blue {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 1px solid #bae6fd;
        color: #075985 !important;
    }
    .contextual-kpi-blue .contextual-kpi-val { color: #0284c7; }

    .contextual-mini-metric {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        transition: background-color 0.2s ease;
    }

    .contextual-mini-metric:hover {
        background: #f1f5f9;
    }

    .sidak-floating-dock-container {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1040;
        pointer-events: none;
    }

    .sidak-floating-dock {
        pointer-events: auto;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(203, 213, 225, 0.85);
        border-radius: 9999px;
        padding: 6px 14px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12), 0 2px 8px rgba(15, 23, 42, 0.04);
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.25s ease;
    }

    .sidak-floating-dock:hover {
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.18);
        border-color: #94a3b8;
    }

    .dock-pill-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #475569 !important;
        text-decoration: none !important;
        font-size: 15px;
        position: relative;
        transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .dock-pill-btn:hover {
        background: #f1f5f9;
        color: #0f172a !important;
        transform: translateY(-2px);
    }

    .dock-pill-btn.active {
        background: #0f172a;
        color: #ffffff !important;
    }

    .dock-pill-btn.has-badge .dock-badge {
        position: absolute;
        top: -2px;
        right: -4px;
        font-size: 9px;
        font-weight: 800;
        padding: 1px 5px;
        border-radius: 9999px;
        line-height: 1.1;
        border: 1px solid #ffffff;
    }

    .dock-btn-create {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #00B5B8 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 10px rgba(0, 181, 184, 0.3);
    }

    .dock-btn-create:hover {
        background: #009699 !important;
        color: #ffffff !important;
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 14px rgba(0, 181, 184, 0.4);
    }

    .dock-btn-ai {
        height: 38px;
        width: auto !important;
        min-width: 140px;
        padding: 0 16px !important;
        border-radius: 9999px;
        background: #0f172a !important;
        color: #ffffff !important;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none !important;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.2);
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .dock-btn-ai:hover {
        background: #1e293b !important;
        color: #38bdf8 !important;
        transform: translateY(-2px);
    }

    .dock-pill-status {
        display: flex;
        align-items: center;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        padding-left: 6px;
        border-left: 1px solid #e2e8f0;
    }

    @media (max-width: 991.98px) {
        .constellation-canvas-card {
            min-height: 440px;
        }
        .constellation-canvas-body {
            min-height: 380px;
        }
    }

    @media (max-width: 575.98px) {
        .constellation-canvas-card {
            min-height: 380px;
        }
        .constellation-canvas-body {
            min-height: 320px;
        }
        .constellation-central-ring {
            width: 44px;
            height: 44px;
            font-size: 16px;
        }
        .constellation-central-halo {
            width: 120px;
            height: 120px;
        }
        .constellation-node-disc {
            width: 32px;
            height: 32px;
            font-size: 12px;
        }
        .constellation-node-aura {
            width: 48px;
            height: 48px;
        }
        .constellation-node-label {
            font-size: 9px;
            padding: 1px 5px;
        }
        .contextual-kpi-val {
            font-size: 22px;
        }
        .contextual-kpi-title {
            font-size: 10px;
        }
    }

    /* Node Highlight and Dynamic Previews */
    .constellation-svg-line.highlighted {
        stroke: #10b981 !important;
        stroke-width: 3px !important;
        stroke-dasharray: none !important;
        filter: drop-shadow(0 0 6px rgba(16, 185, 129, 0.7));
    }
    .constellation-node.active-hover {
        transform: translate(-50%, -50%) scale(1.15) !important;
        z-index: 15 !important;
    }
    .constellation-node.active-hover .constellation-node-aura {
        opacity: 1 !important;
        filter: blur(12px) !important;
    }

    /* Command Palette Modal (Identical to Foto #4 / User Reference) */
    .cmd-palette-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 1090;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: cmdFadeIn 0.2s ease-out;
    }

    @keyframes cmdFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .cmd-palette-dialog {
        width: 100%;
        max-width: 580px;
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25), 0 10px 20px rgba(15, 23, 42, 0.1);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 85vh;
        animation: cmdSlideDown 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes cmdSlideDown {
        from { transform: translateY(-16px) scale(0.97); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }

    .cmd-palette-search-box {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        background: #ffffff;
    }

    .cmd-palette-search-icon {
        color: #94a3b8;
        font-size: 16px;
    }

    .cmd-palette-input {
        border: none;
        outline: none;
        font-size: 15px;
        font-weight: 500;
        color: #1e293b;
        background: transparent;
        flex: 1;
        width: 100%;
        font-family: inherit;
    }

    .cmd-palette-input::placeholder {
        color: #94a3b8;
    }

    .cmd-palette-esc-btn {
        background: #f1f5f9;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        cursor: pointer;
        line-height: 1;
        transition: all 0.15s ease;
    }

    .cmd-palette-esc-btn:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .cmd-palette-results {
        overflow-y: auto;
        padding: 10px 12px;
        flex: 1;
        max-height: 480px;
    }

    .cmd-group-header {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #94a3b8;
        padding: 8px 12px 4px 12px;
    }

    .cmd-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 12px;
        text-decoration: none !important;
        color: #1e293b !important;
        transition: all 0.15s ease;
        margin-bottom: 2px;
        cursor: pointer;
    }

    .cmd-item:hover, .cmd-item.active {
        background: #1e293b !important;
        color: #ffffff !important;
    }

    .cmd-item:hover .cmd-item-sub, .cmd-item.active .cmd-item-sub {
        color: #94a3b8 !important;
    }

    .cmd-item:hover .cmd-chevron, .cmd-item.active .cmd-chevron {
        color: #ffffff !important;
        transform: translateX(2px);
    }

    .cmd-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .cmd-item-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .cmd-item-title {
        font-size: 13px;
        font-weight: 700;
        line-height: 1.2;
    }

    .cmd-item-sub {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }

    .cmd-chevron {
        font-size: 11px;
        color: #cbd5e1;
        transition: transform 0.15s ease;
    }

    .cmd-palette-footer {
        padding: 10px 18px;
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 11px;
        color: #64748b;
    }

    .cmd-shortcut-badge {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 2px 6px;
        font-weight: 700;
        font-size: 10px;
        color: #475569;
    }

    .cmd-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 9999px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
        margin-left: auto;
    }

    .cmd-item:hover .cmd-badge, .cmd-item.active .cmd-badge {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
        border-color: rgba(255, 255, 255, 0.3);
    }
</style>

<div class="sidak-bento-container emc-container container-fluid py-3">

    <!-- 1. SECTION A: EXECUTIVE WELCOME BANNER -->
    <div class="sidak-bento-card sidak-bento-welcome emc-card p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo PLN SIDAK TEJO" style="max-height: 52px;" class="bg-white p-1 rounded-3 shadow-sm">
                <div>
                    <?php
                        $hour = (int)date('H');
                        if ($hour >= 0 && $hour < 11) $greeting = 'Selamat Pagi';
                        elseif ($hour >= 11 && $hour < 15) $greeting = 'Selamat Siang';
                        elseif ($hour >= 15 && $hour < 18) $greeting = 'Selamat Sore';
                        else $greeting = 'Selamat Malam';
                    ?>
                    <h4 class="fw-bold mb-1 text-white">
                        <?= $greeting ?>, <span class="text-warning"><?= esc(session()->get('user_name') ?: 'Mas Dwi') ?></span> 👋
                    </h4>
                    <p class="text-white-50 small mb-0">
                        Hari ini ada <strong>18 pekerjaan aktif</strong> &middot; ULP: <strong><?= esc(session()->get('user_ulp_nama') ?: 'UP3 Sidoarjo') ?></strong> &middot; Role: <strong><?= esc(get_role_label((string)(session()->get('user_role') ?: 'administrator'))) ?></strong>
                    </p>
                </div>
            </div>

            <!-- Server Time & Date -->
            <div class="text-end d-none d-md-block">
                <h3 class="fw-bold font-monospace mb-0 text-warning" id="emc-clock"><?= date('H:i:s') ?> WIB</h3>
                <small class="text-white-50"><i class="fas fa-calendar-day me-1"></i> <?= date('l, d F Y') ?></small>
            </div>
        </div>

        <!-- Permanent Admin Motivation Quote Banner -->
        <hr class="border-secondary opacity-25 my-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 12px;">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-quote-left text-warning fs-5"></i>
                <span id="permanent-motivation-text" class="fst-italic text-white">"<?= esc(get_daily_announcement()) ?>"</span>
                <button type="button" class="btn btn-xs btn-outline-light rounded-circle ms-2" onclick="editMotivation()" title="Edit Motivasi Admin"><i class="fas fa-pencil"></i></button>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                <span class="status-pulse-live me-1"></span> Live Monitoring Center PLN
            </span>
        </div>
    </div>

    <!-- 2. SECTION B: TOUCH-FRIENDLY QUICK ACTIONS -->
    <div class="sidak-bento-action-bar quick-action-bar-emc">
        <?php if ($canInput ?? check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
        <a href="<?= site_url('temuan/create') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-plus-circle text-success fs-5"></i>
            <span>Input Temuan</span>
        </a>
        <?php endif; ?>
        <a href="<?= site_url('temuan') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-list-check text-primary fs-5"></i>
            <span>Data Temuan</span>
        </a>
        <?php if ($canEdit ?? !check_role(['supervisor_up3'])): ?>
        <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-pen-to-square text-warning fs-5"></i>
            <span>Update Pekerjaan</span>
        </a>
        <?php endif; ?>
        <a href="javascript:void(0)" onclick="triggerQrScanModal()" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-qrcode text-purple fs-5"></i>
            <span>QR Scanner</span>
        </a>
        <a href="<?= site_url('ai-copilot') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-robot text-info fs-5"></i>
            <span>Voice AI</span>
        </a>
        <a href="<?= site_url('temuan/terdekat') ?>" class="sidak-bento-action-pill quick-emc-btn">
            <i class="fas fa-location-crosshairs text-danger fs-5"></i>
            <span>Lokasi Terdekat</span>
        </a>
    </div>

    <!-- 3. SECTION C: CONSTELLATION MISSION CONTROL & CONTEXTUAL DASHBOARD (PHASE 2E) -->
    <?php
        $dailyTarget = max(1, (int)($stats['target_harian'] ?? 25));
        $dailyDone = (int)($stats['hari_ini'] ?? $stats['selesai_hari_ini'] ?? ($stats['selesai'] ?? 0));
        $dailyPct = min(100, (int)round(($dailyDone / $dailyTarget) * 100));
        $gisPinCount = count($mapPins ?? []);
    ?>
    <div class="row g-4 mb-4 align-items-stretch">
        <!-- 3A. CONSTELLATION MISSION CONTROL CANVAS (LEFT / CENTER) -->
        <div class="col-lg-7 col-12">
            <div class="sidak-bento-card emc-card constellation-canvas-card h-100">
                <!-- Canvas Top Header -->
                <div class="p-3 pb-2 d-flex justify-content-between align-items-start z-2 position-relative">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size: 10px; font-weight: 700; letter-spacing: 0.5px;">
                                <i class="fas fa-network-wired me-1"></i> CONSTELLATION NAV &bull; LIVE
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1" style="letter-spacing: -0.3px;">Pilih titik cahaya untuk membuka menu</h5>
                        <p class="text-muted small mb-0" style="font-size: 11px;">Setiap node adalah lokasi menu operasional. Hover untuk preview, klik untuk navigasi langsung.</p>
                    </div>
                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small fw-bold">9 nodes</span>
                </div>

                <!-- Constellation Interactive Body -->
                <div class="constellation-canvas-body position-relative">
                    <!-- SVG Vector Connecting Lines Overlay -->
                    <svg class="constellation-svg-network" viewBox="0 0 1000 600" preserveAspectRatio="none">
                        <!-- Ring web connections between adjacent satellites -->
                        <line x1="500" y1="72" x2="820" y2="156" class="constellation-svg-line" />
                        <line x1="820" y1="156" x2="840" y2="324" class="constellation-svg-line" />
                        <line x1="840" y1="324" x2="740" y2="492" class="constellation-svg-line" />
                        <line x1="740" y1="492" x2="500" y2="516" class="constellation-svg-line" />
                        <line x1="500" y1="516" x2="260" y2="492" class="constellation-svg-line" />
                        <line x1="260" y1="492" x2="160" y2="324" class="constellation-svg-line" />
                        <line x1="160" y1="324" x2="180" y2="156" class="constellation-svg-line" />
                        <line x1="180" y1="156" x2="500" y2="72" class="constellation-svg-line" />

                        <!-- Center to satellite vector lines with discrete IDs for hover highlight -->
                        <line id="svg-line-ai" x1="500" y1="288" x2="500" y2="72" class="constellation-svg-line active" />
                        <line id="svg-line-wo" x1="500" y1="288" x2="820" y2="156" class="constellation-svg-line active" />
                        <line id="svg-line-temuan" x1="500" y1="288" x2="840" y2="324" class="constellation-svg-line active" />
                        <line id="svg-line-analytics" x1="500" y1="288" x2="740" y2="492" class="constellation-svg-line active" />
                        <line id="svg-line-planning" x1="500" y1="288" x2="500" y2="516" class="constellation-svg-line active" />
                        <line id="svg-line-assets" x1="500" y1="288" x2="260" y2="492" class="constellation-svg-line active" />
                        <line id="svg-line-tugas" x1="500" y1="288" x2="160" y2="324" class="constellation-svg-line active" />
                        <line id="svg-line-gis" x1="500" y1="288" x2="180" y2="156" class="constellation-svg-line active" />
                    </svg>

                    <!-- 1. Central Hub Node: ◎ Dashboard Utama -->
                    <div class="constellation-central-node" style="left: 50%; top: 48%;" id="node-hub-dashboard" data-node="dashboard" onmouseenter="showNodePreview('dashboard')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-central-halo"></div>
                        <a href="<?= site_url('dashboard') ?>" class="constellation-central-ring" title="Dashboard Utama">
                            <i class="fas fa-dot-circle"></i>
                        </a>
                        <a href="<?= site_url('dashboard') ?>" class="constellation-central-pill">
                            <span class="rounded-circle bg-success" style="width: 8px; height: 8px;"></span>
                            <strong class="text-dark" style="font-size: 12px;">Dashboard Utama</strong>
                            <span class="badge bg-light text-dark border px-2 py-0" style="font-size: 11px;"><?= number_format($stats['total'] ?? 0) ?></span>
                            <span class="text-muted small">Klik untuk buka</span>
                        </a>
                    </div>

                    <!-- 2. Satellite Node: AI Center & Copilot (Top Center) -->
                    <a href="<?= site_url('ai-copilot') ?>" class="constellation-node" style="left: 50%; top: 12%;" title="AI Copilot & Intelligence" id="node-ai" data-node="ai" onmouseenter="showNodePreview('ai')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(6, 182, 212, 0.45);"></div>
                        <div class="constellation-node-disc" style="border-color: #06b6d4;">
                            <i class="fas fa-robot text-info"></i>
                            <span class="constellation-node-badge bg-info text-white">AI</span>
                        </div>
                        <span class="constellation-node-label text-info">AI Copilot</span>
                    </a>

                    <!-- 3. Satellite Node: Work Orders (WO) (Top Right) -->
                    <a href="<?= site_url('work-orders') ?>" class="constellation-node" style="left: 82%; top: 26%;" title="Work Orders (WO)" id="node-wo" data-node="wo" onmouseenter="showNodePreview('wo')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(239, 68, 68, 0.35);"></div>
                        <span class="constellation-node-pill bg-dark text-white border border-secondary shadow-sm">
                            <i class="fas fa-bolt text-warning me-1"></i><?= number_format($woStats['aktif'] ?? 0) ?> WO
                        </span>
                        <span class="constellation-node-label">Work Orders</span>
                    </a>

                    <!-- 4. Satellite Node: Data Temuan (Right Center) -->
                    <a href="<?= site_url('temuan') ?>" class="constellation-node" style="left: 84%; top: 54%;" title="Data Temuan" id="node-temuan" data-node="temuan" onmouseenter="showNodePreview('temuan')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(245, 158, 11, 0.45);"></div>
                        <div class="constellation-node-disc" style="border-color: #f59e0b;">
                            <i class="fas fa-clipboard-list text-warning"></i>
                            <span class="constellation-node-badge bg-warning text-dark"><?= number_format($stats['total'] ?? 0) ?></span>
                        </div>
                        <span class="constellation-node-label">Data Temuan</span>
                    </a>

                    <!-- 5. Satellite Node: Executive Analytics (Bottom Right) -->
                    <a href="<?= site_url('executive-dashboard') ?>" class="constellation-node" style="left: 74%; top: 82%;" title="Executive Analytics" id="node-analytics" data-node="analytics" onmouseenter="showNodePreview('analytics')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(16, 185, 129, 0.4);"></div>
                        <div class="constellation-node-disc" style="border-color: #10b981;">
                            <i class="fas fa-chart-line text-success"></i>
                            <span class="constellation-node-badge bg-success text-white"><?= $dailyPct ?>%</span>
                        </div>
                        <span class="constellation-node-label">Analytics</span>
                    </a>

                    <!-- 6. Satellite Node: Planning Inspeksi (Bottom Center) -->
                    <a href="<?= site_url('planning') ?>" class="constellation-node" style="left: 50%; top: 86%;" title="Planning Inspeksi" id="node-planning" data-node="planning" onmouseenter="showNodePreview('planning')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(59, 130, 246, 0.4);"></div>
                        <div class="constellation-node-disc" style="border-color: #3b82f6;">
                            <i class="fas fa-calendar-check text-primary"></i>
                            <span class="constellation-node-badge bg-primary text-white"><?= number_format($stats['target_harian'] ?? 25) ?></span>
                        </div>
                        <span class="constellation-node-label">Planning Inspeksi</span>
                    </a>

                    <!-- 7. Satellite Node: Master Asset (Bottom Left) -->
                    <a href="<?= site_url('master-assets') ?>" class="constellation-node" style="left: 26%; top: 82%;" title="Master Asset PLN" id="node-assets" data-node="assets" onmouseenter="showNodePreview('assets')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(100, 116, 139, 0.4);"></div>
                        <div class="constellation-node-disc" style="border-color: #64748b;">
                            <i class="fas fa-boxes-stacked text-secondary"></i>
                            <span class="constellation-node-badge bg-secondary text-white">Asset</span>
                        </div>
                        <span class="constellation-node-label">Master Asset</span>
                    </a>

                    <!-- 8. Satellite Node: Tugas Inspeksi Saya (Left Center) -->
                    <a href="<?= site_url('my-inspections') ?>" class="constellation-node" style="left: 16%; top: 54%;" title="Tugas Saya" id="node-tugas" data-node="tugas" onmouseenter="showNodePreview('tugas')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(244, 63, 94, 0.4);"></div>
                        <div class="constellation-node-disc" style="border-color: #f43f5e;">
                            <i class="fas fa-user-check text-danger"></i>
                            <span class="constellation-node-badge bg-danger text-white"><?= number_format($stats['belum'] ?? 0) ?></span>
                        </div>
                        <span class="constellation-node-label">Tugas Saya</span>
                    </a>

                    <!-- 9. Satellite Node: Peta Jaringan GIS (Top Left) -->
                    <a href="<?= site_url('gis') ?>" class="constellation-node" style="left: 18%; top: 26%;" title="Peta Jaringan GIS" id="node-gis" data-node="gis" onmouseenter="showNodePreview('gis')" onmouseleave="scheduleResetPreview()">
                        <div class="constellation-node-aura" style="background: rgba(2, 132, 199, 0.45);"></div>
                        <div class="constellation-node-disc" style="border-color: #0284c7;">
                            <i class="fas fa-map-marked-alt text-primary"></i>
                            <span class="constellation-node-badge bg-primary text-white"><?= number_format($gisPinCount) ?></span>
                        </div>
                        <span class="constellation-node-label">Peta GIS</span>
                    </a>
                </div>

                <!-- Canvas Footer Legend -->
                <div class="p-3 pt-2 d-flex flex-wrap align-items-center justify-content-between gap-2 z-2 position-relative border-top border-light" style="font-size: 11px;">
                    <div class="d-flex flex-wrap align-items-center gap-3 text-muted">
                        <span><span class="badge bg-primary rounded-circle p-1 me-1 d-inline-block"></span> GIS</span>
                        <span><span class="badge bg-warning rounded-circle p-1 me-1 d-inline-block"></span> Temuan</span>
                        <span><span class="badge bg-danger rounded-circle p-1 me-1 d-inline-block"></span> WO / Darurat</span>
                        <span><span class="badge bg-info rounded-circle p-1 me-1 d-inline-block"></span> AI Copilot</span>
                        <span><span class="badge bg-success rounded-circle p-1 me-1 d-inline-block"></span> Analytics</span>
                    </div>
                    <span class="text-muted small"><i class="fas fa-circle-info me-1"></i> Interactive Constellation Canvas</span>
                </div>
            </div>
        </div>

        <!-- 3B. CONTEXTUAL DASHBOARD PANEL (RIGHT) -->
        <div class="col-lg-5 col-12" id="contextual-panel-container">
            <div class="sidak-bento-card contextual-dashboard-card emc-card p-4 d-flex flex-column justify-content-between h-100">
                <div id="contextual-views-container">
                    <!-- VIEW 1: DASHBOARD UTAMA OVERVIEW (DEFAULT) -->
                    <div id="view-dashboard" class="contextual-view-block">
                        <!-- Panel Top Header -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="sidak-bento-icon-box bg-success-subtle text-success rounded-3 fs-5">
                                    <i class="fas fa-th-large"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="fw-bold text-dark mb-0">Dashboard Utama</h5>
                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-0 small fw-bold">
                                            <?= number_format($stats['total'] ?? 0) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted" style="font-size: 11px;">Ringkasan &amp; KPI &bull; Constellation &rarr; HOME</small>
                                </div>
                            </div>
                            <a href="<?= site_url('dashboard') ?>" class="btn btn-sm btn-outline-light text-muted border rounded-circle" title="Refresh Dashboard">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                        </div>

                        <!-- 4 Pastel Bento KPI Cards (2x2 Grid) -->
                        <div class="contextual-kpi-grid sidak-bento-kpi-grid mb-3">
                            <!-- 1. Jumlah Temuan (Mint) -->
                            <a href="<?= site_url('temuan') ?>" class="contextual-kpi-card contextual-kpi-mint sidak-bento-kpi-card kpi-drilldown-link">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="contextual-kpi-val sidak-bento-val" id="kpi-total-temuan"><?= number_format($stats['total'] ?? 0) ?></div>
                                    <i class="fas fa-arrow-up-right text-muted opacity-75"></i>
                                </div>
                                <div>
                                    <div class="contextual-kpi-title sidak-bento-lbl">Jumlah Temuan</div>
                                    <div class="contextual-kpi-sub">Total Inspeksi Fisik</div>
                                </div>
                            </a>

                            <!-- 2. Emergency (Rose) -->
                            <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="contextual-kpi-card contextual-kpi-rose sidak-bento-kpi-card kpi-drilldown-link">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="contextual-kpi-val text-danger sidak-bento-val" id="kpi-emergency"><?= number_format($stats['emergency'] ?? 0) ?></div>
                                    <i class="fas fa-arrow-up-right text-danger opacity-75"></i>
                                </div>
                                <div>
                                    <div class="contextual-kpi-title text-danger sidak-bento-lbl">Emergency</div>
                                    <div class="contextual-kpi-sub">Prioritas Tinggi</div>
                                </div>
                            </a>

                            <!-- 3. Belum Selesai (Peach) — User Amendment #1 Preserved -->
                            <a href="<?= site_url('temuan?status=BELUM') ?>" class="contextual-kpi-card contextual-kpi-peach sidak-bento-kpi-card kpi-drilldown-link">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="contextual-kpi-val text-warning sidak-bento-val" id="kpi-belum"><?= number_format($stats['belum'] ?? 0) ?></div>
                                    <i class="fas fa-arrow-up-right text-warning opacity-75"></i>
                                </div>
                                <div>
                                    <div class="contextual-kpi-title text-warning sidak-bento-lbl">Belum Selesai</div>
                                    <div class="contextual-kpi-sub">Dalam Antrian</div>
                                </div>
                            </a>

                            <!-- 4. GIS Node (Sky Blue) -->
                            <a href="<?= site_url('gis') ?>" class="contextual-kpi-card contextual-kpi-blue sidak-bento-kpi-card kpi-drilldown-link">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="contextual-kpi-val text-primary sidak-bento-val" id="kpi-gis-pins"><?= number_format($gisPinCount) ?></div>
                                    <i class="fas fa-arrow-up-right text-primary opacity-75"></i>
                                </div>
                                <div>
                                    <div class="contextual-kpi-title text-primary sidak-bento-lbl">GIS Node</div>
                                    <div class="contextual-kpi-sub">Terverifikasi</div>
                                </div>
                            </a>
                        </div>

                        <!-- 3 Mini Metric Indicator Cards (Row of 3) -->
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <a href="<?= site_url('temuan?status=SELESAI') ?>" class="contextual-mini-metric text-center p-2 d-block text-decoration-none">
                                    <div class="fw-bold fs-5 text-dark" id="kpi-selesai"><?= number_format($stats['selesai'] ?? 0) ?></div>
                                    <div class="text-muted text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Selesai</div>
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="<?= site_url('work-orders?status=AKTIF') ?>" class="contextual-mini-metric text-center p-2 d-block text-decoration-none">
                                    <div class="fw-bold fs-5 text-dark" id="kpi-wo-aktif"><?= number_format($woStats['aktif'] ?? 0) ?></div>
                                    <div class="text-muted text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Progress</div>
                                </a>
                            </div>
                            <div class="col-4">
                                <div class="contextual-mini-metric text-center p-2">
                                    <span class="fw-bold fs-5 text-primary" id="kpi-target-harian-pct"><?= $dailyPct ?>%</span>
                                    <div class="text-muted text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: 0.5px;">Target</div>
                                </div>
                            </div>
                        </div>

                        <!-- Compact Target Progress & Secondary Priority Strip -->
                        <div class="p-2 mb-3 rounded-3 bg-light border">
                            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size: 11px;">
                                <span class="text-muted fw-semibold">
                                    Target Harian: <strong id="kpi-target-harian-text"><?= number_format($dailyDone) ?> <span class="text-muted">/ <?= number_format($dailyTarget) ?></span></strong>
                                </span>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?= site_url('temuan?prioritas=HIGH') ?>" class="text-decoration-none text-muted">
                                        High: <div class="d-inline fw-bold text-warning" id="kpi-high"><?= number_format($stats['high'] ?? 0) ?></div>
                                    </a>
                                    &bull;
                                    <a href="<?= site_url('temuan?prioritas=MEDIUM') ?>" class="text-decoration-none text-muted">
                                        Med: <div class="d-inline fw-bold text-info" id="kpi-medium"><?= number_format($stats['medium'] ?? 0) ?></div>
                                    </a>
                                </div>
                            </div>
                            <div class="progress" style="height: 6px; border-radius: 4px; background: #e2e8f0;">
                                <div class="progress-bar bg-success rounded-pill" role="progressbar" style="width: <?= $dailyPct ?>%;" id="kpi-target-harian-bar" aria-valuenow="<?= $dailyPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <!-- Operational Live Feed: AKTIVITAS HARI INI -->
                        <div class="contextual-activity-section">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-dark text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                                    <i class="fas fa-satellite-dish text-primary me-1"></i> Aktivitas Hari Ini
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0 small">
                                    <span class="status-pulse-live me-1"></span> Live
                                </span>
                            </div>
                            <div class="contextual-activity-list">
                                <?php 
                                $contextualFeed = !empty($mapPins) ? array_slice($mapPins, 0, 3) : [];
                                if (!empty($contextualFeed)): 
                                    foreach ($contextualFeed as $feedItem): 
                                        $prio = strtoupper((string)($feedItem['prioritas'] ?? 'MEDIUM'));
                                        $dotColor = $prio === 'EMERGENCY' ? '#ef4444' : ($prio === 'HIGH' ? '#f59e0b' : '#10b981');
                                        $nomor = !empty($feedItem['nomor_temuan']) ? $feedItem['nomor_temuan'] : 'STJ-' . ($feedItem['id'] ?? '0');
                                        $title = !empty($feedItem['judul']) ? $feedItem['judul'] : (!empty($feedItem['penyulang_nama']) ? 'Penyulang ' . $feedItem['penyulang_nama'] : 'Temuan Lapangan');
                                ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden me-2">
                                            <span class="rounded-circle flex-shrink-0" style="width: 8px; height: 8px; background-color: <?= $dotColor ?>;"></span>
                                            <span class="small text-dark text-truncate fw-medium" style="font-size: 12px;"><?= esc($nomor) ?> &ndash; <?= esc($title) ?></span>
                                        </div>
                                        <a href="<?= site_url('temuan/detail/' . ($feedItem['id'] ?? 0)) ?>" class="small text-primary text-nowrap text-decoration-none fw-semibold" style="font-size: 11px;">
                                            Lihat <i class="fas fa-chevron-right ms-1 opacity-75"></i>
                                        </a>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="text-muted small py-3 text-center">Belum ada temuan terpetakan hari ini.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- VIEW 2: GIS PREVIEW -->
                    <div id="view-gis" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-primary-subtle text-primary rounded-3 fs-4">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Peta Jaringan GIS</h5>
                                <small class="text-muted">Monitoring Spasial &bull; JTM / JTR / Gardu</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-primary-subtle border border-primary-subtle">
                            <div class="fs-2 fw-bold text-primary mb-1"><?= number_format($gisPinCount) ?> Node</div>
                            <p class="small text-dark mb-0">Total titik anomali terpetakan di seluruh wilayah kerja PLN UP3 Sidoarjo (Sidoarjo Kota, Gedangan, Candi, Porong, Krian).</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>OpenStreetMap &amp; Leaflet Engine</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Auto-Complete AI Section Jaringan</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Filter Multi-Penyulang &amp; ULP</li>
                        </ul>
                        <a href="<?= site_url('gis') ?>" class="btn btn-primary w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-map-location-dot me-1"></i> Buka Peta GIS Lengkap &rarr;
                        </a>
                    </div>

                    <!-- VIEW 3: WORK ORDERS PREVIEW -->
                    <div id="view-wo" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-danger-subtle text-danger rounded-3 fs-4">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Work Orders (WO)</h5>
                                <small class="text-muted">Manajemen Perintah Kerja Lapangan</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-danger-subtle border border-danger-subtle">
                            <div class="fs-2 fw-bold text-danger mb-1"><?= number_format($woStats['aktif'] ?? 0) ?> WO Aktif</div>
                            <p class="small text-dark mb-0">Perintah kerja penanganan fisik lapangan yang sedang berjalan dan diawasi oleh supervisor teknik.</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Smart WO Dispatch System</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Tracking SLA &amp; Material Checklist</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Integrasi Regu Yantek &amp; Har</li>
                        </ul>
                        <a href="<?= site_url('work-orders') ?>" class="btn btn-danger w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-file-signature me-1"></i> Buka Work Orders &rarr;
                        </a>
                    </div>

                    <!-- VIEW 4: DATA TEMUAN PREVIEW -->
                    <div id="view-temuan" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-warning-subtle text-warning rounded-3 fs-4">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Data Temuan</h5>
                                <small class="text-muted">Database Anomali &amp; Inspeksi Fisik</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-warning-subtle border border-warning-subtle">
                            <div class="fs-2 fw-bold text-warning mb-1"><?= number_format($stats['total'] ?? 0) ?> Temuan</div>
                            <p class="small text-dark mb-0">Rekam jejak anomali inspeksi preventif: <strong><?= number_format($stats['emergency'] ?? 0) ?> Emergency</strong>, <strong><?= number_format($stats['high'] ?? 0) ?> High</strong>.</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Ekspor Excel, CSV &amp; Cetak PDF</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Filter Prioritas, Status &amp; ULP</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Penyelarasan Material Transaksi</li>
                        </ul>
                        <a href="<?= site_url('temuan') ?>" class="btn btn-warning text-dark w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-list-check me-1"></i> Buka Data Temuan &rarr;
                        </a>
                    </div>

                    <!-- VIEW 5: PLANNING INSPEKSI PREVIEW -->
                    <div id="view-planning" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-info-subtle text-info rounded-3 fs-4">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Planning Inspeksi</h5>
                                <small class="text-muted">Perencanaan Rute &amp; Jadwal Lapangan</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-info-subtle border border-info-subtle">
                            <div class="fs-2 fw-bold text-info mb-1"><?= number_format($stats['target_harian'] ?? 25) ?> Target Harian</div>
                            <p class="small text-dark mb-0">Rencana target inspeksi preventif jaringan per hari untuk menjaga keandalan SAIDI/SAIFI UP3 Sidoarjo.</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Penjadwalan Rute per Penyulang</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Alokasi Petugas &amp; Tim Inspeksi</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Monitoring Kepatuhan Rencana</li>
                        </ul>
                        <a href="<?= site_url('planning') ?>" class="btn btn-info text-white w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-calendar-days me-1"></i> Buka Planning Inspeksi &rarr;
                        </a>
                    </div>

                    <!-- VIEW 6: MASTER ASSET PREVIEW -->
                    <div id="view-assets" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-secondary-subtle text-secondary rounded-3 fs-4">
                                <i class="fas fa-boxes-stacked"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Master Asset PLN</h5>
                                <small class="text-muted">Inventaris Aset Distribusi Sidoarjo</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-light border">
                            <div class="fs-2 fw-bold text-dark mb-1">Aset Distribusi</div>
                            <p class="small text-muted mb-0">Master data Gardu Induk, Penyulang, Section JTM, Tiang, Trafo Distribusi &amp; Kubikel UP3 Sidoarjo.</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Asset Health Monitoring</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>QR Code Asset Tagging</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Sinkronisasi Spasial &amp; GIS</li>
                        </ul>
                        <a href="<?= site_url('master-assets') ?>" class="btn btn-secondary w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-database me-1"></i> Buka Master Asset &rarr;
                        </a>
                    </div>

                    <!-- VIEW 7: EXECUTIVE ANALYTICS PREVIEW -->
                    <div id="view-analytics" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-success-subtle text-success rounded-3 fs-4">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Executive Analytics</h5>
                                <small class="text-muted">Strategic Decision &amp; SLA Korporat</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-success-subtle border border-success-subtle">
                            <div class="fs-2 fw-bold text-success mb-1"><?= $dailyPct ?>% Realisasi</div>
                            <p class="small text-dark mb-0">Pencapaian target inspeksi hari ini terhadap target harian korporat UP3 Sidoarjo.</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Komparasi Performa 3 ULP</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Tren Gangguan Periodik Penyulang</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Audit Kepatuhan SLA Kritis</li>
                        </ul>
                        <a href="<?= site_url('executive-dashboard') ?>" class="btn btn-success w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-chart-pie me-1"></i> Buka Executive Dashboard &rarr;
                        </a>
                    </div>

                    <!-- VIEW 8: TUGAS SAYA PREVIEW -->
                    <div id="view-tugas" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-danger-subtle text-danger rounded-3 fs-4">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Tugas Inspeksi Saya</h5>
                                <small class="text-muted">Antrian &amp; Penugasan Petugas Lapangan</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-danger-subtle border border-danger-subtle">
                            <div class="fs-2 fw-bold text-danger mb-1"><?= number_format($stats['belum'] ?? 0) ?> Antrian</div>
                            <p class="small text-dark mb-0">Temuan belum selesai yang memerlukan tindak lanjut inspeksi atau verifikasi lapangan.</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Daftar Tugas per Shift Petugas</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Checklist Pekerjaan Cepat</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Log Riwayat Eksekusi Mandiri</li>
                        </ul>
                        <a href="<?= site_url('my-inspections') ?>" class="btn btn-danger w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-tasks me-1"></i> Buka Tugas Saya &rarr;
                        </a>
                    </div>

                    <!-- VIEW 9: AI COPILOT & CENTER PREVIEW -->
                    <div id="view-ai" class="contextual-view-block" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="sidak-bento-icon-box bg-info-subtle text-info rounded-3 fs-4">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">AI Copilot &amp; Center</h5>
                                <small class="text-muted">Kecerdasan Buatan &amp; Prediksi Anomali</small>
                            </div>
                        </div>
                        <div class="p-3 mb-3 rounded-3 bg-info-subtle border border-info-subtle">
                            <div class="fs-2 fw-bold text-info mb-1">AI Copilot Live</div>
                            <p class="small text-dark mb-0">Asisten cerdas berbasis suara untuk pelaporan temuan cepat, perankingan otomatis dan prediksi kegagalan aset.</p>
                        </div>
                        <ul class="list-unstyled small text-muted mb-4">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Voice-to-Text Temuan Lapangan</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Auto-Ranking Risiko Anomali Jaringan</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Auto-Complete Section Topology GIS</li>
                        </ul>
                        <a href="<?= site_url('ai-copilot') ?>" class="btn btn-info text-white w-100 py-2 rounded-pill fw-bold">
                            <i class="fas fa-headset me-1"></i> Buka SIDAK AI Copilot &rarr;
                        </a>
                    </div>
                </div>

                <!-- Panel Quick Footer Action -->
                <div class="pt-3 border-top border-light mt-3">
                    <a href="<?= site_url('audit-log') ?>" class="btn btn-sm btn-outline-secondary w-100 rounded-pill fw-semibold" style="font-size: 11px;">
                        <i class="fas fa-clock-rotate-left me-1"></i> Buka Log Audit &amp; Aktivitas Lengkap
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3C. FLOATING COMMAND DOCK (DESKTOP ONLY) -->
    <div class="sidak-floating-dock-container d-none d-lg-flex">
        <div class="sidak-floating-dock">
            <button type="button" class="dock-pill-btn" onclick="openCommandPalette()" title="Command Search (Ctrl+K)">
                <i class="fas fa-terminal"></i>
            </button>
            <a href="<?= site_url('gis') ?>" class="dock-pill-btn" title="Peta Jaringan GIS">
                <i class="fas fa-map-marked-alt"></i>
            </a>
            <a href="<?= site_url('temuan') ?>" class="dock-pill-btn has-badge" title="Data Temuan">
                <i class="fas fa-clipboard-list"></i>
                <span class="dock-badge bg-warning text-dark"><?= min(99, (int)($stats['total'] ?? 0)) ?></span>
            </a>
            <a href="<?= site_url('work-orders') ?>" class="dock-pill-btn has-badge" title="Work Orders">
                <i class="fas fa-tools"></i>
                <span class="dock-badge bg-danger text-white"><?= min(99, (int)($woStats['aktif'] ?? 0)) ?></span>
            </a>
            <a href="<?= site_url('planning') ?>" class="dock-pill-btn" title="Planning Inspeksi">
                <i class="fas fa-calendar-alt"></i>
            </a>
            <?php if ($canInput ?? check_role(['administrator', 'admin_ulp', 'inspeksi'])): ?>
            <a href="<?= site_url('temuan/create') ?>" class="dock-pill-btn dock-btn-create" title="Input Temuan Baru">
                <i class="fas fa-plus"></i>
            </a>
            <?php endif; ?>
            <a href="<?= site_url('ai-copilot') ?>" class="dock-pill-btn dock-btn-ai" title="AI Copilot Voice">
                <i class="fas fa-robot me-1"></i>
                <span>Input Temuan / AI</span>
            </a>
            <button type="button" class="dock-pill-btn" onclick="toggleSidebarDrawer()" title="Menu Lengkap Drawer">
                <i class="fas fa-bars"></i>
            </button>
            <div class="dock-pill-status">
                <span class="status-pulse-dot bg-success me-1"></span> Synced
            </div>
        </div>
    </div>

    <!-- 4. SECTION D: MINI GIS MAP & OPERATIONAL FINDINGS FEED -->
    <div class="row g-4 mb-4">
        <!-- Mini GIS Map -->
        <div class="col-lg-8 col-12">
            <div class="sidak-bento-card emc-card p-4 h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="sidak-bento-icon-box bg-success-subtle text-success">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-0">Mini GIS - Sebaran Temuan Lapangan</h5>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small">
                                <i class="fas fa-map-pin me-1"></i><?= number_format(count($mapPins ?? [])) ?> Titik Terpetakan
                            </span>
                        </div>
                    </div>
                    <a href="<?= site_url('gis') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" id="gis-full-btn">
                        <i class="fas fa-expand me-1"></i> Full Map Mode
                    </a>
                </div>
                <div id="emc-mini-map" style="height: 290px; border-radius: 14px;" class="border shadow-sm"></div>
            </div>
        </div>

        <!-- Realtime Operational Feed Panel -->
        <div class="col-lg-4 col-12">
            <div class="sidak-bento-card emc-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="sidak-bento-icon-box bg-primary-subtle text-primary">
                                <i class="fas fa-satellite-dish"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-0">Aktivitas Lapangan Terkini</h5>
                                <small class="text-muted" style="font-size: 11px;">Data Temuan Operasional Riil</small>
                            </div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill small">Live Data</span>
                    </div>
                    
                    <div class="sidak-bento-feed-list">
                        <?php 
                        $recentPins = !empty($mapPins) ? array_slice($mapPins, 0, 4) : [];
                        if (!empty($recentPins)): 
                            foreach ($recentPins as $item): 
                                $prio = strtoupper((string)($item['prioritas'] ?? 'MEDIUM'));
                                $badgeClass = $prio === 'EMERGENCY' ? 'bg-danger text-white' : ($prio === 'HIGH' ? 'bg-warning text-dark' : 'bg-info-subtle text-primary');
                                $status = strtoupper((string)($item['status'] ?? 'BELUM'));
                                $statusBadge = $status === 'SELESAI' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle';
                                $nomor = !empty($item['nomor_temuan']) ? $item['nomor_temuan'] : 'STJ-' . ($item['id'] ?? '0');
                                $title = !empty($item['judul']) ? $item['judul'] : (!empty($item['penyulang_nama']) ? 'Penyulang ' . $item['penyulang_nama'] : 'Temuan Inspeksi');
                        ?>
                            <div class="sidak-bento-feed-item mb-2 p-2 rounded-3 border d-flex justify-content-between align-items-center">
                                <div class="me-2 overflow-hidden">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-dark small text-truncate" style="max-width: 140px;"><?= esc($nomor) ?></span>
                                        <span class="badge <?= $badgeClass ?> px-1 py-0" style="font-size: 9px;"><?= esc($prio) ?></span>
                                    </div>
                                    <small class="text-muted d-block text-truncate" style="font-size: 11px;">
                                        <?= esc($title) ?>
                                    </small>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <span class="badge <?= $statusBadge ?> px-2 py-0 mb-1 d-inline-block" style="font-size: 9px;"><?= esc($status) ?></span>
                                    <a href="<?= site_url('temuan/detail/' . ($item['id'] ?? 0)) ?>" class="d-block small text-primary fw-bold text-decoration-none" style="font-size: 11px;">
                                        Lihat <i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                                <p class="small mb-0">Belum ada temuan terpetakan terkini.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="<?= site_url('audit-log') ?>" class="btn btn-sm btn-outline-secondary w-100 rounded-pill fw-semibold" style="font-size: 11px;">
                        <i class="fas fa-history me-1"></i> Buka Log Aktivitas Lengkap
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. SECTION E: SLA MONITOR WIDGET -->
    <div class="sidak-bento-card emc-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="sidak-bento-icon-box bg-warning-subtle text-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-0">SLA Monitoring Widget</h5>
                    <small class="text-muted" style="font-size: 11px;">Kepatuhan Batas Waktu Tindak Lanjut Temuan Lapangan</small>
                </div>
            </div>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill small">SLA Korporat</span>
        </div>
        <div class="row g-3 text-center">
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="sidak-bento-sla-card bg-danger-subtle border border-danger-subtle">
                    <small class="text-danger fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">EMERGENCY (SLA 3 Hari)</small>
                    <h3 class="fw-bold text-danger my-1" id="sla-val-emergency"><?= number_format($stats['emergency'] ?? 0) ?></h3>
                    <span class="small text-danger opacity-75" style="font-size: 11px;">Penanganan Kritis <i class="fas fa-arrow-right ms-1"></i></span>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('temuan?prioritas=HIGH') ?>" class="sidak-bento-sla-card bg-warning-subtle border border-warning-subtle">
                    <small class="text-warning fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">HIGH (SLA 7 Hari)</small>
                    <h3 class="fw-bold text-warning my-1" id="sla-val-high"><?= number_format($stats['high'] ?? 0) ?></h3>
                    <span class="small text-warning opacity-75" style="font-size: 11px;">Prioritas Tinggi <i class="fas fa-arrow-right ms-1"></i></span>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('temuan?prioritas=MEDIUM') ?>" class="sidak-bento-sla-card bg-primary-subtle border border-primary-subtle">
                    <small class="text-primary fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">MEDIUM (SLA 31 Hari)</small>
                    <h3 class="fw-bold text-primary my-1" id="sla-val-medium"><?= number_format($stats['medium'] ?? 0) ?></h3>
                    <span class="small text-primary opacity-75" style="font-size: 11px;">Jadwal Terencana <i class="fas fa-arrow-right ms-1"></i></span>
                </a>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <a href="<?= site_url('pekerjaan') ?>" class="sidak-bento-sla-card bg-danger-subtle border border-danger">
                    <small class="text-danger fw-bold d-block text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;">SLA MELEWATI (OVERDUE)</small>
                    <h3 class="fw-bold text-danger my-1" id="sla-val-overdue"><?= number_format($woStats['overdue'] ?? 0) ?></h3>
                    <span class="small text-danger fw-bold" style="font-size: 11px;">Perlu Eskalasi Segera <i class="fas fa-exclamation-triangle ms-1"></i></span>
                </a>
            </div>
        </div>
    </div>

    <!-- 6. SECTION F: EXECUTIVE ANALYTICS CTA -->
    <div class="sidak-bento-cta-card mb-4 shadow-sm">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="p-3 bg-white bg-opacity-10 rounded-3 border border-white border-opacity-20 text-warning fs-2">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-white mb-1">Executive Analytics &amp; Strategic Decision Center</h5>
                    <p class="text-white-50 small mb-0">
                        Pantau perbandingan performa lintas 3 ULP, rasio penyelesaian penyulang prioritas, tren gangguan periodik, serta kepatuhan SLA korporat secara komprehensif.
                    </p>
                </div>
            </div>
            <div>
                <a href="<?= site_url('executive-dashboard') ?>" class="sidak-bento-cta-btn">
                    <span>Buka Executive Dashboard</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
</div>

<!-- 7. COMMAND PALETTE MODAL (CTRL+K / CMD+K / TOPBAR SEARCH / DOCK BUTTON) -->
<div id="sidak-command-palette-modal" class="cmd-palette-backdrop" style="display: none;" onclick="if(event.target === this) closeCommandPalette()">
    <div class="cmd-palette-dialog" onclick="event.stopPropagation()">
        <!-- Search Input Header -->
        <div class="cmd-palette-search-box">
            <i class="fas fa-search cmd-palette-search-icon"></i>
            <input type="text" id="cmd-palette-search-input" class="cmd-palette-input" placeholder="Cari menu constellation (GIS, WO, Temuan, Planning, Master Asset, AI)..." autocomplete="off" oninput="filterCommandPalette(this.value)" onkeydown="handleCommandPaletteKey(event)">
            <button type="button" class="cmd-palette-esc-btn" onclick="closeCommandPalette()">ESC</button>
        </div>

        <!-- Search Results List -->
        <div class="cmd-palette-results" id="cmd-palette-results">
            <!-- Group 1: CONSTELLATION CAPABILITIES (8 + 1) -->
            <div class="cmd-group-block">
                <div class="cmd-group-header">MENU CONSTELLATION (8 + 1)</div>
                <a href="<?= site_url('dashboard') ?>" class="cmd-item" data-search="dashboard utama home kpi beranda total temuan ringkasan">
                    <span class="cmd-dot" style="background: #10b981;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Dashboard Utama</span>
                            <span class="cmd-badge">9 nodes</span>
                        </div>
                        <span class="cmd-item-sub">Ringkasan KPI, target harian &amp; aktivitas live</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('gis') ?>" class="cmd-item" data-search="peta jaringan gis map geospasial sebaran tiang gardu krian porong candi gedangan sidoarjo">
                    <span class="cmd-dot" style="background: #0284c7;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Peta Jaringan GIS</span>
                            <span class="cmd-badge"><?= number_format($gisPinCount) ?> Node</span>
                        </div>
                        <span class="cmd-item-sub">Peta sebaran anomali, gardu, penyulang &amp; GIS</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('work-orders') ?>" class="cmd-item" data-search="work orders wo spk perintah kerja lapangan overdue perbaikan yantek pemeliharaan">
                    <span class="cmd-dot" style="background: #ef4444;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Work Orders (WO)</span>
                            <span class="cmd-badge"><?= number_format($woStats['aktif'] ?? 0) ?> WO</span>
                        </div>
                        <span class="cmd-item-sub">Manajemen SPK penanganan fisik &amp; SLA lapangan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan') ?>" class="cmd-item" data-search="data temuan inspeksi anomali daftar list ekspor filter riwayat fisik">
                    <span class="cmd-dot" style="background: #f59e0b;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Data Temuan</span>
                            <span class="cmd-badge"><?= number_format($stats['total'] ?? 0) ?></span>
                        </div>
                        <span class="cmd-item-sub">Database lengkap temuan inspeksi, filter &amp; ekspor</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('planning') ?>" class="cmd-item" data-search="planning inspeksi jadwal rute rencana kalender target harian agenda">
                    <span class="cmd-dot" style="background: #3b82f6;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Planning Inspeksi</span>
                            <span class="cmd-badge"><?= number_format($stats['target_harian'] ?? 25) ?> Target</span>
                        </div>
                        <span class="cmd-item-sub">Perencanaan jadwal, rute inspeksi &amp; target harian</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('master-assets') ?>" class="cmd-item" data-search="master asset data aset gardu tiang trafo kubikel jaringan distribusi">
                    <span class="cmd-dot" style="background: #64748b;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Master Asset PLN</span>
                            <span class="cmd-badge">Asset</span>
                        </div>
                        <span class="cmd-item-sub">Data aset GI, penyulang, tiang, trafo &amp; kubikel</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('executive-dashboard') ?>" class="cmd-item" data-search="executive analytics dashboard pimpinan manajemen ulp saidi saifi sla korporat">
                    <span class="cmd-dot" style="background: #10b981;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Executive Analytics</span>
                            <span class="cmd-badge"><?= $dailyPct ?>% SLA</span>
                        </div>
                        <span class="cmd-item-sub">Analisis performa ULP, SLA korporat &amp; tren gangguan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('my-inspections') ?>" class="cmd-item" data-search="tugas saya antrian inspeksi penugasan petugas shift pekerjaan mandiri">
                    <span class="cmd-dot" style="background: #f43f5e;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">Tugas Saya</span>
                            <span class="cmd-badge"><?= number_format($stats['belum'] ?? 0) ?> Antrian</span>
                        </div>
                        <span class="cmd-item-sub">Daftar tugas &amp; antrian inspeksi petugas lapangan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('ai-copilot') ?>" class="cmd-item" data-search="ai copilot voice voice-to-text asisten cerdas intelligence kecerdasan buatan suara">
                    <span class="cmd-dot" style="background: #06b6d4;"></span>
                    <div class="cmd-item-info">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="cmd-item-title">AI Copilot &amp; Center</span>
                            <span class="cmd-badge">AI Voice</span>
                        </div>
                        <span class="cmd-item-sub">Voice inspection, AI auto-rank &amp; rekomendasi</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
            </div>

            <!-- Group 2: OPERASIONAL & ANOMALI LAPANGAN -->
            <div class="cmd-group-block">
                <div class="cmd-group-header">OPERASIONAL &amp; ANOMALI LAPANGAN</div>
                <a href="<?= site_url('temuan/create') ?>" class="cmd-item" data-search="input temuan baru form lapor tambah foto upload anomali">
                    <span class="cmd-dot" style="background: #10b981;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Input Temuan Baru</span>
                        <span class="cmd-item-sub">Form input inspeksi fisik &amp; upload foto anomali</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="cmd-item" data-search="update pekerjaan lapangan status progres tindak lanjut fisik">
                    <span class="cmd-dot" style="background: #f59e0b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Update Pekerjaan Lapangan</span>
                        <span class="cmd-item-sub">Update status progres penanganan temuan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan/terdekat') ?>" class="cmd-item" data-search="lokasi terdekat gps radius sekitar posisi koordinat anomali">
                    <span class="cmd-dot" style="background: #ef4444;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Lokasi Terdekat GPS</span>
                        <span class="cmd-item-sub">Pencarian anomali terdekat dari posisi saat ini</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan/riwayat') ?>" class="cmd-item" data-search="riwayat temuan historis arsip pelaporan user petugas">
                    <span class="cmd-dot" style="background: #64748b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Riwayat Temuan Terinput</span>
                        <span class="cmd-item-sub">Arsip pelaporan historis per user/petugas</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan?status=BELUM') ?>" class="cmd-item" data-search="temuan status belum selesai pending antrian">
                    <span class="cmd-dot" style="background: #f59e0b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Temuan Status Belum Selesai</span>
                        <span class="cmd-item-sub">Daftar anomali antrian belum ditangani</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan?prioritas=EMERGENCY') ?>" class="cmd-item" data-search="temuan emergency darurat kritis 3 hari sla bahaya keselamatan">
                    <span class="cmd-dot" style="background: #ef4444;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Temuan Emergency Kritis</span>
                        <span class="cmd-item-sub">Prioritas darurat tinggi batas waktu 3 hari</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan?prioritas=HIGH') ?>" class="cmd-item" data-search="temuan high priority 7 hari tinggi penting">
                    <span class="cmd-dot" style="background: #f59e0b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Temuan High Priority</span>
                        <span class="cmd-item-sub">Prioritas tinggi batas waktu 7 hari</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="javascript:void(0)" onclick="triggerQrScanModal(); closeCommandPalette();" class="cmd-item" data-search="qr scanner scan barcode kamera aset tiang gardu">
                    <span class="cmd-dot" style="background: #8b5cf6;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">QR Scanner Aset Lapangan</span>
                        <span class="cmd-item-sub">Scan barcode tiang/gardu untuk detail aset</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
            </div>

            <!-- Group 3: WORK ORDERS (SPK) -->
            <div class="cmd-group-block">
                <div class="cmd-group-header">WORK ORDERS (SPK) &amp; EKSEKUSI</div>
                <a href="<?= site_url('work-orders') ?>" class="cmd-item" data-search="daftar work orders wo spk perintah kerja lapangan">
                    <span class="cmd-dot" style="background: #ef4444;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Daftar Work Orders (WO)</span>
                        <span class="cmd-item-sub">Monitoring seluruh SPK penanganan lapangan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('work-orders/create') ?>" class="cmd-item" data-search="buat work order baru spk perintah kerja yantek har">
                    <span class="cmd-dot" style="background: #ef4444;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Buat Work Order Baru</span>
                        <span class="cmd-item-sub">Terbitkan perintah kerja regu pemeliharaan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('work-orders?status=AKTIF') ?>" class="cmd-item" data-search="work order aktif progress pengerjaan berjalan tim">
                    <span class="cmd-dot" style="background: #3b82f6;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Work Order Aktif</span>
                        <span class="cmd-item-sub">SPK sedang dalam pengerjaan tim yantek</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('pekerjaan') ?>" class="cmd-item" data-search="pekerjaan melewati sla overdue terlambat eskalasi segera">
                    <span class="cmd-dot" style="background: #dc2626;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Pekerjaan Melewati SLA (Overdue)</span>
                        <span class="cmd-item-sub">Daftar SPK yang melewati target waktu SLA</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('pekerjaan/selesai') ?>" class="cmd-item" data-search="pekerjaan selesai verifikasi supervisor tuntas arsip">
                    <span class="cmd-dot" style="background: #10b981;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Pekerjaan Selesai Diverifikasi</span>
                        <span class="cmd-item-sub">Arsip pekerjaan yang telah diverifikasi supervisor</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
            </div>

            <!-- Group 4: SPASIAL & GIS MONITORING -->
            <div class="cmd-group-block">
                <div class="cmd-group-header">SPASIAL &amp; GIS MONITORING</div>
                <a href="<?= site_url('gis') ?>" class="cmd-item" data-search="peta spasial gis leaflet maps interaktif sebaran sidoarjo">
                    <span class="cmd-dot" style="background: #0284c7;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Peta Jaringan Spasial GIS</span>
                        <span class="cmd-item-sub">Peta interaktif sebaran anomali se-Sidoarjo</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('gis') ?>" class="cmd-item" data-search="filter temuan penyulang jtm jaringan feeder isolasi">
                    <span class="cmd-dot" style="background: #0284c7;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Filter Temuan per Penyulang</span>
                        <span class="cmd-item-sub">Isolasi jalur distribusi anomali tertentu</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('gis') ?>" class="cmd-item" data-search="filter temuan wilayah ulp sidoarjo kota gedangan candi porong krian">
                    <span class="cmd-dot" style="background: #0284c7;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Filter Temuan per Wilayah ULP</span>
                        <span class="cmd-item-sub">Sidoarjo Kota, Gedangan, Candi, Porong, Krian</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
            </div>

            <!-- Group 5: PLANNING & PENJADWALAN -->
            <div class="cmd-group-block">
                <div class="cmd-group-header">PLANNING &amp; PENJADWALAN</div>
                <a href="<?= site_url('planning') ?>" class="cmd-item" data-search="jadwal planning inspeksi kalender rute rutin target">
                    <span class="cmd-dot" style="background: #3b82f6;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Jadwal Planning Inspeksi</span>
                        <span class="cmd-item-sub">Kalender &amp; rencana inspeksi rutin penyulang</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('planning/create') ?>" class="cmd-item" data-search="buat jadwal planning baru agenda rencana inspeksi tim target">
                    <span class="cmd-dot" style="background: #3b82f6;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Buat Jadwal Planning Baru</span>
                        <span class="cmd-item-sub">Jadwalkan tim inspeksi ke penyulang target</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
            </div>

            <!-- Group 6: ANALITIK, LAPORAN & AUDIT -->
            <div class="cmd-group-block">
                <div class="cmd-group-header">ANALITIK, LAPORAN &amp; AUDIT</div>
                <a href="<?= site_url('executive-dashboard') ?>" class="cmd-item" data-search="executive analytics analisis sla saidi saifi pimpinan performa ulp">
                    <span class="cmd-dot" style="background: #10b981;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Executive Analytics Dashboard</span>
                        <span class="cmd-item-sub">Ringkasan performa pimpinan &amp; metrik SLA</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan/export-excel') ?>" class="cmd-item" data-search="ekspor data temuan excel xlsx download unduh rekap spreadsheet">
                    <span class="cmd-dot" style="background: #10b981;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Ekspor Data Temuan Excel (XLSX)</span>
                        <span class="cmd-item-sub">Unduh spreadsheet rekapan temuan lapangan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('temuan/export-pdf') ?>" class="cmd-item" data-search="cetak laporan pdf resmi unduh dokumen cetak pimpinan">
                    <span class="cmd-dot" style="background: #ef4444;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Cetak Laporan PDF Resmi</span>
                        <span class="cmd-item-sub">Format laporan siap cetak untuk manajemen</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('audit-log') ?>" class="cmd-item" data-search="audit log riwayat aktivitas sistem jejak logbook user perubahan transaksi">
                    <span class="cmd-dot" style="background: #64748b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Audit Log &amp; Riwayat Sistem</span>
                        <span class="cmd-item-sub">Rekam jejak aktivitas pengguna &amp; perubahan data</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('notifikasi') ?>" class="cmd-item" data-search="pusat notifikasi alert peringatan pengumuman broadcast info">
                    <span class="cmd-dot" style="background: #f59e0b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Pusat Notifikasi &amp; Alert</span>
                        <span class="cmd-item-sub">Daftar peringatan batas waktu &amp; update sistem</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
            </div>

            <!-- Group 7: MASTER DATA & ADMINISTRASI -->
            <div class="cmd-group-block">
                <div class="cmd-group-header">MASTER DATA &amp; ADMINISTRASI</div>
                <a href="<?= site_url('master-assets') ?>" class="cmd-item" data-search="master data aset gardu tiang transformator penyulang distribusi">
                    <span class="cmd-dot" style="background: #64748b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Master Asset PLN Distribusi</span>
                        <span class="cmd-item-sub">Inventaris GI, penyulang, tiang &amp; transformator</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('master-penyulang') ?>" class="cmd-item" data-search="master data penyulang feeder kode panjang jaringan gardu">
                    <span class="cmd-dot" style="background: #64748b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Master Data Penyulang</span>
                        <span class="cmd-item-sub">Database kode penyulang &amp; panjang jaringan</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('user') ?>" class="cmd-item" data-search="manajemen pengguna user role akun hak akses password regu inspeksi">
                    <span class="cmd-dot" style="background: #0284c7;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Manajemen Pengguna (User Management)</span>
                        <span class="cmd-item-sub">Pengaturan akun, tim inspeksi &amp; hak akses role</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('setting') ?>" class="cmd-item" data-search="pengaturan sistem setting konfigurasi parameter target pengumuman motivasi">
                    <span class="cmd-dot" style="background: #64748b;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Pengaturan Sistem &amp; Parameter</span>
                        <span class="cmd-item-sub">Konfigurasi kuota, pengumuman &amp; parameter app</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
                <a href="<?= site_url('panduan') ?>" class="cmd-item" data-search="panduan petunjuk penggunaan manual sop dokumentasi tutorial">
                    <span class="cmd-dot" style="background: #10b981;"></span>
                    <div class="cmd-item-info">
                        <span class="cmd-item-title">Panduan Penggunaan &amp; SOP</span>
                        <span class="cmd-item-sub">Buku panduan operasional aplikasi SIDAK TEJO</span>
                    </div>
                    <i class="fas fa-chevron-right cmd-chevron ms-2"></i>
                </a>
            </div>
        </div>

        <!-- Footer Shortcuts -->
        <div class="cmd-palette-footer">
            <div class="d-flex align-items-center gap-2">
                <span><kbd class="cmd-shortcut-badge">&uarr;</kbd> <kbd class="cmd-shortcut-badge">&darr;</kbd> Navigasi</span>
                <span>&bull;</span>
                <span><kbd class="cmd-shortcut-badge">&crarr;</kbd> Pilih</span>
                <span>&bull;</span>
                <span><kbd class="cmd-shortcut-badge">ESC</kbd> Tutup</span>
            </div>
            <span class="fw-semibold text-muted">SIDAK TEJO &bull; PLN UP3 Sidoarjo</span>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Phase 2E Constellation Preview & Command Palette Engine
var activePreviewTimeout = null;
var currentSelectedNode = 'dashboard';

window.showNodePreview = function(nodeKey) {
    if (activePreviewTimeout) {
        clearTimeout(activePreviewTimeout);
        activePreviewTimeout = null;
    }
    var views = document.querySelectorAll('.contextual-view-block');
    views.forEach(function(v) { v.style.display = 'none'; });
    var target = document.getElementById('view-' + nodeKey);
    if (target) {
        target.style.display = 'block';
    } else {
        var fallback = document.getElementById('view-dashboard');
        if (fallback) fallback.style.display = 'block';
    }

    // Highlight connecting line
    document.querySelectorAll('.constellation-svg-line').forEach(function(l) {
        l.classList.remove('highlighted');
    });
    var line = document.getElementById('svg-line-' + nodeKey);
    if (line) line.classList.add('highlighted');

    // Highlight node
    document.querySelectorAll('.constellation-node, .constellation-central-node').forEach(function(n) {
        n.classList.remove('active-hover');
    });
    var nodeEl = document.getElementById('node-' + nodeKey) || document.getElementById('node-hub-' + nodeKey);
    if (nodeEl) nodeEl.classList.add('active-hover');
};

window.scheduleResetPreview = function() {
    if (activePreviewTimeout) clearTimeout(activePreviewTimeout);
    activePreviewTimeout = setTimeout(function() {
        showNodePreview(currentSelectedNode);
    }, 320);
};

window.selectNode = function(nodeKey) {
    currentSelectedNode = nodeKey;
    showNodePreview(nodeKey);
};

// Command Palette Search Filter & Keyboard Navigation
window.filterCommandPalette = function(query) {
    var q = (query || '').toLowerCase().trim();
    var items = document.querySelectorAll('#cmd-palette-results .cmd-item');
    var groups = document.querySelectorAll('#cmd-palette-results .cmd-group-block');

    items.forEach(function(item) {
        var text = (item.getAttribute('data-search') || item.innerText).toLowerCase();
        if (!q || text.indexOf(q) !== -1) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });

    // Hide empty group headers
    groups.forEach(function(grp) {
        var visibleItems = grp.querySelectorAll('.cmd-item[style*="display: flex"]');
        if (q && visibleItems.length === 0) {
            grp.style.display = 'none';
        } else {
            grp.style.display = 'block';
        }
    });

    // Set active item to first visible
    items.forEach(function(i) { i.classList.remove('active'); });
    var firstVisible = document.querySelector('#cmd-palette-results .cmd-item[style*="display: flex"]');
    if (firstVisible) firstVisible.classList.add('active');
};

window.handleCommandPaletteKey = function(e) {
    var modal = document.getElementById('sidak-command-palette-modal');
    if (!modal || modal.style.display === 'none') return;

    var visibleItems = Array.from(document.querySelectorAll('#cmd-palette-results .cmd-item[style*="display: flex"]'));
    if (visibleItems.length === 0) return;

    var activeIndex = visibleItems.findIndex(function(el) { return el.classList.contains('active'); });

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (activeIndex !== -1) visibleItems[activeIndex].classList.remove('active');
        var nextIndex = (activeIndex + 1) % visibleItems.length;
        visibleItems[nextIndex].classList.add('active');
        visibleItems[nextIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (activeIndex !== -1) visibleItems[activeIndex].classList.remove('active');
        var prevIndex = (activeIndex - 1 + visibleItems.length) % visibleItems.length;
        visibleItems[prevIndex].classList.add('active');
        visibleItems[prevIndex].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex !== -1 && visibleItems[activeIndex]) {
            visibleItems[activeIndex].click();
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        closeCommandPalette();
    }
};

document.addEventListener("DOMContentLoaded", function() {
    // Realtime Clock
    setInterval(function() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        var clockEl = document.getElementById('emc-clock');
        if (clockEl) clockEl.innerText = h + ':' + m + ':' + s + ' WIB';
    }, 1000);

    window.editMotivation = function() {
        var currentText = ($('#permanent-motivation-text').text() || '').replace(/^"|"$/g, '').trim();
        var input = prompt("Masukkan Kata-Kata Motivasi Admin Baru (Disimpan Permanen ke Database):", currentText);
        if (input !== null && input.trim() !== '') {
            const newText = input.trim();
            $.ajax({
                url: '<?= site_url('setting/update-announcement') ?>',
                type: 'POST',
                data: {
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>',
                    'daily_motivation': newText
                },
                dataType: 'JSON',
                success: function(res) {
                    if (res && res.success) {
                        var motivEl = document.getElementById('permanent-motivation-text');
                        if (motivEl) motivEl.innerText = '"' + newText + '"';
                        var mMotivEl = document.getElementById('m-permanent-motivation');
                        if (mMotivEl) mMotivEl.innerText = '"' + newText + '"';
                        var tickerEl = document.getElementById('running-announcement-text');
                        if (tickerEl) tickerEl.innerText = newText;
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Motivasi Admin Saved!',
                                text: 'Kata-kata motivasi harian berhasil tersimpan permanen di database server.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    } else {
                        alert(res ? res.message : 'Gagal menyimpan motivasi.');
                    }
                },
                error: function(xhr) {
                    alert('Gagal terhubung ke server untuk menyimpan motivasi.');
                }
            });
        }
    };

    // Mini GIS Map
    var miniMapEl = document.getElementById('emc-mini-map');
    if (miniMapEl && typeof L !== 'undefined') {
        var map = L.map('emc-mini-map').setView([-7.4478, 112.7183], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var pins = <?= json_encode($mapPins ?? []) ?>;
        pins.forEach(function(p) {
            if (p.latitude && p.longitude) {
                var color = '#10b981'; // Hijau / Normal
                if (p.prioritas === 'EMERGENCY') color = '#ef4444'; // Merah
                else if (p.prioritas === 'HIGH') color = '#f59e0b'; // Kuning

                var circle = L.circleMarker([p.latitude, p.longitude], {
                    radius: 7, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
                }).addTo(map);

                var tipText = '<strong>' + (p.nomor_temuan || ('Temuan #' + p.id)) + '</strong><br><span style="font-size:11px;">Prioritas: ' + (p.prioritas || '-') + '</span>';
                circle.bindTooltip(tipText, { direction: 'top', offset: [0, -4] });

                circle.on('click', function() {
                    window.location.href = "<?= site_url('temuan/detail/') ?>" + p.id;
                });
            }
        });
    }
});
</script>
<?= $this->endSection() ?>
