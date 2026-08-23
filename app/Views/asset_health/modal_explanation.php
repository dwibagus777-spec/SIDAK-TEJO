<!--
  Modal Partial: 3-Layer Health Index Explanation Modal
  Phase 1D & Phase 2 Read-Only UI Explanation Component
-->
<div class="modal fade" id="modalHealthIndexExplanation" tabindex="-1" aria-labelledby="modalHIExplanationLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow-lg border-0">
      
      <!-- Modal Header -->
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title fw-bold" id="modalHIExplanationLabel">
          <i class="bi bi-shield-check me-2"></i>Penjelasan Health Index & Audit History
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4 bg-light">
        
        <!-- Live Preview Fallback Warning Alert -->
        <div id="hiLivePreviewAlert" class="alert alert-warning border-warning d-none mb-3 shadow-sm" role="alert">
          <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
            <div>
              <strong class="d-block">Mode Simulasi Live Preview (Belum Ada Perhitungan Resmi)</strong>
              <span class="small">Aset ini belum memiliki rekaman kalkulasi resmi di database. Nilai di bawah merupakan simulasi langsung dari temuan aktif saat ini.</span>
            </div>
          </div>
        </div>

        <!-- Loading Indicator -->
        <div id="hiModalLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Memuat data...</span>
          </div>
          <p class="mt-3 text-muted fw-semibold">Memuat rincian kalkulasi resmi...</p>
        </div>

        <!-- Content Body (Hidden while loading) -->
        <div id="hiModalContent" class="d-none">

          <!-- Layer 1: Main Health Index Score & Category Pill -->
          <div class="card border-0 shadow-sm mb-4 bg-white">
            <div class="card-body p-4 text-center">
              <span class="text-uppercase tracking-wider text-muted fw-semibold d-block mb-1">Skor Kondisi Aset Saat Ini</span>
              <div class="display-3 fw-bold text-dark mb-2" id="hiScoreValue">100.00</div>
              <div>
                <span class="badge px-3 py-2 fs-6 rounded-pill" id="hiCategoryPill">VERY GOOD</span>
              </div>
            </div>
          </div>

          <!-- Layer 2: 7-Component Breakdown Accordion -->
          <h6 class="fw-bold text-secondary mb-3">
            <i class="bi bi-diagram-3 me-2"></i>Rincian Pengurangan Poin (7 Komponen Katalog)
          </h6>
          
          <div class="accordion mb-4" id="hiComponentsAccordion">
            
            <!-- ACTIVE_FINDINGS Card -->
            <div class="accordion-item border shadow-sm mb-2 rounded">
              <h2 class="accordion-header" id="headingFindings">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFindings" aria-expanded="true">
                  <div class="d-flex justify-content-between align-items-center w-100 me-3">
                    <span class="fw-bold text-dark">
                      <i class="bi bi-exclamation-octagon-fill text-warning me-2"></i>
                      Temuan Aktif Belum Selesai (ACTIVE_FINDINGS)
                    </span>
                    <span class="badge bg-danger" id="hiDeductionFindings">-0.00 Poin</span>
                  </div>
                </button>
              </h2>
              <div id="collapseFindings" class="accordion-collapse collapse show" data-bs-parent="#hiComponentsAccordion">
                <div class="accordion-body p-3 bg-white">
                  <div id="hiFindingsContainer">
                    <!-- Populated dynamically via JS -->
                  </div>
                </div>
              </div>
            </div>

            <!-- AGE Card -->
            <div class="accordion-item border shadow-sm mb-2 rounded">
              <h2 class="accordion-header" id="headingAge">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAge">
                  <div class="d-flex justify-content-between align-items-center w-100 me-3">
                    <span class="fw-bold text-dark">
                      <i class="bi bi-clock-history text-info me-2"></i>
                      Umur / Masa Pakai Aset (AGE)
                    </span>
                    <span class="badge bg-secondary" id="hiDeductionAge">-0.00 Poin</span>
                  </div>
                </button>
              </h2>
              <div id="collapseAge" class="accordion-collapse collapse" data-bs-parent="#hiComponentsAccordion">
                <div class="accordion-body p-3 bg-white small text-muted" id="hiAgeDetails">
                  Pengurangan umur: -1.0 poin per 5 tahun masa pakai aset (Maksimal -10.00).
                </div>
              </div>
            </div>

            <!-- INSPECTION Card -->
            <div class="accordion-item border shadow-sm mb-2 rounded">
              <h2 class="accordion-header" id="headingInspection">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInspection">
                  <div class="d-flex justify-content-between align-items-center w-100 me-3">
                    <span class="fw-bold text-dark">
                      <i class="bi bi-calendar-x text-danger me-2"></i>
                      Jadwal Inspeksi Terlewat (INSPECTION)
                    </span>
                    <span class="badge bg-secondary" id="hiDeductionInspection">-0.00 Poin</span>
                  </div>
                </button>
              </h2>
              <div id="collapseInspection" class="accordion-collapse collapse" data-bs-parent="#hiComponentsAccordion">
                <div class="accordion-body p-3 bg-white small text-muted" id="hiInspectionDetails">
                  Pengurangan inspeksi: -3.00 poin jika inspeksi terakhir terlewat > 180 hari.
                </div>
              </div>
            </div>

            <!-- VEGETATION Card -->
            <div class="accordion-item border shadow-sm mb-2 rounded">
              <h2 class="accordion-header" id="headingVegetation">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVegetation">
                  <div class="d-flex justify-content-between align-items-center w-100 me-3">
                    <span class="fw-bold text-dark">
                      <i class="bi bi-tree text-success me-2"></i>
                      Risiko Vegetasi Jaringan (VEGETATION)
                    </span>
                    <span class="badge bg-secondary" id="hiDeductionVegetation">-0.00 Poin</span>
                  </div>
                </button>
              </h2>
              <div id="collapseVegetation" class="accordion-collapse collapse" data-bs-parent="#hiComponentsAccordion">
                <div class="accordion-body p-3 bg-white small text-muted" id="hiVegetationDetails">
                  <!-- Populated dynamically via JS -->
                </div>
              </div>
            </div>

            <!-- THERMOVISION Card -->
            <div class="accordion-item border shadow-sm mb-2 rounded">
              <h2 class="accordion-header" id="headingThermovision">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThermovision">
                  <div class="d-flex justify-content-between align-items-center w-100 me-3">
                    <span class="fw-bold text-dark">
                      <i class="bi bi-thermometer-high text-danger me-2"></i>
                      Pengukuran Thermovision Hotspot (THERMOVISION)
                    </span>
                    <span class="badge bg-secondary" id="hiDeductionThermovision">-0.00 Poin</span>
                  </div>
                </button>
              </h2>
              <div id="collapseThermovision" class="accordion-collapse collapse" data-bs-parent="#hiComponentsAccordion">
                <div class="accordion-body p-3 bg-white small text-muted" id="hiThermovisionDetails">
                  <!-- Populated dynamically via JS -->
                </div>
              </div>
            </div>

            <!-- PLACEHOLDER COMPONENT CARDS (MATERIAL_ANOMALY & CONSTRUCTION) -->
            <div class="accordion-item border shadow-sm mb-2 rounded bg-light opacity-75">
              <div class="p-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-muted">
                  <i class="bi bi-box-seam me-2"></i>Anomali Material & Sparepart (MATERIAL_ANOMALY)
                </span>
                <span class="badge bg-secondary text-white">Belum aktif dalam Engine Version 1.0</span>
              </div>
            </div>

            <div class="accordion-item border shadow-sm mb-2 rounded bg-light opacity-75">
              <div class="p-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-muted">
                  <i class="bi bi-building me-2"></i>Faktor Jenis Konstruksi (CONSTRUCTION)
                </span>
                <span class="badge bg-secondary text-white">Belum aktif dalam Engine Version 1.0</span>
              </div>
            </div>

          </div>

          <!-- Layer 3: Audit Fingerprint & Metadata Footer -->
          <div class="card border bg-light shadow-sm">
            <div class="card-body p-3 small">
              <h6 class="fw-bold text-secondary mb-2">
                <i class="bi bi-file-earmark-code me-2"></i>Audit Fingerprint & Metadata Engine
              </h6>
              <div class="row text-muted">
                <div class="col-md-6 mb-1">Engine Version: <strong class="text-dark" id="hiEngineVer">1.0</strong></div>
                <div class="col-md-6 mb-1 text-md-end">Trigger Event: <strong class="text-dark" id="hiTrigger">VIEW</strong></div>
                <div class="col-12 mt-2">
                  <span class="d-block fw-semibold mb-1">SHA-256 Calculation Hash:</span>
                  <code class="text-break bg-white p-2 rounded border d-block text-primary" id="hiCalcHash">-</code>
                </div>
              </div>
            </div>
          </div>

        </div> <!-- End hiModalContent -->

      </div> <!-- End modal-body -->

      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Tutup</button>
      </div>

    </div>
  </div>
