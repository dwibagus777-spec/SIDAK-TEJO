<?php
/**
 * Shared accessible chart drill-down modal for `/gangguan` and `/dashboard`.
 * Displays real aggregated breakdowns, Pareto cumulative sequence, and full
 * navigable/searchable matching events (collapsible accordion, explicit scroll container,
 * separated Matched / Loaded / In-Viewport counters, pagination, and "Muat Lagi").
 */
?>
<style>
    #gangguanDrilldownModal .gg-drill-table-scroll {
        max-height: 390px;
        overflow-y: auto !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        position: relative;
        border-radius: 0 !important;
        box-shadow: none !important;
        scrollbar-gutter: stable;
    }
    @media (max-width: 767.98px) {
        #gangguanDrilldownModal .gg-drill-table-scroll {
            max-height: 52vh;
        }
    }
    #gangguanDrilldownModal .gg-drill-table-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 4;
        background-color: #f8fafc !important;
        box-shadow: inset 0 -1px 0 #cbd5e1;
        white-space: nowrap;
    }
    #gangguanDrilldownModal .gg-drill-pareto-scroll {
        max-height: 215px;
        overflow-y: auto !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    #gangguanDrilldownModal .gg-drill-pareto-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background-color: #fef3c7 !important;
        box-shadow: inset 0 -1px 0 #fde68a;
    }
