<?php
/**
 * Shared accessible chart drill-down modal for `/gangguan` and `/dashboard`.
 * Displays real aggregated breakdowns, Pareto cumulative sequence, and matching events.
 */
?>
<div class="modal fade" id="gangguanDrilldownModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="gangguanDrilldownTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light py-3 px-4">
                <div class="d-flex align-items-start gap-2.5 pe-3">
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-3 mt-0.5"><i class="fas fa-chart-simple fs-6" aria-hidden="true"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0.5" id="gangguanDrilldownTitle" style="font-size: 1rem;">Detail Analitik Gangguan</h5>
                        <div class="text-muted small" id="gangguanDrilldownSubtitle" style="font-size: 12px;">Memuat rincian data...</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup modal detail" style="min-width: 44px; min-height: 44px;"></button>
            </div>
            <div class="modal-body p-3 p-md-4" id="gangguanDrilldownBody">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border text-primary mb-2" role="status"><span class="visually-hidden">Memuat...</span></div>
                    <div class="small">Mengambil detail kejadian riil...</div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light py-2 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-muted small" id="gangguanDrilldownFooterNote" style="font-size: 11.5px;"></span>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal" style="min-height: 44px;">
                    <i class="fas fa-times me-1" aria-hidden="true"></i>Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    if (window.openGangguanDrilldown) return;

    var lastTriggerEl = null;
    var pendingEscapeClose = false;
    var pendingOpenOpts = null;
    var modalEl = document.getElementById('gangguanDrilldownModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            if (pendingEscapeClose) {
                pendingEscapeClose = false;
                try { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } catch (e) {}
                return;
            }
            var closeBtn = modalEl.querySelector('.btn-close');
            if (closeBtn && typeof closeBtn.focus === 'function') {
                try { closeBtn.focus({ preventScroll: true }); } catch (e) {}
            }
        });
        modalEl.addEventListener('hidden.bs.modal', function() {
            pendingEscapeClose = false;
            if (pendingOpenOpts) {
                var nextOpts = pendingOpenOpts;
                pendingOpenOpts = null;
                setTimeout(function() { window.openGangguanDrilldown(nextOpts); }, 0);
                return;
            }
            if (lastTriggerEl && typeof lastTriggerEl.focus === 'function' && lastTriggerEl.tagName !== 'CANVAS') {
                try { lastTriggerEl.focus({ preventScroll: true }); } catch (e) {}
            }
            lastTriggerEl = null;
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalEl.classList.contains('show')) {
                var inst = bootstrap.Modal.getOrCreateInstance(modalEl);
                if (inst && inst._isTransitioning) {
                    pendingEscapeClose = true;
                } else if (inst) {
                    inst.hide();
                }
            }
        });
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderChips(title, items) {
        if (!Array.isArray(items) || !items.length) return '';
        var pills = items.map(function(it) {
            return '<span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 fw-normal" style="font-size: 11.5px;">' +
                esc(it.label) + ': <strong class="font-monospace">' + Number(it.count || 0).toLocaleString('id-ID') + '</strong>' +
                ' <span class="text-muted">(' + Number(it.pct || 0).toFixed(1) + '%)</span>' +
            '</span>';
        }).join('');
        return '<div class="mb-3"><div class="fw-bold text-dark mb-1.5" style="font-size: 12px;">' + esc(title) + '</div><div class="d-flex flex-wrap gap-1.5">' + pills + '</div></div>';
    }

    window.openGangguanDrilldown = function(opts) {
        if (!modalEl || typeof bootstrap === 'undefined') return;
        opts = opts || {};
        var modalInst = bootstrap.Modal.getOrCreateInstance(modalEl);
        if (modalInst && modalInst._isTransitioning && !modalEl.classList.contains('show')) {
            pendingOpenOpts = opts;
            return;
        }
        pendingEscapeClose = false;
        pendingOpenOpts = null;
        lastTriggerEl = opts.triggerEl || document.activeElement;

        var titleEl    = document.getElementById('gangguanDrilldownTitle');
        var subtitleEl = document.getElementById('gangguanDrilldownSubtitle');
        var bodyEl     = document.getElementById('gangguanDrilldownBody');
        var footerEl   = document.getElementById('gangguanDrilldownFooterNote');

        if (titleEl) titleEl.textContent = opts.loadingTitle || 'Detail Grafik Gangguan';
        if (subtitleEl) subtitleEl.textContent = opts.loadingSubtitle || 'Mengambil data sesuai filter aktif...';
        if (footerEl) footerEl.textContent = '';
        if (bodyEl) {
            bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary mb-2" role="status"><span class="visually-hidden">Memuat...</span></div><div class="small">Memuat rincian kejadian dari basis data...</div></div>';
        }

        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        var qs = new URLSearchParams();
        Object.keys(opts.params || {}).forEach(function(k) {
            var v = opts.params[k];
            if (v !== null && typeof v !== 'undefined' && String(v) !== '') {
                qs.set(k, String(v));
            }
        });

        var baseEndpoint = opts.endpoint || '<?= site_url('gangguan/drilldown') ?>';
        fetch(baseEndpoint + '?' + qs.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(res) {
                if (res.status !== 'success' || !res.data) {
                    throw new Error(res.message || 'Gagal memuat data detail.');
                }
                var d = res.data;
                if (titleEl) titleEl.textContent = d.title || 'Detail Gangguan';
                if (subtitleEl) subtitleEl.textContent = d.subtitle || '';

                var ctxInfo = d.filter_context || {};
                var bd      = d.breakdowns || {};
                var fc      = bd.fault_current || {};
                var html    = '';

                // KPI summary strip
                html += '<div class="row g-2 mb-3">' +
                    '<div class="col-6 col-md-3"><div class="p-2.5 rounded-3 border bg-light h-100">' +
                        '<div class="text-muted" style="font-size: 11px;">Kejadian Cocok</div>' +
                        '<div class="fs-5 fw-bold text-primary font-monospace">' + Number(d.total_matching || 0).toLocaleString('id-ID') + '</div>' +
                        '<div class="text-muted" style="font-size: 10.5px;">dari ' + Number(d.total_scope || 0).toLocaleString('id-ID') + ' (' + Number(d.share_pct || 0).toFixed(1) + '%)</div>' +
                    '</div></div>' +
                    '<div class="col-6 col-md-3"><div class="p-2.5 rounded-3 border bg-light h-100">' +
                        '<div class="text-muted" style="font-size: 11px;">Periode &amp; Filter</div>' +
                        '<div class="fw-bold text-dark" style="font-size: 12.5px;">' + esc(ctxInfo.year_label || '-') + '</div>' +
                        '<div class="text-muted text-truncate" style="font-size: 10.5px;" title="' + esc((ctxInfo.ulp_label || '') + ' / ' + (ctxInfo.penyulang_label || '')) + '">' + esc(ctxInfo.ulp_label || 'Semua ULP') + ' &bull; ' + esc(ctxInfo.penyulang_label || 'Semua Penyulang') + '</div>' +
                    '</div></div>' +
                    '<div class="col-6 col-md-3"><div class="p-2.5 rounded-3 border bg-light h-100">' +
                        '<div class="text-muted" style="font-size: 11px;">Energi Tak Tersalurkan</div>' +
                        '<div class="fs-5 fw-bold text-dark font-monospace">' + Number(bd.energy_kwh_total || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <span class="fs-6 fw-normal">kWh</span></div>' +
                    '</div></div>' +
                    '<div class="col-6 col-md-3"><div class="p-2.5 rounded-3 border bg-light h-100">' +
                        '<div class="text-muted" style="font-size: 11px;">Arus Gangguan (A)</div>' +
                        (fc.count > 0
                            ? '<div class="fw-bold text-danger font-monospace" style="font-size: 14px;">Rata: ' + esc(fc.avg) + ' A</div><div class="text-muted" style="font-size: 10.5px;">Min ' + esc(fc.min) + ' A &bull; Maks ' + esc(fc.max) + ' A (' + esc(fc.count) + ' trip)</div>'
                            : '<div class="text-muted small mt-1">Tidak tercatat</div>') +
                    '</div></div>' +
                '</div>';

                // Optional Pareto cumulative ordered sequence (when cumulative point clicked)
                if (Array.isArray(d.pareto_sequence) && d.pareto_sequence.length) {
                    html += '<div class="mb-3 border rounded-3 overflow-hidden">' +
                        '<div class="px-3 py-2 bg-warning-subtle border-bottom fw-bold text-dark d-flex justify-content-between align-items-center" style="font-size: 12px;">' +
                            '<span><i class="fas fa-chart-line text-danger me-1" aria-hidden="true"></i>Urutan Kategori s.d. Titik Kumulatif #' + d.pareto_sequence.length + '</span>' +
                            '<span class="font-monospace">Akumulasi: ' + Number(d.pareto_point ? d.pareto_point.cumulative_pct : 0).toFixed(1) + '%</span>' +
                        '</div>' +
                        '<div class="table-responsive" style="max-height: 210px;"><table class="table table-sm table-hover mb-0" style="font-size: 11.5px;">' +
                            '<thead class="table-light sticky-top"><tr><th>#</th><th>Kode Penyebab</th><th>Kategori Kanonik</th><th class="text-end">Jumlah</th><th class="text-end">Kontribusi</th><th class="text-end">Kumulatif</th><th>Status</th></tr></thead><tbody>';
                    d.pareto_sequence.forEach(function(p) {
                        html += '<tr>' +
                            '<td class="fw-bold">' + esc(p.rank) + '</td>' +
                            '<td class="font-monospace fw-semibold">' + esc(p.cause_raw) + '</td>' +
                            '<td class="text-muted">' + esc(p.category_label) + '</td>' +
                            '<td class="text-end font-monospace fw-bold">' + Number(p.count || 0).toLocaleString('id-ID') + '</td>' +
                            '<td class="text-end font-monospace">' + Number(p.pct || 0).toFixed(1) + '%</td>' +
                            '<td class="text-end font-monospace">' + Number(p.cumulative_count || 0).toLocaleString('id-ID') + ' (' + Number(p.cumulative_pct || 0).toFixed(1) + '%)</td>' +
                            '<td>' + (p.is_vital_80 ? '<span class="badge bg-warning-subtle text-warning-emphasis">Vital &le;80%</span>' : '<span class="badge bg-light text-secondary border">Tail &gt;80%</span>') + '</td>' +
                        '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                }

                // Aggregated contextual breakdowns
                html += '<div class="row g-2 mb-2">' +
                    '<div class="col-md-6">' + renderChips('Sebaran ULP', bd.by_ulp) + renderChips('Famili Peralatan', bd.by_equipment_family) + '</div>' +
                    '<div class="col-md-6">' + renderChips('Kategori & Relai', [].concat(bd.by_category || [], bd.by_relay || [])) + '</div>' +
                '</div>';
                html += renderChips('Top Penyulang Terdampak', bd.top_feeders);
                html += renderChips('Kode Penyebab Sumber', bd.top_causes);

                // Event list
                var evs = Array.isArray(d.events) ? d.events : [];
                html += '<div class="border rounded-3 overflow-hidden">' +
                    '<div class="px-3 py-2 bg-light border-bottom fw-bold text-dark d-flex justify-content-between align-items-center" style="font-size: 12px;">' +
                        '<span><i class="fas fa-list me-1" aria-hidden="true"></i>Daftar Kejadian Gangguan</span>' +
                        '<span class="text-muted fw-normal">' + (d.events_capped ? ('Menampilkan ' + evs.length + ' terbaru dari ' + Number(d.total_matching).toLocaleString('id-ID')) : (evs.length + ' kejadian')) + '</span>' +
                    '</div>';

                if (!evs.length) {
                    html += '<div class="p-4 text-center text-muted small">Tidak ada catatan kejadian yang cocok dengan pilihan dan filter aktif.</div>';
                } else {
                    html += '<div class="table-responsive" style="max-height: 280px;"><table class="table table-sm table-hover align-middle mb-0" style="font-size: 11.5px;">' +
                        '<thead class="table-light sticky-top"><tr><th>Tanggal</th><th>ULP</th><th>Penyulang</th><th>Peralatan</th><th>Relai</th><th>Kategori</th><th class="text-end">Arus / kWh</th><th>Penyebab</th><th>Keterangan</th></tr></thead><tbody>';
                    evs.forEach(function(ev) {
                        var hasKwh = (ev.energy_not_supplied_kwh !== null && typeof ev.energy_not_supplied_kwh !== 'undefined' && ev.energy_not_supplied_kwh !== '');
                        var kwhTxt = hasKwh ? (Number(ev.energy_not_supplied_kwh).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kWh') : '-';
                        var metricStr = (ev.fault_current_amperes ? (ev.fault_current_amperes + ' A') : '-') + ' / ' + kwhTxt;
                        var catBadge  = ev.interruption_category === 'PERMANENT'
                            ? '<span class="badge bg-danger-subtle text-danger">Permanen</span>'
                            : (ev.interruption_category === 'TEMPORARY' ? '<span class="badge bg-secondary-subtle text-dark">Temporer</span>' : '<span class="badge bg-light text-muted">Lainnya</span>');
                        html += '<tr>' +
                            '<td class="font-monospace text-nowrap">' + esc(ev.interruption_started_at || ev.event_date) + '</td>' +
                            '<td>' + esc(ev.ulp_name) + '</td>' +
                            '<td class="fw-semibold text-dark">' + esc(ev.feeder_name) + '</td>' +
                            '<td><div>' + esc(ev.device_label) + '</div><div class="text-muted" style="font-size: 10px;">' + esc(ev.equipment_family) + '</div></td>' +
                            '<td class="font-monospace">' + esc(ev.relay_label) + '</td>' +
                            '<td>' + catBadge + '</td>' +
                            '<td class="text-end font-monospace text-nowrap">' + esc(metricStr) + '</td>' +
                            '<td class="font-monospace fw-semibold">' + esc(ev.cause_raw) + '</td>' +
                            '<td class="small text-muted" style="min-width: 170px;">' + esc(ev.field_narrative_raw || '-') + '</td>' +
                        '</tr>';
                    });
                    html += '</tbody></table></div>';
                }
                html += '</div>';

                if (bodyEl) bodyEl.innerHTML = html;
                var syncMeta = res.sync_status || {};
                if (footerEl) {
                    footerEl.textContent = 'Sumber: ' + (syncMeta.source_label || 'Google Sheets') + (syncMeta.last_synced_at ? (' • Terakhir sinkron: ' + syncMeta.last_synced_at) : '');
                }
            })
            .catch(function(err) {
                if (bodyEl) {
                    bodyEl.innerHTML = '<div class="alert alert-warning mb-0 d-flex flex-column gap-2" role="alert">' +
                        '<div><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><strong>Gagal memuat rincian:</strong> ' + esc(err && err.message ? err.message : 'kesalahan jaringan') + '</div>' +
                        '<div><button type="button" class="btn btn-sm btn-outline-dark rounded-pill" id="btnRetryDrilldown">Coba Lagi</button></div>' +
                    '</div>';
                    var rBtn = document.getElementById('btnRetryDrilldown');
                    if (rBtn) rBtn.onclick = function() { window.openGangguanDrilldown(opts); };
                }
            });
    };

    window.bindAccessibleAction = function(el, handler) {
        if (!el || typeof handler !== 'function') return;
        el.style.cursor = 'pointer';
        el.setAttribute('tabindex', '0');
        el.setAttribute('role', 'button');
        el.addEventListener('click', handler);
        el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handler.call(el, e);
            }
        });
    };
})();
</script>