</div>

<script>
function showHIExplanationModal(assetId) {
    const modalEl = document.getElementById('modalHealthIndexExplanation');
    const bsModal = new bootstrap.Modal(modalEl);
    
    document.getElementById('hiModalLoading').classList.remove('d-none');
    document.getElementById('hiModalContent').classList.add('d-none');
    bsModal.show();

    fetch('<?= base_url('asset-health/explanation') ?>/' + assetId)
        .then(response => response.json())
        .then(res => {
            if (res.status !== 'success') {
                alert('Gagal memuat rincian HI: ' + (res.message || 'Unknown error'));
                bsModal.hide();
                return;
            }
            renderHIModalData(res.data, res.is_live);
        })
        .catch(err => {
            alert('Error koneksi: ' + err.message);
            bsModal.hide();
        });
}

function renderHIModalData(data, isLive) {
    const liveAlert = document.getElementById('hiLivePreviewAlert');
    if (isLive) {
        liveAlert.classList.remove('d-none');
    } else {
        liveAlert.classList.add('d-none');
    }

    document.getElementById('hiScoreValue').innerText = Number(data.final_score).toFixed(2);
    
    const catPill = document.getElementById('hiCategoryPill');
    catPill.innerText = data.category.replace('_', ' ');
    catPill.className = 'badge px-3 py-2 fs-6 rounded-pill ' + getHICategoryBadgeClass(data.category);

    const explanation = data.explanation_json;

    // ACTIVE_FINDINGS
    const findingsComp = explanation.ACTIVE_FINDINGS || {};
    document.getElementById('hiDeductionFindings').innerText = (findingsComp.deduction || 0).toFixed(2) + ' Poin';
    
    const findingsContainer = document.getElementById('hiFindingsContainer');
    if (findingsComp.active_cases_count === 0) {
        findingsContainer.innerHTML = '<div class="alert alert-success py-2 mb-0 small"><i class="bi bi-check-circle me-2"></i>Tidak ada temuan aktif pada aset ini. (0 Deduction)</div>';
    } else {
        let html = `<div class="mb-2 small text-muted">Total kasus aktif: <strong>${findingsComp.active_cases_count} kasus</strong> (Uncapped: ${findingsComp.uncapped_deduction.toFixed(2)} poin)</div>`;
        html += '<table class="table table-sm table-bordered small align-middle"><thead class="table-light"><tr><th>No. Temuan</th><th>Severity</th><th>Aging (>30 hr)</th><th>Recurrence</th><th>Pengurangan</th></tr></thead><tbody>';
        
        (findingsComp.breakdown || []).forEach(item => {
            html += `<tr>
                <td><strong>${item.nomor_temuan}</strong><br><span class="text-muted small">${item.jenis_temuan}</span></td>
                <td><span class="badge ${getSeverityBadgeClass(item.severity)}">${item.severity}</span></td>
                <td>${item.days_open} hari (-${item.aging_ded.toFixed(2)})</td>
                <td>${item.observation_count} obs (rec: ${item.recurrence_count}x, -${item.recurrence_ded.toFixed(2)})</td>
                <td class="fw-bold text-danger">-${item.total_case_ded.toFixed(2)} Poin</td>
            </tr>`;
        });

        html += '</tbody></table>';
        findingsContainer.innerHTML = html;
    }

    // AGE
    const ageComp = explanation.AGE || {};
    document.getElementById('hiDeductionAge').innerText = (ageComp.deduction || 0).toFixed(2) + ' Poin';
    document.getElementById('hiAgeDetails').innerText = `Masa pakai aset: ${ageComp.asset_age_yrs || 0} tahun. (Pengurangan: ${(ageComp.deduction || 0).toFixed(2)} poin)`;

    // INSPECTION
    const inspComp = explanation.INSPECTION || {};
    document.getElementById('hiDeductionInspection').innerText = (inspComp.deduction || 0).toFixed(2) + ' Poin';

    // VEGETATION (Phase 2A Extension)
    const vegComp = explanation.VEGETATION || {};
    document.getElementById('hiDeductionVegetation').innerText = (vegComp.deduction || 0).toFixed(2) + ' Poin';
    const vegDetails = document.getElementById('hiVegetationDetails');
    if (vegComp.status === 'ACTIVE') {
        vegDetails.innerHTML = `Rule Version: <strong>${vegComp.rule_version}</strong><br>Reason: <code>${vegComp.reason_code}</code><br>Pengurangan: <strong>-${(vegComp.deduction || 0).toFixed(2)} poin</strong>`;
    } else {
        vegDetails.innerText = 'Risiko Vegetasi Jaringan (Evaluasi RoW JTM deterministik & Wind-Contact Emergency Override).';
    }

    // THERMOVISION (Phase 2B Extension)
    const thermoComp = explanation.THERMOVISION || {};
    document.getElementById('hiDeductionThermovision').innerText = (thermoComp.deduction || 0).toFixed(2) + ' Poin';
    const thermoDetails = document.getElementById('hiThermovisionDetails');
    if (thermoComp.status === 'ACTIVE') {
        thermoDetails.innerHTML = `Domain: <strong>${thermoComp.inspection_domain}</strong> | Rule Version: <strong>${thermoComp.rule_version}</strong><br>Temperatur: <strong>${thermoComp.temperature_c}°C</strong> | Operational Status: <span class="badge ${getSeverityBadgeClass(thermoComp.operational_status)}">${thermoComp.operational_status}</span><br>Pengurangan: <strong>-${(thermoComp.deduction || 0).toFixed(2)} poin</strong>`;
    } else {
        thermoDetails.innerText = 'Pengukuran Thermovision Hotspot (Rule Ladder Dual-Domain: JTM/PDKB & HAR GTT).';
    }

    // AUDIT METADATA
    document.getElementById('hiEngineVer').innerText = data.engine_version || '1.0';
    document.getElementById('hiTrigger').innerText = data.trigger_event || 'VIEW';
    document.getElementById('hiCalcHash').innerText = data.calculation_hash || '-';

    document.getElementById('hiModalLoading').classList.add('d-none');
    document.getElementById('hiModalContent').classList.remove('d-none');
}

function getHICategoryBadgeClass(category) {
    switch (category) {
        case 'VERY_GOOD': return 'bg-success text-white';
        case 'GOOD':      return 'bg-info text-white';
        case 'FAIR':      return 'bg-warning text-dark';
        case 'POOR':      return 'bg-orange text-white';
        case 'CRITICAL':  return 'bg-danger text-white';
        default:          return 'bg-secondary text-white';
    }
}

function getSeverityBadgeClass(severity) {
    switch (severity) {
        case 'EMERGENCY': return 'bg-danger';
        case 'CRITICAL':  return 'bg-danger';
        case 'HIGH':      return 'bg-warning text-dark';
        case 'MEDIUM':    return 'bg-info text-white';
        case 'LOW':       return 'bg-secondary';
        default:          return 'bg-primary';
    }
}
</script>
