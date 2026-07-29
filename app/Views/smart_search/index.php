<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Smart Search — SIDAK TEJO<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Smart Search<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.search-result-card {
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    transition: all 0.25s;
    background: #fff;
}
.search-result-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    border-color: #0284c7;
}
.search-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
    border-radius: 20px;
    padding: 36px;
    color: #fff;
    margin-bottom: 28px;
}
.search-hero .form-control {
    border-radius: 14px;
    font-size: 16px;
    padding: 14px 20px;
    border: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}
.search-section-label {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: #64748b;
    border-left: 3px solid #0284c7;
    padding-left: 10px;
    margin-bottom: 14px;
}
.badge-priority-emergency { background: #ef4444; color: #fff; }
.badge-priority-high      { background: #f59e0b; color: #fff; }
.badge-priority-medium    { background: #3b82f6; color: #fff; }
.badge-priority-low       { background: #10b981; color: #fff; }
.result-item-url {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
}
</style>

<div class="search-hero">
    <h4 class="fw-bold mb-2"><i class="fas fa-search me-2"></i>Smart Search</h4>
    <p class="text-white-50 mb-3" style="font-size: 13px;">Cari Temuan, Work Order, Penyulang secara instan</p>
    <form method="GET" action="<?= site_url('smart-search') ?>">
        <div class="input-group">
            <input type="text" name="q" class="form-control"
                   placeholder="Ketik minimal 2 karakter... cth: EMERGENCY, Penyulang 09, WO-2025"
                   value="<?= esc($q ?? '') ?>" autofocus autocomplete="off">
            <button class="btn btn-primary px-4 fw-bold" type="submit">
                <i class="fas fa-search me-1"></i> Cari
            </button>
        </div>
    </form>
</div>

<?php if ($searched): ?>
<div class="mb-3">
    <span class="fw-semibold text-secondary">
        Menampilkan <strong><?= number_format($total) ?></strong> hasil untuk
        "<span class="text-primary"><?= esc($q) ?></span>"
    </span>
</div>

<?php if ($total === 0): ?>
<div class="text-center py-5 text-muted">
    <i class="fas fa-search fa-3x mb-3 opacity-30"></i>
    <p class="fw-semibold">Tidak ada hasil yang ditemukan.</p>
    <small>Coba kata kunci lain, misal kode temuan, jenis, atau penyulang.</small>
</div>
<?php else: ?>

<?php foreach ($results as $section => $items): ?>
<?php if (empty($items)) continue; ?>

<div class="mb-4">
    <div class="search-section-label"><?= esc($section) ?> (<?= count($items) ?>)</div>
    <div class="row g-3">
        <?php foreach ($items as $item): ?>
        <div class="col-12">
            <a href="<?= $item['_url'] ?>" class="text-decoration-none">
                <div class="search-result-card p-3 d-flex align-items-start gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:42px;height:42px;background:#f1f5f9;">
                        <i class="fas <?= $item['_icon'] ?? 'fa-file' ?> <?= $item['_color'] ?? 'text-secondary' ?>"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-dark text-truncate"><?= esc($item['_label'] ?? '') ?></div>
                        <?php if (!empty($item['prioritas'])): ?>
                        <span class="badge badge-priority-<?= strtolower($item['prioritas']) ?> badge-sm me-1">
                            <?= esc($item['prioritas']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($item['status'])): ?>
                        <span class="badge bg-secondary-subtle text-secondary badge-sm">
                            <?= esc($item['status']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($item['jumlah_temuan'])): ?>
                        <span class="badge bg-info-subtle text-info badge-sm">
                            <?= $item['jumlah_temuan'] ?> Temuan
                        </span>
                        <?php endif; ?>
                        <div class="result-item-url text-truncate"><?= $item['_url'] ?></div>
                    </div>
                    <div class="flex-shrink-0">
                        <i class="fas fa-chevron-right text-muted opacity-50" style="font-size: 12px;"></i>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
