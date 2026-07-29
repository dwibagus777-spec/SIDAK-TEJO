<?= $this->extend('layouts/admin') ?>
<?= $this->section('title') ?>Auto Ranking — SIDAK TEJO<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Auto Ranking<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.ranking-card { border-radius: 18px; border: 1px solid #e2e8f0; background: #fff; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
.ranking-hero { background: linear-gradient(135deg, #0f172a, #1e3a5f); color: #fff; padding: 28px 32px; }
.rank-row { display: flex; align-items: center; gap: 14px; padding: 12px 18px; border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.rank-row:hover { background: #f8fafc; }
.rank-num { font-size: 16px; font-weight: 800; min-width: 30px; text-align: center; }
.rank-bar-wrap { flex-grow: 1; background: #f1f5f9; border-radius: 10px; height: 8px; }
.rank-bar-fill { height: 8px; border-radius: 10px; }
.section-lbl { font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; border-left: 3px solid #0284c7; padding-left: 10px; margin-bottom: 16px; }
</style>

<div class="ranking-card mb-4">
    <div class="ranking-hero">
        <h4 class="fw-bold mb-1"><i class="fas fa-ranking-star me-2"></i>Auto Ranking System</h4>
        <p class="text-white-50 mb-0" style="font-size: 13px;">Ranking otomatis berdasarkan realisasi, SLA, dan kecepatan penyelesaian</p>
    </div>

    <div class="p-4">
        <div class="row g-4">

            <!-- ULP Ranking (Step 9) -->
            <div class="col-lg-4">
                <div class="section-lbl"><i class="fas fa-building me-1"></i>Ranking ULP</div>
                <?php if (empty($ulpRanking)): ?>
                <p class="text-muted text-center py-3 small">Belum ada data.</p>
                <?php else: ?>
                <?php $maxUlp = max(array_column($ulpRanking, 'selesai') ?: [1]); ?>
                <?php foreach ($ulpRanking as $i => $u): ?>
                <div class="rank-row">
                    <span class="rank-num"><?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i+1))) ?></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark" style="font-size: 13px;"><?= esc($u['ulp_name']) ?></div>
                        <div class="rank-bar-wrap mt-1">
                            <div class="rank-bar-fill bg-primary" style="width: <?= $maxUlp > 0 ? round($u['selesai']/$maxUlp*100) : 0 ?>%;"></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-success"><?= number_format($u['selesai']) ?></div>
                        <small class="text-muted"><?= $u['pct'] ?>%</small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Petugas Ranking -->
            <div class="col-lg-4">
                <div class="section-lbl"><i class="fas fa-user-hard-hat me-1"></i>Ranking Petugas</div>
                <?php if (empty($leaderboard)): ?>
                <p class="text-muted text-center py-3 small">Belum ada data gamifikasi.</p>
                <?php else: ?>
                <?php $maxPts = max(array_column($leaderboard, 'total_points') ?: [1]); ?>
                <?php foreach ($leaderboard as $i => $lb): ?>
                <div class="rank-row">
                    <span class="rank-num <?= $i === 0 ? 'text-warning' : ($i === 1 ? 'text-secondary' : '') ?>"><?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i+1))) ?></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark" style="font-size: 13px;"><?= esc($lb['nama_pegawai'] ?? 'User') ?></div>
                        <small class="text-muted" style="font-size: 10px;"><?= esc($lb['ulp'] ?? '') ?> · <?= ucfirst($lb['level'] ?? 'bronze') ?></small>
                        <div class="rank-bar-wrap mt-1">
                            <div class="rank-bar-fill" style="width: <?= $maxPts > 0 ? round($lb['total_points']/$maxPts*100) : 0 ?>%; background: #f59e0b;"></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-warning"><?= number_format($lb['total_points'] ?? 0) ?></div>
                        <small class="text-muted">pts</small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Penyulang Ranking -->
            <div class="col-lg-4">
                <div class="section-lbl"><i class="fas fa-bolt me-1"></i>Ranking Penyulang</div>
                <?php if (empty($penyulangRanking)): ?>
                <p class="text-muted text-center py-3 small">Belum ada data.</p>
                <?php else: ?>
                <?php $maxPen = max(array_column($penyulangRanking, 'total_temuan') ?: [1]); ?>
                <?php foreach ($penyulangRanking as $i => $p): ?>
                <div class="rank-row">
                    <span class="rank-num"><?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i+1))) ?></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark" style="font-size: 13px;"><?= esc($p['penyulang_name']) ?></div>
                        <div class="rank-bar-wrap mt-1">
                            <div class="rank-bar-fill bg-danger" style="width: <?= $maxPen > 0 ? round($p['total_temuan']/$maxPen*100) : 0 ?>%;"></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-danger"><?= number_format($p['total_temuan']) ?></div>
                        <small class="text-muted"><?= $p['selesai'] ?> selesai</small>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?= $this->endSection() ?>