</style>
<div class="modal fade" id="gangguanDrilldownModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="gangguanDrilldownTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light py-2.5 px-4">
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
    var activeDrillState = null;

    var modalEl = document.getElementById('gangguanDrilldownModal');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', function() {
            if (pendingEscapeClose) {
                pendingEscapeClose = false;
                try { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } catch (e) {}
                return;
            }
            updateViewportVisibleRange();
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
                ' <span class="text-muted">(' + Number(it.pct || 0).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%)</span>' +
            '</span>';
        }).join('');
        return '<div class="mb-2.5"><div class="fw-bold text-dark mb-1" style="font-size: 11.5px;">' + esc(title) + '</div><div class="d-flex flex-wrap gap-1.5">' + pills + '</div></div>';
    }

    function renderEventRowHtml(ev, fallbackNum) {
        var rowNum = Number(ev.row_num || fallbackNum || 1);
        var hasKwh = (ev.energy_not_supplied_kwh !== null && typeof ev.energy_not_supplied_kwh !== 'undefined' && ev.energy_not_supplied_kwh !== '');
        var kwhTxt = hasKwh ? (Number(ev.energy_not_supplied_kwh).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kWh') : '-';
        var ampTxt = (ev.fault_current_amperes !== null && typeof ev.fault_current_amperes !== 'undefined' && ev.fault_current_amperes !== '')
            ? (Number(ev.fault_current_amperes).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' A')
            : '-';
        var durTxt = (ev.outage_duration_minutes !== null && typeof ev.outage_duration_minutes !== 'undefined' && Number(ev.outage_duration_minutes) > 0)
            ? ('<div class="text-muted" style="font-size: 10px;">Padam: ' + Number(ev.outage_duration_minutes).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' mnt</div>')
            : '';
        var catBadge = ev.interruption_category === 'PERMANENT'
            ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Permanen</span>'
            : (ev.interruption_category === 'TEMPORARY'
                ? '<span class="badge bg-secondary-subtle text-dark border">Temporer</span>'
                : '<span class="badge bg-light text-muted border">Lainnya</span>');
        var narr = esc(ev.field_narrative_raw || '-');
        var rest = ev.restoration_action_raw ? ('<div class="text-muted mt-0.5" style="font-size: 10px;"><i class="fas fa-wrench me-1" aria-hidden="true"></i>' + esc(ev.restoration_action_raw) + '</div>') : '';

        return '<tr data-row-num="' + rowNum + '">' +
            '<td class="text-muted font-monospace text-center" style="width: 42px;">' + rowNum.toLocaleString('id-ID') + '</td>' +
            '<td class="font-monospace text-nowrap"><div>' + esc(ev.interruption_started_at || ev.event_date) + '</div>' + durTxt + '</td>' +
            '<td>' + esc(ev.ulp_name) + '</td>' +
            '<td class="fw-semibold text-dark">' + esc(ev.feeder_name) + '</td>' +
            '<td><div>' + esc(ev.device_label) + '</div><div class="text-muted" style="font-size: 10px;">' + esc(ev.equipment_family) + (ev.switching_device_type ? (' • ' + esc(ev.switching_device_type)) : '') + '</div></td>' +
            '<td class="font-monospace">' + esc(ev.relay_label) + '</td>' +
            '<td>' + catBadge + '</td>' +
            '<td class="text-end font-monospace text-nowrap"><div>' + esc(ampTxt) + '</div><div class="text-muted" style="font-size: 10.5px;">' + esc(kwhTxt) + '</div></td>' +
            '<td class="font-monospace fw-semibold">' + esc(ev.cause_raw) + '</td>' +
            '<td class="small text-dark" style="min-width: 185px;"><div>' + narr + '</div>' + rest + '</td>' +
        '</tr>';
    }

    function buildQueryUrl(state, overrideOffset, overrideLimit) {
        var qs = new URLSearchParams();
        Object.keys(state.baseParams || {}).forEach(function(k) {
            var v = state.baseParams[k];
            if (v !== null && typeof v !== 'undefined' && String(v) !== '') {
                qs.set(k, String(v));
            }
        });
        qs.set('offset', String(typeof overrideOffset === 'number' ? overrideOffset : state.offset));
        qs.set('limit', String(typeof overrideLimit === 'number' ? overrideLimit : state.limit));
        if (state.search) qs.set('search', state.search);
        if (state.eventCategory) qs.set('event_category', state.eventCategory);
        if (state.eventUlp) qs.set('event_ulp', state.eventUlp);
        return state.baseEndpoint + '?' + qs.toString();
    }

    /**
     * Measures which table rows are actually visible inside `#ggDrillEventsTableWrap`'s
     * scroll viewport right now and updates `#ggDrillStatViewport` + `#ggDrillFooterRangeText`.
     */
    function updateViewportVisibleRange() {
        var wrap = document.getElementById('ggDrillEventsTableWrap');
        var tbody = document.getElementById('ggDrillEventsTbody');
        var vpBadge = document.getElementById('ggDrillStatViewport');
        var footerRange = document.getElementById('ggDrillFooterRangeText');
        if (!wrap || !tbody || !activeDrillState) return;

        var trs = tbody.querySelectorAll('tr[data-row-num]');
        var loadedCnt = trs.length;
        var totalMatch = Number(activeDrillState.totalMatching || 0);
        var filtMatch = Number(typeof activeDrillState.filteredMatching === 'number' ? activeDrillState.filteredMatching : totalMatch);

        if (!loadedCnt || filtMatch === 0) {
            if (vpBadge) vpBadge.textContent = 'Tampil di layar: 0 baris';
            if (footerRange) footerRange.textContent = 'Cocok: ' + filtMatch.toLocaleString('id-ID') + ' • Dimuat: 0 baris • Tampil: 0 baris';
            return;
        }

        var wRect = wrap.getBoundingClientRect();
        var thead = wrap.querySelector('thead');
        var headerH = thead ? thead.getBoundingClientRect().height : 32;
        var viewTop = wRect.top + headerH - 4;
        var viewBottom = wRect.bottom + 4;

        var firstInView = null;
        var lastInView = null;
        var inViewCount = 0;

        for (var i = 0; i < trs.length; i++) {
            var rRect = trs[i].getBoundingClientRect();
            var rMid = (rRect.top + rRect.bottom) / 2;
            if (rRect.height > 0 && rMid >= viewTop && rMid <= viewBottom) {
                var rn = Number(trs[i].getAttribute('data-row-num') || (i + 1));
                if (firstInView === null) firstInView = rn;
                lastInView = rn;
                inViewCount++;
            }
        }

        var loadedFirst = Number(trs[0].getAttribute('data-row-num') || 1);
        var loadedLast = Number(trs[loadedCnt - 1].getAttribute('data-row-num') || loadedCnt);
        if (firstInView === null) {
            firstInView = loadedFirst;
            lastInView = Math.min(loadedLast, loadedFirst + 6);
        }

        var canScrollMoreInLoaded = loadedCnt > inViewCount;
        var vpText = 'Sedang tampil: #' + firstInView.toLocaleString('id-ID') + '–#' + lastInView.toLocaleString('id-ID')
            + (canScrollMoreInLoaded ? (' (gulir tabel untuk #' + loadedFirst.toLocaleString('id-ID') + '–#' + loadedLast.toLocaleString('id-ID') + ')') : '');

        if (vpBadge) {
            vpBadge.textContent = vpText;
        }
        if (footerRange) {
            var hasSub = Boolean(activeDrillState.search || activeDrillState.eventCategory || activeDrillState.eventUlp);
            var matchStr = hasSub
                ? ('Cocok: ' + filtMatch.toLocaleString('id-ID') + ' / ' + totalMatch.toLocaleString('id-ID'))
                : ('Cocok: ' + totalMatch.toLocaleString('id-ID'));
            var loadStr = 'Dimuat: ' + loadedCnt.toLocaleString('id-ID') + ' baris (#' + loadedFirst.toLocaleString('id-ID') + '–#' + loadedLast.toLocaleString('id-ID') + ')';
            var viewStr = 'Tampil di area gulir: #' + firstInView.toLocaleString('id-ID') + '–#' + lastInView.toLocaleString('id-ID');
            footerRange.textContent = matchStr + ' • ' + loadStr + ' • ' + viewStr;
        }
    }

    function updateEventsSectionUi(state, skipTbodyRebuild) {
        var secEl        = document.getElementById('ggDrillEventsSection');
        if (secEl) secEl.setAttribute('data-busy', state.busy ? '1' : '0');
        var tbody        = document.getElementById('ggDrillEventsTbody');
        var emptyBox     = document.getElementById('ggDrillEventsEmpty');
        var tableWrap    = document.getElementById('ggDrillEventsTableWrap');
        var visSummary   = document.getElementById('ggDrillVisibilitySummary');
        var statMatched  = document.getElementById('ggDrillStatMatched');
        var statLoaded   = document.getElementById('ggDrillStatLoaded');
        var countPill    = document.getElementById('ggDrillCountPill');
        var btnLoadMore  = document.getElementById('btnGgDrillLoadMore');
        var btnLoadAll   = document.getElementById('btnGgDrillLoadAll');
        var btnFirst     = document.getElementById('btnGgDrillPageFirst');
        var btnPrev      = document.getElementById('btnGgDrillPagePrev');
        var btnNext      = document.getElementById('btnGgDrillPageNext');
        var btnLast      = document.getElementById('btnGgDrillPageLast');
        var pageInd      = document.getElementById('ggDrillPageIndicator');

        var totalMatch   = Number(state.totalMatching || 0);
        var filtMatch    = Number(typeof state.filteredMatching === 'number' ? state.filteredMatching : totalMatch);
        var rows         = Array.isArray(state.visibleRows) ? state.visibleRows : [];
        var visibleCnt   = rows.length;
        var startRow     = visibleCnt > 0 ? Number(rows[0].row_num || (state.pageStartOffset + 1)) : 0;
        var endRow       = visibleCnt > 0 ? Number(rows[visibleCnt - 1].row_num || (state.pageStartOffset + visibleCnt)) : 0;
        var remaining    = Math.max(0, filtMatch - endRow);
        var hasMoreAfter = endRow < filtMatch;
        var isFullList   = (startRow === 1 && endRow === filtMatch && filtMatch > 0);
        var hasSubFilter = Boolean(state.search || state.eventCategory || state.eventUlp);

        if (countPill) {
            countPill.textContent = hasSubFilter
                ? (filtMatch.toLocaleString('id-ID') + ' / ' + totalMatch.toLocaleString('id-ID') + ' cocok')
                : (totalMatch.toLocaleString('id-ID') + ' kejadian');
        }
        if (statMatched) {
            statMatched.textContent = hasSubFilter
                ? ('Cocok Filter: ' + filtMatch.toLocaleString('id-ID') + ' dari ' + totalMatch.toLocaleString('id-ID') + ' kejadian')
                : ('Cocok Filter: ' + totalMatch.toLocaleString('id-ID') + ' kejadian');
        }
        if (statLoaded) {
            statLoaded.textContent = visibleCnt > 0
                ? ('Telah Dimuat: ' + visibleCnt.toLocaleString('id-ID') + ' baris (#' + startRow.toLocaleString('id-ID') + '–#' + endRow.toLocaleString('id-ID') + ')' + (remaining > 0 ? (' • belum dimuat: ' + remaining.toLocaleString('id-ID')) : ' • semua dimuat'))
                : 'Telah Dimuat: 0 baris';
        }

        if (visSummary) {
            if (filtMatch === 0) {
                visSummary.innerHTML = '<span class="badge bg-secondary-subtle text-secondary border">0 kejadian cocok</span>';
            } else if (isFullList) {
                visSummary.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle">' +
                    '<i class="fas fa-check-circle me-1" aria-hidden="true"></i>Menampilkan seluruh ' + visibleCnt.toLocaleString('id-ID') + ' dari ' + filtMatch.toLocaleString('id-ID') + ' kejadian cocok' +
                '</span>';
            } else {
                visSummary.innerHTML = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">' +
                    'Menampilkan baris ' + startRow.toLocaleString('id-ID') + '–' + endRow.toLocaleString('id-ID') + ' dari ' + filtMatch.toLocaleString('id-ID') + ' kejadian cocok' +
                    (remaining > 0 ? (' • sisa ' + remaining.toLocaleString('id-ID') + ' belum dimuat') : '') +
                '</span>';
            }
        }

        if (tbody && !skipTbodyRebuild) {
            tbody.innerHTML = rows.map(function(ev, i) {
                return renderEventRowHtml(ev, state.pageStartOffset + i + 1);
            }).join('');
        }
        if (emptyBox) emptyBox.classList.toggle('d-none', visibleCnt > 0);
        if (tableWrap) tableWrap.classList.toggle('d-none', visibleCnt === 0);

        var pageSize   = Math.max(1, Number(state.limit || 100));
        var totalPages = Math.max(1, Math.ceil(filtMatch / pageSize));
        var curPage    = Math.min(totalPages, Math.floor(state.pageStartOffset / pageSize) + 1);

        if (pageInd) {
            pageInd.textContent = 'Hal ' + curPage.toLocaleString('id-ID') + ' / ' + totalPages.toLocaleString('id-ID');
        }
        if (btnFirst) btnFirst.disabled = (state.pageStartOffset <= 0 || state.busy);
        if (btnPrev)  btnPrev.disabled  = (state.pageStartOffset <= 0 || state.busy);
        if (btnNext)  btnNext.disabled  = (!hasMoreAfter || state.busy);
        if (btnLast)  btnLast.disabled  = (!hasMoreAfter || state.busy);

        if (btnLoadMore) {
            btnLoadMore.classList.toggle('d-none', !hasMoreAfter);
            btnLoadMore.disabled = Boolean(state.busy);
            var nextBatch = Math.min(pageSize, remaining);
            btnLoadMore.innerHTML = '<i class="fas fa-plus-circle me-1" aria-hidden="true"></i>Muat Lagi (+' + nextBatch.toLocaleString('id-ID') + ' baris • sisa ' + remaining.toLocaleString('id-ID') + ')';
        }
        if (btnLoadAll) {
            btnLoadAll.classList.toggle('d-none', !hasMoreAfter || remaining <= pageSize);
            btnLoadAll.disabled = Boolean(state.busy);
            btnLoadAll.innerHTML = '<i class="fas fa-layer-group me-1" aria-hidden="true"></i>Tampilkan Semua (' + filtMatch.toLocaleString('id-ID') + ')';
        }

        updateViewportVisibleRange();
    }

    function showInlinePageError(msg, retryFn) {
        var errBox = document.getElementById('ggDrillInlineError');
        if (!errBox) return;
        errBox.classList.remove('d-none');
        errBox.innerHTML = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">' +
            '<span><i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i><strong>Gagal memuat baris tambahan:</strong> ' + esc(msg || 'kesalahan jaringan') + '. Daftar saat ini belum lengkap.</span>' +
            '<button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2.5 py-0.5" id="btnGgDrillRetryInline">Coba Lagi</button>' +
        '</div>';
        var btn = document.getElementById('btnGgDrillRetryInline');
        if (btn && typeof retryFn === 'function') {
            btn.onclick = function() {
                errBox.classList.add('d-none');
                retryFn();
            };
        }
    }

    function clearInlinePageError() {
        var errBox = document.getElementById('ggDrillInlineError');
        if (errBox) {
            errBox.classList.add('d-none');
            errBox.innerHTML = '';
        }
    }

    function fetchDrillSlice(state, mode, targetOffset, customLimit) {
        if (!state || state.busy) return;
        state.busy = true;
        clearInlinePageError();
        updateEventsSectionUi(state, true);

        var prevCount = state.visibleRows.length;
        var reqOffset = typeof targetOffset === 'number' ? Math.max(0, targetOffset) : state.offset;
        var reqLimit  = typeof customLimit === 'number' ? customLimit : state.limit;
        var url       = buildQueryUrl(state, reqOffset, reqLimit);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(res) {
                if (!res || res.status !== 'success' || !res.data) {
                    throw new Error((res && res.message) ? res.message : 'Respons detail tidak valid');
                }
                state.busy = false;
                var d = res.data;
                state.totalMatching    = Number(d.total_matching || 0);
                state.filteredMatching = Number(typeof d.filtered_matching === 'number' ? d.filtered_matching : state.totalMatching);
                var incoming = Array.isArray(d.events) ? d.events : [];

                if (mode === 'append') {
                    state.visibleRows = state.visibleRows.concat(incoming);
                    state.offset      = reqOffset + incoming.length;
                } else {
                    state.pageStartOffset = reqOffset;
                    state.visibleRows     = incoming;
                    state.offset          = reqOffset + incoming.length;
                }
                updateEventsSectionUi(state, false);

                var wrap = document.getElementById('ggDrillEventsTableWrap');
                if (wrap) {
                    if (mode === 'append' && incoming.length > 0) {
                        var firstNewNum = Number(incoming[0].row_num || (prevCount + 1));
                        var newTr = wrap.querySelector('tr[data-row-num="' + firstNewNum + '"]');
                        if (newTr) {
                            wrap.scrollTop = Math.max(0, newTr.offsetTop - 36);
                        }
                    } else if (mode === 'page') {
                        wrap.scrollTop = 0;
                    }
                    updateViewportVisibleRange();
                }
            })
            .catch(function(err) {
                state.busy = false;
                updateEventsSectionUi(state, true);
                showInlinePageError(err && err.message ? err.message : 'kesalahan jaringan', function() {
                    fetchDrillSlice(state, mode, targetOffset, customLimit);
                });
            });
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

        var initialLimit = Number((opts.params && opts.params.limit) ? opts.params.limit : 100);
        activeDrillState = {
            baseEndpoint: opts.endpoint || '<?= site_url('gangguan/drilldown') ?>',
            baseParams: Object.assign({}, opts.params || {}),
            pageStartOffset: 0,
            offset: 0,
            limit: initialLimit,
            search: '',
            eventCategory: '',
            eventUlp: '',
            totalMatching: 0,
            filteredMatching: 0,
            visibleRows: [],
            busy: true
        };

        var url = buildQueryUrl(activeDrillState, 0, activeDrillState.limit);
        fetch(url, { headers: { 'Accept': 'application/json' } })
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
                html += '<div class="row g-2 mb-2.5">' +
                    '<div class="col-6 col-md-3"><div class="p-2.5 rounded-3 border bg-light h-100">' +
                        '<div class="text-muted" style="font-size: 11px;">Kejadian Cocok</div>' +
                        '<div class="fs-5 fw-bold text-primary font-monospace" id="ggDrillTotalMatchingVal">' + Number(d.total_matching || 0).toLocaleString('id-ID') + '</div>' +
                        '<div class="text-muted" style="font-size: 10.5px;">dari ' + Number(d.total_scope || 0).toLocaleString('id-ID') + ' (' + Number(d.share_pct || 0).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%)</div>' +
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

                // Optional Pareto cumulative ordered sequence
                if (Array.isArray(d.pareto_sequence) && d.pareto_sequence.length) {
                    html += '<div class="mb-2.5 border rounded-3 overflow-hidden">' +
                        '<div class="px-3 py-2 bg-warning-subtle border-bottom fw-bold text-dark d-flex justify-content-between align-items-center" style="font-size: 12px;">' +
                            '<span><i class="fas fa-chart-line text-danger me-1" aria-hidden="true"></i>Urutan Kategori s.d. Titik Kumulatif #' + d.pareto_sequence.length + '</span>' +
                            '<span class="font-monospace">Akumulasi: ' + Number(d.pareto_point ? d.pareto_point.cumulative_pct : 0).toFixed(1) + '%</span>' +
                        '</div>' +
                        '<div class="table-responsive gg-drill-pareto-scroll"><table class="table table-sm table-hover mb-0" style="font-size: 11.5px;">' +
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
                html += '<div class="row g-2 mb-1">' +
                    '<div class="col-md-6">' + renderChips('Sebaran ULP', bd.by_ulp) + renderChips('Famili Peralatan', bd.by_equipment_family) + '</div>' +
                    '<div class="col-md-6">' + renderChips('Kategori & Relai', [].concat(bd.by_category || [], bd.by_relay || [])) + '</div>' +
                '</div>';
                html += renderChips('Top Penyulang Terdampak', bd.top_feeders);
                html += renderChips('Kode Penyebab Sumber', bd.top_causes);

                var ulpOptions = '<option value="">Semua ULP</option>';
                if (Array.isArray(bd.by_ulp)) {
                    bd.by_ulp.forEach(function(u) {
                        if (u && u.label) {
                            ulpOptions += '<option value="' + esc(u.label) + '">' + esc(u.label) + ' (' + Number(u.count || 0).toLocaleString('id-ID') + ')</option>';
                        }
                    });
                }

                // Collapsible Accordion / Dropdown for Full Event List + Search + Pagination + Load More
                html += '<div class="border rounded-3 overflow-hidden" id="ggDrillEventsSection">' +
                    '<button type="button" class="btn btn-light w-100 px-3 py-2 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 text-start rounded-0" ' +
                        'id="ggDrillEventsToggleBtn" data-bs-toggle="collapse" data-bs-target="#ggDrillEventsCollapse" aria-expanded="true" aria-controls="ggDrillEventsCollapse" style="font-size: 12.5px;">' +
                        '<span class="fw-bold text-dark d-flex align-items-center gap-1.5">' +
                            '<i class="fas fa-list-check text-primary" aria-hidden="true"></i>' +
                            '<span>Daftar Kejadian Gangguan</span>' +
                            '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace" id="ggDrillCountPill">0</span>' +
                        '</span>' +
                        '<span class="d-flex flex-wrap align-items-center gap-2">' +
                            '<span id="ggDrillVisibilitySummary" class="small"></span>' +
                            '<span class="badge bg-white text-dark border rounded-pill px-2 py-1" style="font-size: 10.5px;"><i class="fas fa-chevron-down me-1" aria-hidden="true"></i>Buka / Tutup Rincian</span>' +
                        '</span>' +
                    '</button>' +
                    '<div class="collapse show" id="ggDrillEventsCollapse">' +
                        '<div class="px-3 py-1.5 bg-white border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size: 11px;">' +
                            '<div class="d-flex flex-wrap align-items-center gap-2">' +
                                '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-monospace px-2.5 py-1" id="ggDrillStatMatched">Cocok Filter: 0</span>' +
                                '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill font-monospace px-2.5 py-1" id="ggDrillStatLoaded">Telah Dimuat: 0 baris</span>' +
                                '<span class="badge bg-light text-dark border rounded-pill font-monospace px-2.5 py-1" id="ggDrillStatViewport">Sedang tampil: -</span>' +
                            '</div>' +
                            '<span class="text-muted" style="font-size: 10.5px;"><i class="fas fa-arrows-up-down me-1" aria-hidden="true"></i>Gulir tabel ke bawah atau klik Muat Lagi untuk seluruh baris</span>' +
                        '</div>' +
                        '<div class="p-2.5 bg-light border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">' +
                            '<div class="d-flex flex-wrap align-items-center gap-1.5 flex-grow-1">' +
                                '<div class="input-group input-group-sm" style="max-width: 260px;">' +
                                    '<span class="input-group-text bg-white"><i class="fas fa-search text-muted" aria-hidden="true"></i></span>' +
                                    '<input type="search" class="form-control" id="ggDrillSearchInput" placeholder="Cari penyulang, alat, sebab, ket..." aria-label="Cari di daftar kejadian">' +
                                '</div>' +
                                '<select class="form-select form-select-sm" id="ggDrillCatSelect" aria-label="Filter kategori kejadian" style="width: auto;">' +
                                    '<option value="">Semua Kategori</option>' +
                                    '<option value="TEMPORARY">Temporer</option>' +
                                    '<option value="PERMANENT">Permanen</option>' +
                                '</select>' +
                                '<select class="form-select form-select-sm" id="ggDrillUlpSelect" aria-label="Filter ULP kejadian" style="width: auto;">' +
                                    ulpOptions +
                                '</select>' +
                                '<button type="button" class="btn btn-sm btn-primary rounded-pill px-2.5" id="btnGgDrillApplyFilter">Filter</button>' +
                                '<button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5" id="btnGgDrillResetFilter">Reset</button>' +
                            '</div>' +
                            '<div class="d-flex align-items-center gap-1.5">' +
                                '<label for="ggDrillLimitSelect" class="text-muted small mb-0" style="font-size: 11px;">Baris/hal:</label>' +
                                '<select class="form-select form-select-sm font-monospace" id="ggDrillLimitSelect" style="width: auto;">' +
                                    '<option value="50">50</option>' +
                                    '<option value="100" selected>100</option>' +
                                    '<option value="200">200</option>' +
                                '</select>' +
                            '</div>' +
                        '</div>' +
                        '<div id="ggDrillInlineError" class="alert alert-warning rounded-0 mb-0 py-2 px-3 small d-none" role="alert"></div>' +
                        '<div id="ggDrillEventsEmpty" class="p-4 text-center text-muted small d-none">Tidak ada catatan kejadian yang cocok dengan pilihan dan filter aktif.</div>' +
                        '<div class="table-responsive gg-drill-table-scroll" id="ggDrillEventsTableWrap">' +
                            '<table class="table table-sm table-hover align-middle mb-0" id="ggDrillEventsTable" style="font-size: 11.5px;">' +
                                '<thead class="table-light">' +
                                    '<tr>' +
                                        '<th class="text-center">#</th>' +
                                        '<th>Tanggal &amp; Durasi</th>' +
                                        '<th>ULP</th>' +
                                        '<th>Penyulang</th>' +
                                        '<th>Peralatan</th>' +
                                        '<th>Relai</th>' +
                                        '<th>Kategori</th>' +
                                        '<th class="text-end">Arus / ENS</th>' +
                                        '<th>Penyebab</th>' +
                                        '<th>Keterangan &amp; Tindak Lanjut</th>' +
                                    '</tr>' +
                                '</thead>' +
                                '<tbody id="ggDrillEventsTbody"></tbody>' +
                            '</table>' +
                        '</div>' +
                        '<div class="px-3 py-2 bg-light border-top d-flex flex-wrap justify-content-between align-items-center gap-2" id="ggDrillPaginationBar">' +
                            '<div class="small text-muted font-monospace" id="ggDrillFooterRangeText" style="font-size: 11px;"></div>' +
                            '<div class="d-flex flex-wrap align-items-center gap-1.5">' +
                                '<button type="button" class="btn btn-xs btn-primary rounded-pill px-3 py-1 fw-semibold" id="btnGgDrillLoadMore" style="font-size: 11px;"></button>' +
                                '<button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2.5 py-1 fw-semibold" id="btnGgDrillLoadAll" style="font-size: 11px;"></button>' +
                            '</div>' +
                            '<div class="btn-group btn-group-sm" role="group" aria-label="Navigasi halaman kejadian">' +
                                '<button type="button" class="btn btn-outline-secondary" id="btnGgDrillPageFirst" title="Halaman pertama">&laquo; Awal</button>' +
                                '<button type="button" class="btn btn-outline-secondary" id="btnGgDrillPagePrev" title="Halaman sebelumnya">&lsaquo; Seb</button>' +
                                '<span class="btn btn-light border disabled font-monospace text-dark fw-semibold" id="ggDrillPageIndicator" style="font-size: 11px;">Hal 1 / 1</span>' +
                                '<button type="button" class="btn btn-outline-secondary" id="btnGgDrillPageNext" title="Halaman berikutnya">Ber &rsaquo;</button>' +
                                '<button type="button" class="btn btn-outline-secondary" id="btnGgDrillPageLast" title="Halaman terakhir">Akhir &raquo;</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';

                if (bodyEl) bodyEl.innerHTML = html;

                activeDrillState.busy             = false;
                activeDrillState.totalMatching    = Number(d.total_matching || 0);
                activeDrillState.filteredMatching = Number(typeof d.filtered_matching === 'number' ? d.filtered_matching : activeDrillState.totalMatching);
                activeDrillState.pageStartOffset  = Number(d.offset || 0);
                activeDrillState.visibleRows      = Array.isArray(d.events) ? d.events : [];
                activeDrillState.offset           = activeDrillState.pageStartOffset + activeDrillState.visibleRows.length;

                updateEventsSectionUi(activeDrillState, false);

                var wrapEl    = document.getElementById('ggDrillEventsTableWrap');
                var searchIn  = document.getElementById('ggDrillSearchInput');
                var catSel    = document.getElementById('ggDrillCatSelect');
                var ulpSel    = document.getElementById('ggDrillUlpSelect');
                var limSel    = document.getElementById('ggDrillLimitSelect');
                var btnApply  = document.getElementById('btnGgDrillApplyFilter');
                var btnReset  = document.getElementById('btnGgDrillResetFilter');
                var btnMore   = document.getElementById('btnGgDrillLoadMore');
                var btnAll    = document.getElementById('btnGgDrillLoadAll');
                var btnFirst  = document.getElementById('btnGgDrillPageFirst');
                var btnPrev   = document.getElementById('btnGgDrillPagePrev');
                var btnNext   = document.getElementById('btnGgDrillPageNext');
                var btnLast   = document.getElementById('btnGgDrillPageLast');

                if (wrapEl) {
                    wrapEl.addEventListener('scroll', function() {
                        updateViewportVisibleRange();
                    }, { passive: true });
                }

                var triggerSubFilter = function() {
                    if (!activeDrillState) return;
                    activeDrillState.search        = searchIn ? searchIn.value.trim() : '';
                    activeDrillState.eventCategory = catSel ? catSel.value : '';
                    activeDrillState.eventUlp      = ulpSel ? ulpSel.value : '';
                    activeDrillState.limit         = limSel ? (parseInt(limSel.value, 10) || 100) : 100;
                    fetchDrillSlice(activeDrillState, 'page', 0, activeDrillState.limit);
                };

                if (btnApply) btnApply.onclick = triggerSubFilter;
                if (searchIn) {
                    searchIn.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            triggerSubFilter();
                        }
                    });
                }
                if (catSel) catSel.onchange = triggerSubFilter;
                if (ulpSel) ulpSel.onchange = triggerSubFilter;
                if (limSel) limSel.onchange = triggerSubFilter;

                if (btnReset) {
                    btnReset.onclick = function() {
                        if (searchIn) searchIn.value = '';
                        if (catSel) catSel.value = '';
                        if (ulpSel) ulpSel.value = '';
                        if (limSel) limSel.value = '100';
                        triggerSubFilter();
                    };
                }

                if (btnMore) {
                    btnMore.onclick = function() {
                        if (!activeDrillState) return;
                        fetchDrillSlice(activeDrillState, 'append', activeDrillState.offset, activeDrillState.limit);
                    };
                }
                if (btnAll) {
                    btnAll.onclick = function() {
                        if (!activeDrillState) return;
                        var rem = Math.max(10, activeDrillState.filteredMatching - activeDrillState.offset);
                        fetchDrillSlice(activeDrillState, 'append', activeDrillState.offset, Math.min(1000, rem));
                    };
                }
                if (btnFirst) {
                    btnFirst.onclick = function() {
                        if (!activeDrillState) return;
                        fetchDrillSlice(activeDrillState, 'page', 0, activeDrillState.limit);
                    };
                }
                if (btnPrev) {
                    btnPrev.onclick = function() {
                        if (!activeDrillState) return;
                        var prevOff = Math.max(0, activeDrillState.pageStartOffset - activeDrillState.limit);
                        fetchDrillSlice(activeDrillState, 'page', prevOff, activeDrillState.limit);
                    };
                }
                if (btnNext) {
                    btnNext.onclick = function() {
                        if (!activeDrillState) return;
                        var lastRow = activeDrillState.visibleRows.length
                            ? Number(activeDrillState.visibleRows[activeDrillState.visibleRows.length - 1].row_num || (activeDrillState.pageStartOffset + activeDrillState.visibleRows.length))
                            : (activeDrillState.pageStartOffset + activeDrillState.limit);
                        if (lastRow < activeDrillState.filteredMatching) {
                            fetchDrillSlice(activeDrillState, 'page', lastRow, activeDrillState.limit);
                        }
                    };
                }
                if (btnLast) {
                    btnLast.onclick = function() {
                        if (!activeDrillState) return;
                        var ps = Math.max(1, activeDrillState.limit);
                        var lastPageIdx = Math.max(0, Math.ceil(activeDrillState.filteredMatching / ps) - 1);
                        fetchDrillSlice(activeDrillState, 'page', lastPageIdx * ps, ps);
                    };
                }

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
