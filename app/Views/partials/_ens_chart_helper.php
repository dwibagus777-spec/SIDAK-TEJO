<?php
/**
 * Shared responsive Chart.js ENS (kWh) renderer for `/gangguan` and `/dashboard`.
 * Renders monthly comparison & annual comparison bars with crisp, non-overlapping
 * direct Indonesian-formatted `kWh` labels and rich drill-down tooltips.
 */
?>
<style>
    .ens-chart-scroll-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        padding-bottom: 2px;
    }
    .ens-monthly-canvas-box {
        position: relative;
        width: 100%;
        min-width: 0;
        height: 340px;
    }
    @media (max-width: 991.98px) {
        .ens-monthly-canvas-box {
            min-width: 680px;
        }
    }
    .ens-annual-canvas-box {
        position: relative;
        width: 100%;
        height: 215px;
    }
</style>
<script>
(function() {
    if (window.renderSidaktejoEnsCharts) return;

    var chartInstances = new WeakMap();

    function fmtId(val, dec) {
        var d = typeof dec === 'number' ? dec : 2;
        return Number(val || 0).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
    }

    function destroyOn(canvasEl) {
        if (!canvasEl) return;
        var prev = chartInstances.get(canvasEl);
        if (prev && typeof prev.destroy === 'function') {
            try { prev.destroy(); } catch (e) {}
            chartInstances.delete(canvasEl);
        }
        if (typeof Chart !== 'undefined' && typeof Chart.getChart === 'function') {
            var existing = Chart.getChart(canvasEl);
            if (existing) {
                try { existing.destroy(); } catch (e) {}
            }
        }
    }

    var ensMonthlyLabelPlugin = {
        id: 'sidaktejoEnsMonthlyBarLabels',
        afterDatasetsDraw: function(chart) {
            var c = chart.ctx;
            var area = chart.chartArea;
            if (!c || !area) return;

            c.save();
            c.font = '700 9.5px "JetBrains Mono", Consolas, Inter, sans-serif';
            c.lineJoin = 'round';

            chart.data.datasets.forEach(function(ds, dsIdx) {
                var meta = chart.getDatasetMeta(dsIdx);
                if (!meta || meta.hidden) return;
                var cntArr = Array.isArray(ds.ensTripCounts) ? ds.ensTripCounts : [];
                var ink = ds.ensLabelColor || '#0f172a';

                meta.data.forEach(function(elem, ptIdx) {
                    var raw = ds.data[ptIdx];
                    if (raw === null || typeof raw === 'undefined' || isNaN(raw)) return;
                    var val = Number(raw);
                    var trips = Number(cntArr[ptIdx] || 0);
                    if (val <= 0 && trips <= 0) return;

                    var txt = fmtId(val, 2) + ' kWh';
                    var textW = c.measureText(txt).width;
                    var aboveBottomY = elem.y - 6;
                    var aboveTopY = aboveBottomY - textW;
                    var barH = Math.max(0, elem.base - elem.y);

                    c.save();
                    if (aboveTopY >= area.top + 2) {
                        c.translate(elem.x, aboveBottomY);
                        c.rotate(-Math.PI / 2);
                        c.textAlign = 'left';
                        c.textBaseline = 'middle';
                        c.lineWidth = 3.2;
                        c.strokeStyle = 'rgba(255, 255, 255, 0.96)';
                        c.strokeText(txt, 0, 0);
                        c.fillStyle = ink;
                        c.fillText(txt, 0, 0);
                    } else if (barH >= textW + 14) {
                        c.translate(elem.x, elem.y + 8);
                        c.rotate(-Math.PI / 2);
                        c.textAlign = 'right';
                        c.textBaseline = 'middle';
                        c.lineWidth = 2.6;
                        c.strokeStyle = 'rgba(15, 23, 42, 0.35)';
                        c.strokeText(txt, 0, 0);
                        c.fillStyle = '#ffffff';
                        c.fillText(txt, 0, 0);
                    } else {
                        c.translate(elem.x, Math.max(area.top + 4 + textW, elem.y - 4));
                        c.rotate(-Math.PI / 2);
                        c.textAlign = 'left';
                        c.textBaseline = 'middle';
                        c.lineWidth = 3.2;
                        c.strokeStyle = 'rgba(255, 255, 255, 0.96)';
                        c.strokeText(txt, 0, 0);
                        c.fillStyle = ink;
                        c.fillText(txt, 0, 0);
                    }
                    c.restore();
                });
            });

            c.restore();
        }
    };

    var ensAnnualLabelPlugin = {
        id: 'sidaktejoEnsAnnualBarLabels',
        afterDatasetsDraw: function(chart) {
            var c = chart.ctx;
            var area = chart.chartArea;
            if (!c || !area) return;

            c.save();
            c.textAlign = 'center';
            c.lineJoin = 'round';

            chart.data.datasets.forEach(function(ds, dsIdx) {
                var meta = chart.getDatasetMeta(dsIdx);
                if (!meta || meta.hidden) return;
                var slotW = (area.right - area.left) / Math.max(1, meta.data.length);
                var inks = Array.isArray(ds.ensLabelColors) ? ds.ensLabelColors : [];

                meta.data.forEach(function(elem, ptIdx) {
                    var raw = ds.data[ptIdx];
                    if (raw === null || typeof raw === 'undefined' || isNaN(raw)) return;
                    var val = Number(raw);
                    var numTxt = fmtId(val, 2);
                    var fullTxt = numTxt + ' kWh';
                    var ink = inks[ptIdx] || '#0f172a';

                    c.font = '700 10.5px "JetBrains Mono", Consolas, Inter, sans-serif';
                    var fullW = c.measureText(fullTxt).width;
                    c.lineWidth = 3.2;
                    c.strokeStyle = 'rgba(255, 255, 255, 0.96)';
                    c.fillStyle = ink;

                    var fitsHorizontal = (fullW <= slotW - 16)
                        && ((elem.x - fullW / 2) >= 8)
                        && ((elem.x + fullW / 2) <= chart.width - 8);
                    if (fitsHorizontal) {
                        var ySingle = Math.max(area.top + 12, elem.y - 6);
                        c.textBaseline = 'bottom';
                        c.strokeText(fullTxt, elem.x, ySingle);
                        c.fillText(fullTxt, elem.x, ySingle);
                    } else {
                        var yUnit = Math.max(area.top + 22, elem.y - 5);
                        var yNum = yUnit - 12;
                        c.textBaseline = 'bottom';
                        c.font = '700 10px "JetBrains Mono", Consolas, Inter, sans-serif';
                        c.strokeText(numTxt, elem.x, yNum);
                        c.fillText(numTxt, elem.x, yNum);

                        c.font = '700 9px Inter, sans-serif';
                        c.strokeText('kWh', elem.x, yUnit);
                        c.fillText('kWh', elem.x, yUnit);
                    }
                });
            });

            c.restore();
        }
    };

    window.renderSidaktejoEnsCharts = function(cfg) {
        if (typeof Chart === 'undefined' || !cfg) return null;

        var monthLabels = Array.isArray(cfg.monthLabels) && cfg.monthLabels.length === 12
            ? cfg.monthLabels
            : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        var yrA     = Number(cfg.yearA);
        var yrB     = Number(cfg.yearB);
        var scope   = String(cfg.scope || 'year_a');
        var arrA    = Array.isArray(cfg.monthlyA) ? cfg.monthlyA : [];
        var arrB    = Array.isArray(cfg.monthlyB) ? cfg.monthlyB : [];
        var cntA    = Array.isArray(cfg.monthlyCntA) ? cfg.monthlyCntA : [];
        var cntB    = Array.isArray(cfg.monthlyCntB) ? cfg.monthlyCntB : [];
        var totA    = Number(cfg.totalA || 0);
        var totB    = Number(cfg.totalB || 0);
        var combTot = Number(typeof cfg.combinedTotal === 'number' ? cfg.combinedTotal : (yrA === yrB ? totA : (totA + totB)));
        var totCntA = Number(cfg.countA || 0);
        var totCntB = Number(cfg.countB || 0);
        var avgA    = Number(cfg.avgA || 0);
        var avgB    = Number(cfg.avgB || 0);
        var syncErr = cfg.syncError ? String(cfg.syncError) : '';

        var colorA   = cfg.colorA || 'rgba(13, 110, 253, 0.86)';
        var colorB   = cfg.colorB || 'rgba(100, 116, 139, 0.72)';
        var colorCmb = cfg.colorCombined || 'rgba(22, 163, 74, 0.86)';
        var inkA     = cfg.inkA || '#1e40af';
        var inkB     = cfg.inkB || '#475569';
        var inkCmb   = cfg.inkCombined || '#15803d';

        var setHover = function(evt, elements) {
            if (evt && evt.native && evt.native.target) {
                evt.native.target.style.cursor = (elements && elements.length) ? 'pointer' : 'default';
            }
        };

        var hasAnyData = (totA > 0 || totB > 0);
        var applyEmptyOverlay = function(el, defaultText) {
            if (!el) return;
            el.classList.toggle('d-none', hasAnyData);
            if (!hasAnyData) {
                el.innerHTML = syncErr
                    ? '<i class="fas fa-triangle-exclamation text-warning me-1"></i>Pembaruan Google Sheets gagal (' + syncErr.replace(/</g, '&lt;') + '). ' + defaultText
                    : '<i class="fas fa-circle-info text-muted me-1"></i>' + defaultText;
            }
        };

        applyEmptyOverlay(cfg.monthlyOverlay, 'Belum ada data ENS bulanan untuk filter ini.');
        applyEmptyOverlay(cfg.annualOverlay, 'Belum ada total ENS tahunan.');

        // 1. Monthly Comparison Chart (with direct non-overlapping -90deg labels)
        var moInst = null;
        if (cfg.monthlyCanvas) {
            destroyOn(cfg.monthlyCanvas);
            var maxMo = Math.max.apply(null, [0].concat(
                arrA.filter(function(v) { return v !== null && !isNaN(v); }).map(Number),
                arrB.filter(function(v) { return v !== null && !isNaN(v); }).map(Number)
            ));
            moInst = new Chart(cfg.monthlyCanvas, {
                type: 'bar',
                plugins: [ensMonthlyLabelPlugin],
                data: {
                    labels: monthLabels,
                    datasets: [
                        {
                            label: yrA + ' (' + fmtId(totA, 2) + ' kWh)',
                            data: arrA,
                            ensTripCounts: cntA,
                            ensYear: yrA,
                            ensYearTotal: totA,
                            ensLabelColor: inkA,
                            backgroundColor: colorA,
                            borderRadius: 4,
                            categoryPercentage: 0.78,
                            barPercentage: 0.84
                        },
                        {
                            label: yrB + ' (' + fmtId(totB, 2) + ' kWh)',
                            data: arrB,
                            ensTripCounts: cntB,
                            ensYear: yrB,
                            ensYearTotal: totB,
                            ensLabelColor: inkB,
                            backgroundColor: colorB,
                            borderRadius: 4,
                            categoryPercentage: 0.78,
                            barPercentage: 0.84
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: setHover,
                    onClick: function(evt, elements, chart) {
                        if (typeof cfg.onBarClick !== 'function') return;
                        var hits = (elements && elements.length) ? elements : chart.getElementsAtEventForMode(evt, 'index', { intersect: false }, true);
                        if (!hits || !hits.length) return;
                        var h = hits[0];
                        var yrTgt = (h.datasetIndex === 1) ? yrB : yrA;
                        cfg.onBarClick('ens_month', String(h.index + 1), yrTgt, cfg.monthlyCanvas);
                    },
                    interaction: { mode: 'index', intersect: false },
                    layout: { padding: { top: 18, right: 10, left: 4, bottom: 2 } },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'start',
                            labels: { boxWidth: 10, boxHeight: 10, padding: 12, font: { size: 10.5, weight: '600' } }
                        },
                        tooltip: {
                            callbacks: {
                                title: function(items) {
                                    if (!items || !items.length) return '';
                                    return 'Bulan ' + items[0].label + ' (' + yrA + ' vs ' + yrB + ')';
                                },
                                label: function(tc) {
                                    var ds = tc.dataset;
                                    var yr = ds.ensYear || '';
                                    if (tc.raw === null || typeof tc.raw === 'undefined') {
                                        return 'Tahun ' + yr + ': Belum ada data';
                                    }
                                    var val = Number(tc.raw || 0);
                                    var trips = (ds.ensTripCounts && ds.ensTripCounts[tc.dataIndex]) ? Number(ds.ensTripCounts[tc.dataIndex]) : 0;
                                    var yrTot = Number(ds.ensYearTotal || 0);
                                    var share = yrTot > 0 ? ((val / yrTot) * 100).toFixed(1) : '0.0';
                                    var parts = [fmtId(val, 2) + ' kWh'];
                                    if (trips > 0) {
                                        parts.push(trips.toLocaleString('id-ID') + ' trip');
                                        parts.push('porsi ' + share + '%');
                                        parts.push('rata ' + fmtId(val / trips, 2) + ' kWh/trip');
                                    } else if (yrTot > 0) {
                                        parts.push('porsi ' + share + '%');
                                    }
                                    return 'Tahun ' + yr + ': ' + parts.join(' • ');
                                },
                                afterBody: function() {
                                    return ['Klik batang untuk rincian trip pembentuk ENS'];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { autoSkip: false, maxRotation: 0, minRotation: 0, font: { size: 10.5, weight: '600' } }
                        },
                        y: {
                            beginAtZero: true,
                            suggestedMax: maxMo > 0 ? Math.ceil(maxMo * 1.78) : 10,
                            ticks: {
                                maxTicksLimit: 6,
                                font: { size: 10 },
                                callback: function(v) {
                                    if (v >= 1000) return (v / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' rb';
                                    return Number(v).toLocaleString('id-ID');
                                }
                            },
                            title: { display: true, text: 'ENS (kWh)', font: { size: 10, weight: 'bold' } },
                            grid: { color: 'rgba(148, 163, 184, 0.16)' }
                        }
                    }
                }
            });
            chartInstances.set(cfg.monthlyCanvas, moInst);
        }

        // 2. Annual Comparison Chart (with direct `kWh` values on each bar)
        var yrInst = null;
        if (cfg.annualCanvas) {
            destroyOn(cfg.annualCanvas);
            var yrKeys   = [yrA, yrB];
            var yrLabels = ['Tahun ' + yrA, 'Tahun ' + yrB];
            var yrVals   = [Number(totA.toFixed(2)), Number(totB.toFixed(2))];
            var yrColors = [colorA, colorB];
            var yrInks   = [inkA, inkB];
            var yrTrips  = [totCntA, totCntB];
            var yrAvgs   = [avgA, avgB];

            if (scope === 'combined' && yrA !== yrB) {
                var combCnt = totCntA + totCntB;
                yrKeys.push('COMBINED');
                yrLabels.push('Gabungan');
                yrVals.push(Number(combTot.toFixed(2)));
                yrColors.push(colorCmb);
                yrInks.push(inkCmb);
                yrTrips.push(combCnt);
                yrAvgs.push(combCnt > 0 ? (combTot / combCnt) : 0);
            }

            var maxAnn = Math.max.apply(null, [0].concat(yrVals));
            yrInst = new Chart(cfg.annualCanvas, {
                type: 'bar',
                plugins: [ensAnnualLabelPlugin],
                data: {
                    labels: yrLabels,
                    datasets: [{
                        label: 'Total ENS Tahunan (kWh)',
                        data: yrVals,
                        ensLabelColors: yrInks,
                        backgroundColor: yrColors,
                        borderRadius: 6,
                        maxBarThickness: 58
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: setHover,
                    onClick: function(evt, elements, chart) {
                        if (typeof cfg.onBarClick !== 'function') return;
                        var hits = (elements && elements.length) ? elements : chart.getElementsAtEventForMode(evt, 'nearest', { intersect: false }, true);
                        if (!hits || !hits.length) return;
                        var k = yrKeys[hits[0].index];
                        if (k === 'COMBINED') {
                            cfg.onBarClick('ens_year', 'COMBINED', null, cfg.annualCanvas);
                        } else if (k) {
                            cfg.onBarClick('ens_year', String(k), k, cfg.annualCanvas);
                        }
                    },
                    layout: { padding: { top: 30, right: 14, left: 14, bottom: 2 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(tc) {
                                    return 'Total ENS: ' + fmtId(tc.raw || 0, 2) + ' kWh';
                                },
                                afterLabel: function(tc) {
                                    var i = tc.dataIndex;
                                    var tCnt = Number(yrTrips[i] || 0);
                                    var tAvg = Number(yrAvgs[i] || 0);
                                    var out = [];
                                    if (tCnt > 0) {
                                        out.push(tCnt.toLocaleString('id-ID') + ' trip • rata ' + fmtId(tAvg, 2) + ' kWh/trip');
                                    }
                                    out.push('Sama persis dengan penjumlahan ENS bulanan');
                                    out.push('Klik batang untuk rincian ENS tahun ini');
                                    return out;
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11, weight: '700' } } },
                        y: {
                            beginAtZero: true,
                            suggestedMax: maxAnn > 0 ? Math.ceil(maxAnn * 1.32) : 10,
                            ticks: {
                                maxTicksLimit: 5,
                                font: { size: 10 },
                                callback: function(v) {
                                    if (v >= 1000) return (v / 1000).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb';
                                    return Number(v).toLocaleString('id-ID');
                                }
                            },
                            grid: { color: 'rgba(148, 163, 184, 0.16)' }
                        }
                    }
                }
            });
            chartInstances.set(cfg.annualCanvas, yrInst);
        }

        return { monthlyChart: moInst, annualChart: yrInst };
    };

    function drawRoundedRect(c, x, y, w, h, r) {
        var rad = Math.min(r, w / 2, h / 2);
        c.beginPath();
        c.moveTo(x + rad, y);
        c.lineTo(x + w - rad, y);
        c.quadraticCurveTo(x + w, y, x + w, y + rad);
        c.lineTo(x + w, y + h - rad);
        c.quadraticCurveTo(x + w, y + h, x + w - rad, y + h);
        c.lineTo(x + rad, y + h);
        c.quadraticCurveTo(x, y + h, x, y + h - rad);
        c.lineTo(x, y + rad);
        c.quadraticCurveTo(x, y, x + rad, y);
        c.closePath();
    }

    function boxesOverlap(a, b, pad) {
        var p = typeof pad === 'number' ? pad : 4;
        return Math.abs(a.x - b.x) < ((a.w + b.w) / 2 + p) &&
               Math.abs(a.y - b.y) < ((a.h + b.h) / 2 + p);
    }

    var doughnutDirectLabelPlugin = {
        id: 'sidaktejoDoughnutDirectLabels',
        afterDatasetsDraw: function(chart) {
            var c = chart.ctx;
            var area = chart.chartArea;
            if (!c || !area) return;
            var meta = chart.getDatasetMeta(0);
            if (!meta || meta.hidden || !meta.data || !meta.data.length) return;

            var ds = chart.data.datasets[0] || {};
            var rawData = Array.isArray(ds.data) ? ds.data : [];
            var pctData = Array.isArray(ds.sidaktejoPcts) ? ds.sidaktejoPcts : [];
            var colors  = Array.isArray(ds.backgroundColor) ? ds.backgroundColor : [];
            var inks    = Array.isArray(ds.sidaktejoLabelInks) ? ds.sidaktejoLabelInks : [];
            var total   = typeof ds.sidaktejoTotal === 'number'
                ? ds.sidaktejoTotal
                : rawData.reduce(function(acc, v) { return acc + Number(v || 0); }, 0);

            var firstArc = meta.data[0];
            var cx = firstArc.x;
            var cy = firstArc.y;
            var innerR = firstArc.innerRadius || 0;

            c.save();

            // 1. Center total summary inside doughnut hole
            if (innerR >= 24 && total > 0) {
                c.textAlign = 'center';
                c.textBaseline = 'middle';
                c.fillStyle = '#64748b';
                c.font = '700 8.5px Inter, sans-serif';
                c.fillText('TOTAL', cx, cy - 11);

                c.fillStyle = '#0f172a';
                c.font = '800 13.5px "JetBrains Mono", Consolas, Inter, sans-serif';
                c.fillText(Number(total).toLocaleString('id-ID'), cx, cy + 1);

                c.fillStyle = '#64748b';
                c.font = '600 8.5px Inter, sans-serif';
                c.fillText('kejadian', cx, cy + 12);
            }

            var centerBox = { x: cx, y: cy, w: Math.max(44, innerR * 1.45), h: 34 };
            var pillBoxes = [];

            meta.data.forEach(function(arc, idx) {
                var val = Number(rawData[idx] || 0);
                if (val <= 0 || arc.hidden) return;
                var pct = (typeof pctData[idx] === 'number')
                    ? Number(pctData[idx])
                    : (total > 0 ? Number(((val / total) * 100).toFixed(1)) : 0);

                var valStr = val.toLocaleString('id-ID');
                var pctStr = pct.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
                var span   = Math.abs((arc.endAngle || 0) - (arc.startAngle || 0));
                var midA   = ((arc.startAngle || 0) + (arc.endAngle || 0)) / 2;

                c.font = '800 10.5px "JetBrains Mono", Consolas, Inter, sans-serif';
                var w1 = c.measureText(valStr).width;
                c.font = '700 9.5px "JetBrains Mono", Consolas, Inter, sans-serif';
                var w2 = c.measureText(pctStr).width;

                var bw = Math.ceil(Math.max(w1, w2)) + 12;
                var bh = 28;
                var r  = span < 0.45
                    ? (arc.outerRadius * 0.86)
                    : (arc.innerRadius + (arc.outerRadius - arc.innerRadius) * 0.54);

                var px = cx + Math.cos(midA) * r;
                var py = cy + Math.sin(midA) * r;

                pillBoxes.push({
                    idx: idx,
                    x: px,
                    y: py,
                    midA: midA,
                    w: bw,
                    h: bh,
                    val: val,
                    pct: pct,
                    valStr: valStr,
                    pctStr: pctStr,
                    summary: valStr + ' (' + pctStr + ')',
                    borderColor: colors[idx] || '#475569',
                    inkColor: inks[idx] || '#0f172a'
                });
            });

            // 2. Multi-pass collision avoidance against center hole & sibling pills + bounds clamping
            var minX = 4;
            var maxX = chart.width - 4;
            var minY = 4;
            var maxY = area.bottom - 2;

            for (var pass = 0; pass < 4; pass++) {
                pillBoxes.forEach(function(b) {
                    if (boxesOverlap(b, centerBox, 3)) {
                        var dirX = Math.cos(b.midA);
                        var dirY = Math.sin(b.midA);
                        b.x += dirX * 10;
                        b.y += dirY * 10;
                    }
                });
                for (var i = 0; i < pillBoxes.length; i++) {
                    for (var j = i + 1; j < pillBoxes.length; j++) {
                        var a = pillBoxes[i];
                        var b = pillBoxes[j];
                        if (boxesOverlap(a, b, 4)) {
                            var dy = (b.y >= a.y ? 1 : -1) * (((a.h + b.h) / 2 + 5 - Math.abs(b.y - a.y)) / 2);
                            a.y -= dy;
                            b.y += dy;
                        }
                    }
                }
                pillBoxes.forEach(function(b) {
                    b.x = Math.max(minX + b.w / 2, Math.min(maxX - b.w / 2, b.x));
                    b.y = Math.max(minY + b.h / 2, Math.min(maxY - b.h / 2, b.y));
                });
            }

            // 3. Draw high-contrast rounded pill badges with count + percentage
            pillBoxes.forEach(function(b) {
                var left = b.x - b.w / 2;
                var top  = b.y - b.h / 2;

                c.save();
                c.shadowColor = 'rgba(15, 23, 42, 0.14)';
                c.shadowBlur = 4;
                c.shadowOffsetY = 1;
                drawRoundedRect(c, left, top, b.w, b.h, 6);
                c.fillStyle = 'rgba(255, 255, 255, 0.96)';
                c.fill();
                c.shadowColor = 'transparent';
                c.lineWidth = 1.5;
                c.strokeStyle = b.borderColor;
                c.stroke();

                c.textAlign = 'center';
                c.textBaseline = 'middle';
                c.font = '800 10.5px "JetBrains Mono", Consolas, Inter, sans-serif';
                c.fillStyle = '#0f172a';
                c.fillText(b.valStr, b.x, top + 9.5);

                c.font = '700 9.5px "JetBrains Mono", Consolas, Inter, sans-serif';
                c.fillStyle = b.inkColor;
                c.fillText(b.pctStr, b.x, top + 20.5);
                c.restore();
            });

            chart._sidaktejoSliceLabels = pillBoxes;
            c.restore();
        }
    };

    window.renderSidaktejoDoughnutChart = function(cfg) {
        if (typeof Chart === 'undefined' || !cfg || !cfg.canvas) return null;
        destroyOn(cfg.canvas);

        var labels     = Array.isArray(cfg.labels) ? cfg.labels : [];
        var keys       = Array.isArray(cfg.keys) ? cfg.keys : [];
        var counts     = Array.isArray(cfg.counts) ? cfg.counts.map(function(v) { return Number(v || 0); }) : [];
        var total      = typeof cfg.total === 'number'
            ? cfg.total
            : counts.reduce(function(a, b) { return a + b; }, 0);
        var pcts       = Array.isArray(cfg.pcts) && cfg.pcts.length === counts.length
            ? cfg.pcts.map(function(v) { return Number(v || 0); })
            : counts.map(function(v) { return total > 0 ? Number(((v / total) * 100).toFixed(1)) : 0.0; });
        var colors     = Array.isArray(cfg.colors) ? cfg.colors : ['#64748b', '#ef4444', '#0284c7'];
        var inks       = Array.isArray(cfg.labelInks) ? cfg.labelInks : ['#334155', '#b91c1c', '#0369a1'];
        var yrACounts  = Array.isArray(cfg.yearACounts) ? cfg.yearACounts : [];
        var yrBCounts  = Array.isArray(cfg.yearBCounts) ? cfg.yearBCounts : [];
        var yearA      = cfg.yearA || '';
        var yearB      = cfg.yearB || '';
        var focusLabel = cfg.focusLabel || (yearA ? ('Tahun ' + yearA) : '');
        var filterDesc = cfg.filterSummary || 'Semua ULP • Semua Penyulang';

        var setHover = function(evt, elements) {
            if (evt && evt.native && evt.native.target) {
                evt.native.target.style.cursor = (elements && elements.length) ? 'pointer' : 'default';
            }
        };

        var inst = new Chart(cfg.canvas, {
            type: 'doughnut',
            plugins: [doughnutDirectLabelPlugin],
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    sidaktejoPcts: pcts,
                    sidaktejoTotal: total,
                    sidaktejoLabelInks: inks,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: cfg.cutout || '56%',
                layout: { padding: { top: 10, bottom: 6, left: 14, right: 14 } },
                onHover: setHover,
                onClick: function(evt, elements, chart) {
                    if (typeof cfg.onSliceClick !== 'function') return;
                    var hits = (elements && elements.length)
                        ? elements
                        : chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                    if (!hits || !hits.length) return;
                    var idx = hits[0].index;
                    var k = keys[idx] || labels[idx];
                    if (k) cfg.onSliceClick(k, idx, cfg.canvas);
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 11,
                            boxHeight: 11,
                            padding: 10,
                            font: { size: 10.5, weight: '600' },
                            generateLabels: function(chart) {
                                var d = chart.data;
                                if (!d.labels || !d.datasets.length) return [];
                                var ds = d.datasets[0];
                                var meta = chart.getDatasetMeta(0);
                                return d.labels.map(function(lbl, i) {
                                    var v = Number(ds.data[i] || 0);
                                    var p = Number((ds.sidaktejoPcts && ds.sidaktejoPcts[i] !== undefined) ? ds.sidaktejoPcts[i] : (total > 0 ? ((v / total) * 100) : 0));
                                    var pStr = p.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
                                    var arc = meta.data[i];
                                    var hidden = arc ? arc.hidden : false;
                                    return {
                                        text: lbl + ': ' + v.toLocaleString('id-ID') + ' (' + pStr + ')',
                                        fillStyle: ds.backgroundColor[i],
                                        strokeStyle: '#ffffff',
                                        lineWidth: 1,
                                        hidden: hidden,
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(items) {
                                if (!items || !items.length) return '';
                                return items[0].label || '';
                            },
                            label: function(tc) {
                                var i = tc.dataIndex;
                                var v = Number(tc.raw || 0);
                                var p = Number((pcts[i] !== undefined) ? pcts[i] : (total > 0 ? ((v / total) * 100) : 0));
                                var pStr = p.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                                return 'Jumlah: ' + v.toLocaleString('id-ID') + ' dari ' + Number(total).toLocaleString('id-ID') + ' kejadian (' + pStr + '%)';
                            },
                            afterLabel: function(tc) {
                                var i = tc.dataIndex;
                                var lines = [];
                                if (focusLabel) {
                                    lines.push('Periode Fokus: ' + focusLabel);
                                }
                                if (yearA && yearB && yrACounts[i] !== undefined && yrBCounts[i] !== undefined) {
                                    lines.push('Perbandingan: Tahun ' + yearA + ': ' + Number(yrACounts[i]).toLocaleString('id-ID') + ' • Tahun ' + yearB + ': ' + Number(yrBCounts[i]).toLocaleString('id-ID') + ' kejadian');
                                }
                                if (filterDesc) {
                                    lines.push('Filter: ' + filterDesc);
                                }
                                lines.push('Klik segmen untuk menelusuri seluruh rincian kejadian');
                                return lines;
                            }
                        }
                    }
                }
            }
        });

        chartInstances.set(cfg.canvas, inst);
        return inst;
    };
})();
</script>
