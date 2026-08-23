<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<style>
:root {
    --cc-bg: #0b111e;
    --cc-card-bg: rgba(18, 26, 43, 0.85);
    --cc-border: rgba(45, 62, 92, 0.6);
}

.frm-container {
    background-color: var(--cc-bg);
    color: #e2e8f0;
    padding: 24px;
    border-radius: 12px;
    min-height: calc(100vh - 120px);
}

.frm-card {
    background: var(--cc-card-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    margin-bottom: 20px;
}
</style>

<div class="content-wrapper">
    <div class="frm-container">
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
            <div>
                <a href="<?= base_url('operational-planning/workspace') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Workspace
                </a>
                <h2 class="h3 font-weight-bold text-white mb-0">
                    <i class="fas fa-edit text-info mr-2"></i>Susun Draft Rencana Kerja Operasional (OP-02)
                </h2>
                <small class="text-muted">
                    Kandidat Sumber: <code><?= esc($candidate['candidate_code']) ?></code> &bull; Seksi: <?= esc($candidate['feeder_name']) ?> - <?= esc($candidate['section_name']) ?>
                </small>
            </div>
            <div>
                <span class="badge badge-warning px-3 py-2 text-uppercase font-size-sm">
                    Status Awal: PLAN_DRAFT
                </span>
            </div>
        </div>

        <form method="POST" action="<?= base_url('operational-planning/workspace/store') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="candidate_id" value="<?= (int)$candidate['id'] ?>">

            <div class="row">
                
                <!-- Left: Scope & Safety -->
                <div class="col-lg-7">
                    <div class="frm-card">
                        <h5 class="text-white font-weight-bold mb-3">
                            <i class="fas fa-file-alt text-warning mr-2"></i>Ruang Lingkup & K3
                        </h5>

                        <div class="form-group">
                            <label class="small font-weight-bold text-muted">Kategori Pekerjaan Pemeliharaan:</label>
                            <select name="work_category" class="form-control bg-dark text-white border-secondary" required>
                                <option value="ROW_CLEARANCE">ROW_CLEARANCE (Perabasan & Penebangan Pohon)</option>
                                <option value="EQUIPMENT_REPAIR">EQUIPMENT_REPAIR (Perbaikan / Penggantian Peralatan SUTM)</option>
                                <option value="THERMO_CORRECTION">THERMO_CORRECTION (Koreksi Hotspot Thermovisi)</option>
                                <option value="GROUNDING_IMPROVEMENT">GROUNDING_IMPROVEMENT (Perbaikan Pembumian & Arrester)</option>
                                <option value="INSULATOR_REPLACEMENT">INSULATOR_REPLACEMENT (Penggantian Isolator Flashover / Rusak)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold text-white">Rincian Narasi Ruang Lingkup Pekerjaan (Scope Narrative):</label>
                            <textarea name="work_scope_narrative" class="form-control bg-dark text-white border-secondary" rows="4" required><?= esc($candidate['proposed_work_scope']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold text-warning">Langkah Keselamatan K3 & Prosedur Proteksi:</label>
                            <textarea name="safety_precautions" class="form-control bg-dark text-white border-secondary" rows="3" required>Wajib menggunakan APD Lengkap (Helm, Rompi, Sarung Tangan 20kV, Safety Shoes). Lakukan grounding lokal sebelum pekerjaan dimulai dan periksa tegangan sisa.</textarea>
                        </div>

                        <div class="form-group">
                            <label class="small font-weight-bold text-white d-block">Metode Pekerjaan Jaringan:</label>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="outageNo" name="outage_required" value="0" class="custom-control-input" checked>
                                <label class="custom-control-label text-success font-weight-bold" for="outageNo">PDKB (Pekerjaan Dalam Keadaan Bertegangan)</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="outageYes" name="outage_required" value="1" class="custom-control-input">
                                <label class="custom-control-label text-danger font-weight-bold" for="outageYes">PADAM (Pemadaman SUTM Diperlukan)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Timeline & Indicative Materials -->
                <div class="col-lg-5">
                    
                    <!-- Timeline Card -->
                    <div class="frm-card">
                        <h5 class="text-white font-weight-bold mb-3">
                            <i class="fas fa-clock text-info mr-2"></i>Usulan Jendela Waktu Eksekusi
                        </h5>
                        
                        <div class="form-group">
                            <label class="small text-muted font-weight-bold">Estimasi Mulai Pelaksanaan:</label>
                            <input type="datetime-local" name="proposed_execution_window_start" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d\T08:00', strtotime('+3 days')) ?>">
                        </div>

                        <div class="form-group">
                            <label class="small text-muted font-weight-bold">Estimasi Selesai Pelaksanaan:</label>
                            <input type="datetime-local" name="proposed_execution_window_end" class="form-control bg-dark text-white border-secondary" value="<?= date('Y-m-d\T16:00', strtotime('+3 days')) ?>">
                        </div>

                        <small class="text-muted d-block">
                            <em>Catatan: Jendela waktu ini murni berstatus <code>PROPOSED_WINDOW_ONLY</code> dan bukan merupakan instruksi dispatch kru operasional.</em>
                        </small>
                    </div>

                    <!-- Materials Card -->
                    <div class="frm-card">
                        <h5 class="text-white font-weight-bold mb-3">
                            <i class="fas fa-boxes text-success mr-2"></i>Kebutuhan Material Indikatif
                        </h5>

                        <div id="materialList">
                            <div class="form-row mb-2">
                                <div class="col-6">
                                    <input type="text" name="material_name[]" class="form-control form-control-sm bg-dark text-white border-secondary" value="Kabel A3C / SUTM" placeholder="Nama Material" required>
                                </div>
                                <div class="col-3">
                                    <input type="number" name="material_qty[]" class="form-control form-control-sm bg-dark text-white border-secondary" value="10" min="1" step="0.1" required>
                                </div>
                                <div class="col-3">
                                    <input type="text" name="material_unit[]" class="form-control form-control-sm bg-dark text-white border-secondary" value="meter" required>
                                </div>
                            </div>
                            <div class="form-row mb-2">
                                <div class="col-6">
                                    <input type="text" name="material_name[]" class="form-control form-control-sm bg-dark text-white border-secondary" value="Isolator Tumpu 20kV" placeholder="Nama Material">
                                </div>
                                <div class="col-3">
                                    <input type="number" name="material_qty[]" class="form-control form-control-sm bg-dark text-white border-secondary" value="2" min="1" step="0.1">
                                </div>
                                <div class="col-3">
                                    <input type="text" name="material_unit[]" class="form-control form-control-sm bg-dark text-white border-secondary" value="buah">
                                </div>
                            </div>
                        </div>

                        <small class="text-muted d-block mt-2">
                            <em>Invariant: <code>INDICATIVE_MATERIAL_REQUIREMENT ≠ PROCUREMENT_REQUEST</code></em>
                        </small>
                    </div>

                    <button type="submit" class="btn btn-info font-weight-bold btn-block py-2">
                        <i class="fas fa-save mr-1"></i> Simpan Dokumen Draft Rencana Kerja
                    </button>

                </div>

            </div>
        </form>

    </div>
</div>

<?= $this->endSection() ?>
