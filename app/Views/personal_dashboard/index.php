<?= $this->extend('layouts/admin') ?>
<?= $this->section('title') ?>My Dashboard — <?= esc($userName) ?><?= $this->endSection() ?>
<?= $this->section('page_title') ?>Dashboard Pribadi<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
// Greeting logic
$hour = (int)date('G');
if ($hour >= 5  && $hour < 12) $greeting = 'Selamat Pagi';
elseif ($hour >= 12 && $hour < 15) $greeting = 'Selamat Siang';
elseif ($hour >= 15 && $hour < 19) $greeting = 'Selamat Sore';
else $greeting = 'Selamat Malam';

$levelMeta    = $levelMeta ?? ['label'=>'Bronze','color'=>'#cd7f32','bg'=>'#fef3c7','icon'=>'fa-medal','next'=>250];
$gamData      = $gamData   ?? [];
$todayStats   = $todayStats ?? [];
$weekStats    = $weekStats  ?? ['labels'=>[],'values'=>[],'total'=>0];
$monthStats   = $monthStats ?? [];
$achievements = $achievements ?? [];
$todayTasks   = $todayTasks   ?? [];
$timeline     = $timeline     ?? [];
$leaderboard  = $leaderboard  ?? [];
$curPoints    = $curPoints ?? 0;
$levelPct     = $levelPct  ?? 0;
$nextLevel    = $nextLevel ?? 250;
?>
<style>
/* === Personal Dashboard Styles === */
.pd-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0c4a6e 100%);
    border-radius: 24px;
    padding: 32px 36px;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 28px;
}
.pd-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.pd-hero-greeting { font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.7); letter-spacing: 0.5px; }
.pd-hero-name { font-size: 28px; font-weight: 800; line-height: 1.2; }
.pd-hero-sub { font-size: 13px; color: rgba(255,255,255,0.65); margin-top: 4px; }

.level-badge {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 16px; border-radius: 50px; font-size: 12px; font-weight: 800;
    letter-spacing: 0.5px; text-transform: uppercase;
}
.level-bar-wrap { background: rgba(255,255,255,0.15); border-radius: 20px; height: 8px; margin-top: 8px; }
.level-bar-fill { height: 8px; border-radius: 20px; background: linear-gradient(90deg, #f59e0b, #fbbf24); transition: width 1.2s ease; }

.pd-kpi-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    transition: all 0.25s;
}
.pd-kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
.pd-kpi-val { font-size: 34px; font-weight: 800; line-height: 1; }
.pd-kpi-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; }
.pd-kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }

/* Timeline */
.pd-timeline { position: relative; padding-left: 32px; }
.pd-timeline::before { content: ''; position: absolute; left: 13px; top: 0; bottom: 0; width: 2px; background: linear-gradient(180deg, #0284c7, #e2e8f0); border-radius: 2px; }
.pd-tl-item { position: relative; padding-bottom: 18px; }
.pd-tl-dot { position: absolute; left: -26px; top: 4px; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #0284c7; background: #fff; }
.pd-tl-dot.active { background: #0284c7; }
.pd-tl-time { font-size: 10px; font-weight: 700; color: #94a3b8; letter-spacing: 0.5px; }
.pd-tl-text { font-size: 13px; font-weight: 600; color: #1e293b; }
.pd-tl-pts { font-size: 10px; color: #10b981; font-weight: 700; }

/* Today Tasks */
.task-card {
    border-radius: 14px; border: 1px solid #e2e8f0; padding: 14px 16px;
    margin-bottom: 10px; transition: all 0.2s; background: #fff;
}
.task-card:hover { border-color: #0284c7; box-shadow: 0 4px 12px rgba(2,132,199,0.1); }
.task-priority-badge { font-size: 9px; font-weight: 800; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.5px; }
.task-emergency { background: #fef2f2; border-left: 3px solid #ef4444; }
.task-high { background: #fffbeb; border-left: 3px solid #f59e0b; }
.task-medium { background: #eff6ff; border-left: 3px solid #3b82f6; }
.task-low { background: #f0fdf4; border-left: 3px solid #10b981; }

/* Achievements */
.ach-badge {
    display: flex; flex-direction: column; align-items: center;
    padding: 14px 10px; border-radius: 14px; border: 1px solid #e2e8f0;
    text-align: center; transition: all 0.2s;
    background: #fff; min-width: 80px;
}
.ach-badge.earned { border-color: #f59e0b; background: #fffbeb; }
.ach-badge.locked { opacity: 0.4; filter: grayscale(1); }
.ach-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-bottom: 6px; }
.ach-name { font-size: 10px; font-weight: 700; color: #374151; line-height: 1.2; }

/* Leaderboard */
.lb-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 12px; margin-bottom: 6px; background: #f8fafc; }
.lb-rank { font-size: 14px; font-weight: 800; width: 24px; text-align: center; }
.lb-rank.top-1 { color: #f59e0b; }
.lb-rank.top-2 { color: #9ca3af; }
.lb-rank.top-3 { color: #cd7f32; }

/* Heatmap calendar */
.heatmap-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.heatmap-day { aspect-ratio: 1; border-radius: 4px; background: #f1f5f9; cursor: pointer; position: relative; }
.heatmap-day[data-count="0"] { background: #f1f5f9; }
.heatmap-day[data-count="1"] { background: #bfdbfe; }
.heatmap-day[data-count="2"] { background: #60a5fa; }
.heatmap-day[data-count="3"] { background: #f59e0b; }
.heatmap-day[data-count="4"] { background: #ef4444; }
.heatmap-day:hover::after { content: attr(data-tooltip); position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%); background: #1e293b; color: #fff; font-size: 10px; padding: 3px 7px; border-radius: 6px; white-space: nowrap; z-index: 10; }

.section-label { font-size: 11px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; border-left: 3px solid #0284c7; padding-left: 10px; margin-bottom: 16px; }

/* SLA indicator */
.sla-chip { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 8px; }
.sla-ok { background: #dcfce7; color: #16a34a; }
.sla-warn { background: #fef9c3; color: #ca8a04; }
.sla-danger { background: #fee2e2; color: #dc2626; }
</style>

<!-- ===================== HERO SECTION ===================== -->
<div class="pd-hero">
    <div class="row align-items-center g-3">
        <div class="col-md-7">
            <div class="pd-hero-greeting"><?= $greeting ?>, <?= date('l, d F Y') ?></div>
            <div class="pd-hero-name"><?= esc($userName) ?></div>
            <div class="pd-hero-sub">
                <span class="me-2"><i class="fas fa-id-badge me-1"></i><?= esc(get_role_label($userRole)) ?></span>
                <span class="me-2"><i class="fas fa-building me-1"></i><?= esc($ulpName) ?></span>
                <span><i class="fas fa-clock me-1"></i>Login <?= $loginAt ?></span>
            </div>
            <!-- Level Progress -->
            <div class="mt-3">
                <div class="level-badge mt-2" style="background: <?= $levelMeta['bg'] ?>; color: <?= $levelMeta['color'] ?>;">
                    <i class="fas <?= $levelMeta['icon'] ?>"></i>
                    <?= $levelMeta['label'] ?> — <?= number_format($curPoints) ?> pts
                </div>
                <div class="level-bar-wrap mt-2" style="max-width: 260px;">
                    <div class="level-bar-fill" style="width: <?= $levelPct ?>%;"></div>
                </div>
                <small class="text-white-50 d-block mt-1" style="font-size: 10px;">
                    <?= number_format($curPoints) ?> / <?= number_format($nextLevel) ?> pts menuju level berikutnya
                </small>
            </div>
        </div>
        <div class="col-md-5 text-md-end">
            <!-- Streak -->
            <div class="d-inline-block p-3 rounded-3" style="background: rgba(255,255,255,0.08);">
                <div style="font-size: 36px; font-weight: 800; color: #fb923c;"><?= $gamData['streak_days'] ?? 0 ?> 🔥</div>
                <div style="font-size: 12px; color: rgba(255,255,255,0.7);">Hari Berturut-turut Aktif</div>
            </div>
        </div>
    </div>
</div>

<!-- ===================== KPI ROW (Step 1 + 4) ===================== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pd-kpi-card text-center">
            <div class="pd-kpi-icon mx-auto mb-2" style="background:#eff6ff;color:#3b82f6;"><i class="fas fa-clipboard-list"></i></div>
            <div class="pd-kpi-val text-primary" id="pd-temuan-hari"><?= $todayStats['temuan_hari_ini'] ?? 0 ?></div>
            <div class="pd-kpi-lbl">Temuan Hari Ini</div>
            <div class="progress mt-2" style="height: 5px;">
                <div class="progress-bar bg-primary" style="width: <?= $todayStats['pct_hari_ini'] ?? 0 ?>%;"></div>
            </div>
            <small class="text-muted" style="font-size: 10px;">Target: <?= $todayStats['target_harian'] ?? 5 ?> temuan/hari</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pd-kpi-card text-center">
            <div class="pd-kpi-icon mx-auto mb-2" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-circle-check"></i></div>
            <div class="pd-kpi-val text-success" id="pd-selesai-hari"><?= $todayStats['selesai_hari_ini'] ?? 0 ?></div>
            <div class="pd-kpi-lbl">Selesai Hari Ini</div>
            <small class="text-success mt-1 d-block" style="font-size: 10px;">✓ Tuntas hari ini</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pd-kpi-card text-center">
            <div class="pd-kpi-icon mx-auto mb-2" style="background:#fef2f2;color:#ef4444;"><i class="fas fa-hourglass-half"></i></div>
            <div class="pd-kpi-val text-danger" id="pd-belum"><?= $todayStats['belum_selesai'] ?? 0 ?></div>
            <div class="pd-kpi-lbl">Belum Selesai</div>
            <small class="text-danger mt-1 d-block" style="font-size: 10px;">Outstanding</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pd-kpi-card text-center">
            <div class="pd-kpi-icon mx-auto mb-2" style="background:#fffbeb;color:#d97706;"><i class="fas fa-star"></i></div>
            <div class="pd-kpi-val" style="color:#d97706;" id="pd-points"><?= number_format($curPoints) ?></div>
            <div class="pd-kpi-lbl">Total Poin</div>
            <small class="mt-1 d-block" style="font-size: 10px; color: <?= $levelMeta['color'] ?>;">
                <i class="fas <?= $levelMeta['icon'] ?>"></i> <?= $levelMeta['label'] ?>
            </small>
        </div>
    </div>
</div>

<!-- ===================== MAIN GRID ===================== -->
<div class="row g-4">

    <!-- LEFT COL: Today Tasks + Timeline -->
    <div class="col-lg-8">

        <!-- STEP 2: TODAY TASK -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                <span class="section-label"><i class="fas fa-tasks text-primary me-2"></i>Pekerjaan Hari Ini</span>
                <a href="<?= site_url('temuan') ?>" class="btn btn-outline-primary btn-sm rounded-pill" style="font-size: 11px;">
                    Lihat Semua <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($todayTasks)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-3x mb-2 text-success opacity-50"></i>
                    <p class="fw-semibold mb-0">Tidak ada pekerjaan tertunda!</p>
                    <small>Semua sudah selesai atau belum ada temuan.</small>
                </div>
                <?php else: ?>
                <?php foreach ($todayTasks as $task): ?>
                <?php
                    $pri = strtolower($task['prioritas'] ?? 'low');
                    $sisa = (int)($task['sisa_hari'] ?? 99);
                    $slaClass = $sisa < 0 ? 'sla-danger' : ($sisa <= 2 ? 'sla-warn' : 'sla-ok');
                    $slaText  = $sisa < 0 ? 'Overdue ' . abs($sisa) . 'h' : ($sisa == 0 ? 'Hari ini!' : $sisa . ' hari');
                    $priColors = ['emergency'=>['#ef4444','#fee2e2'],'high'=>['#d97706','#fffbeb'],'medium'=>['#3b82f6','#eff6ff'],'low'=>['#16a34a','#f0fdf4']];
                    $pcol = $priColors[$pri] ?? $priColors['low'];
                ?>
                <a href="<?= site_url('temuan/detail/' . $task['id']) ?>" class="text-decoration-none">
                    <div class="task-card task-<?= $pri ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1 me-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="task-priority-badge" style="background: <?= $pcol[1] ?>; color: <?= $pcol[0] ?>;">
                                        <?= strtoupper($task['prioritas'] ?? '-') ?>
                                    </span>
                                    <span class="fw-bold text-dark" style="font-size: 12px;"><?= esc($task['nomor_temuan'] ?? '-') ?></span>
                                </div>
                                <div class="text-dark fw-semibold" style="font-size: 13px;"><?= esc(mb_substr($task['jenis_temuan'] ?? '-', 0, 60)) ?></div>
                                <small class="text-muted"><?= esc($task['penyulang'] ?? '') ?><?= !empty($task['section']) ? ' / ' . esc($task['section']) : '' ?></small>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="sla-chip <?= $slaClass ?>"><?= $slaText ?></span>
                                <div class="mt-1">
                                    <span class="badge bg-secondary-subtle text-secondary" style="font-size: 9px;"><?= esc($task['status'] ?? '-') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- STEP 3: WORK TIMELINE -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                <span class="section-label"><i class="fas fa-timeline text-info me-2"></i>Work Timeline Hari Ini</span>
                <small class="text-muted" style="font-size: 11px;"><?= date('d F Y') ?></small>
            </div>
            <div class="card-body px-4 pb-4">
                <?php if (empty($timeline)): ?>
                <div class="text-center text-muted py-3" style="font-size: 13px;">
                    <i class="fas fa-clock fa-2x mb-2 opacity-30"></i>
                    <p class="mb-0">Belum ada aktivitas hari ini.</p>
                </div>
                <?php else: ?>
                <div class="pd-timeline">
                    <?php foreach ($timeline as $tl): ?>
                    <?php
                        $actionIcons = [
                            'LOGIN' => ['fa-right-to-bracket','text-primary'],
                            'INPUT_TEMUAN' => ['fa-plus-circle','text-success'],
                            'UPDATE_TEMUAN' => ['fa-pen-to-square','text-warning'],
                            'SELESAI_TEMUAN' => ['fa-circle-check','text-success'],
                            'UPLOAD_FOTO' => ['fa-camera','text-info'],
                            'EMERGENCY_SELESAI' => ['fa-bolt','text-danger'],
                        ];
                        $tlIcon = $actionIcons[$tl['action_type']] ?? ['fa-circle','text-secondary'];
                    ?>
                    <div class="pd-tl-item">
                        <div class="pd-tl-dot active"></div>
                        <div class="pd-tl-time"><?= date('H:i', strtotime($tl['created_at'])) ?></div>
                        <div class="pd-tl-text">
                            <i class="fas <?= $tlIcon[0] ?> <?= $tlIcon[1] ?> me-1"></i>
                            <?= esc($tl['description']) ?>
                        </div>
                        <?php if ($tl['points'] > 0): ?>
                        <div class="pd-tl-pts">+<?= $tl['points'] ?> pts</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- STEP 4: ACHIEVEMENT PROGRESS (Weekly/Monthly) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <span class="section-label"><i class="fas fa-chart-bar text-success me-2"></i>Progress Kerja</span>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="fw-bold text-dark" style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Bulan Ini</div>
                            <div class="d-flex align-items-end gap-2 mt-1">
                                <span class="fw-bold" style="font-size: 28px; color: #0284c7;"><?= $monthStats['this_month'] ?? 0 ?></span>
                                <span class="text-muted" style="font-size: 12px; padding-bottom: 4px;">/ <?= $monthStats['target_month'] ?? 100 ?> target</span>
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-primary" style="width: <?= $monthStats['pct_month'] ?? 0 ?>%;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="fw-bold text-dark" style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Bulan Lalu</div>
                            <div class="d-flex align-items-end gap-2 mt-1">
                                <span class="fw-bold" style="font-size: 28px; color: #64748b;"><?= $monthStats['last_month'] ?? 0 ?></span>
                                <span class="text-muted" style="font-size: 12px; padding-bottom: 4px;">temuan</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="fw-bold text-dark" style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Tahun Ini</div>
                            <div class="d-flex align-items-end gap-2 mt-1">
                                <span class="fw-bold" style="font-size: 28px; color: #16a34a;"><?= $monthStats['this_year'] ?? 0 ?></span>
                                <span class="text-muted" style="font-size: 12px; padding-bottom: 4px;">/ <?= number_format($monthStats['target_year'] ?? 1000) ?></span>
                            </div>
                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: <?= $monthStats['pct_year'] ?? 0 ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Weekly Heatmap mini chart -->
                <div class="mt-3">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 8px;">AKTIVITAS 7 HARI TERAKHIR</div>
                    <canvas id="weeklyChart" height="70"></canvas>
                </div>
            </div>
        </div>

    </div><!-- /LEFT COL -->

    <!-- RIGHT COL: Level, Achievements, Leaderboard -->
    <div class="col-lg-4">

        <!-- STEP 5+6: LEVEL & POINTS -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-2"
                         style="width: 70px; height: 70px; background: <?= $levelMeta['bg'] ?>; font-size: 30px; color: <?= $levelMeta['color'] ?>;">
                        <i class="fas <?= $levelMeta['icon'] ?>"></i>
                    </div>
                    <div class="fw-bold" style="font-size: 20px; color: <?= $levelMeta['color'] ?>;"><?= $levelMeta['label'] ?></div>
                    <div class="text-muted" style="font-size: 12px;">Level Petugas</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="p-2 rounded-3 bg-light">
                            <div class="fw-bold text-primary" style="font-size: 16px;"><?= number_format($curPoints) ?></div>
                            <div style="font-size: 9px; color: #64748b; font-weight: 700; text-transform: uppercase;">Poin</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded-3 bg-light">
                            <div class="fw-bold text-warning" style="font-size: 16px;"><?= $gamData['streak_days'] ?? 0 ?></div>
                            <div style="font-size: 9px; color: #64748b; font-weight: 700; text-transform: uppercase;">Streak</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 rounded-3 bg-light">
                            <div class="fw-bold text-success" style="font-size: 16px;"><?= count($achievements) ?></div>
                            <div style="font-size: 9px; color: #64748b; font-weight: 700; text-transform: uppercase;">Badge</div>
                        </div>
                    </div>
                </div>
                <div class="level-bar-wrap">
                    <div class="level-bar-fill" style="width: <?= $levelPct ?>%;"></div>
                </div>
                <small class="text-muted" style="font-size: 10px;"><?= $levelPct ?>% menuju next level</small>
            </div>
        </div>

        <!-- STEP 7: ACHIEVEMENTS -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <span class="section-label"><i class="fas fa-trophy text-warning me-2"></i>Achievement</span>
            </div>
            <div class="card-body px-4 pb-4">
                <?php
                $earnedKeys = array_column($achievements, 'achievement_key');
                $allDefs    = $allAchDefs ?? [];
                ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($allDefs as $key => $def): ?>
                    <?php $isEarned = in_array($key, $earnedKeys); ?>
                    <div class="ach-badge <?= $isEarned ? 'earned' : 'locked' ?>"
                         title="<?= esc($def['desc']) ?>">
                        <div class="ach-icon" style="background: <?= $isEarned ? '#fef3c7' : '#f1f5f9' ?>; color: <?= $isEarned ? '#d97706' : '#94a3b8' ?>;">
                            <i class="fas <?= $def['icon'] ?>"></i>
                        </div>
                        <div class="ach-name"><?= esc($def['name']) ?></div>
                        <?php if ($isEarned): ?>
                        <small style="font-size: 9px; color: #10b981; font-weight: 700;">✓ Diraih</small>
                        <?php else: ?>
                        <small style="font-size: 9px; color: #94a3b8;">🔒 Terkunci</small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- STEP 9: LEADERBOARD (mini) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                <span class="section-label"><i class="fas fa-ranking-star text-danger me-2"></i>Top Petugas</span>
                <a href="<?= site_url('ranking') ?>" class="btn btn-link btn-sm p-0" style="font-size: 11px;">Lihat Semua</a>
            </div>
            <div class="card-body px-3 pb-4">
                <?php foreach ($leaderboard as $i => $lb): ?>
                <div class="lb-row <?= $lb['user_id'] == session()->get('user_id') ? 'border border-primary' : '' ?>">
                    <span class="lb-rank <?= $i === 0 ? 'top-1' : ($i === 1 ? 'top-2' : ($i === 2 ? 'top-3' : '')) ?>">
                        <?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i + 1))) ?>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark" style="font-size: 12px;"><?= esc($lb['nama_pegawai'] ?? 'User') ?></div>
                        <small class="text-muted" style="font-size: 10px;"><?= esc($lb['ulp'] ?? '') ?></small>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold text-warning" style="font-size: 12px;"><?= number_format($lb['total_points'] ?? 0) ?></span>
                        <div style="font-size: 9px; color: #94a3b8;">pts</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($leaderboard)): ?>
                <div class="text-center text-muted py-3 small">Belum ada data ranking.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SLA Terdekat -->
        <?php if (!empty($todayStats['sla_terdekat'])): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <span class="section-label"><i class="fas fa-bell text-danger me-2"></i>SLA Terdekat</span>
            </div>
            <div class="card-body px-4 pb-4">
                <?php foreach ($todayStats['sla_terdekat'] as $s): ?>
                <?php $sisa = (int)$s['sisa_hari']; $cls = $sisa < 0 ? 'sla-danger' : ($sisa <= 2 ? 'sla-warn' : 'sla-ok'); ?>
                <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded-2 bg-light">
                    <div>
                        <div class="fw-semibold" style="font-size: 11px;"><?= esc($s['nomor_temuan'] ?? '-') ?></div>
                        <div style="font-size: 10px; color: #64748b;"><?= esc($s['prioritas'] ?? '') ?></div>
                    </div>
                    <span class="sla-chip <?= $cls ?>"><?= $sisa < 0 ? 'Overdue '.abs($sisa).'h' : $sisa.' hari' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /RIGHT COL -->
</div><!-- /MAIN GRID -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Weekly Activity Chart (Step 4)
    var weekCtx = document.getElementById('weeklyChart');
    if (weekCtx) {
        new Chart(weekCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($weekStats['labels'] ?? []) ?>,
                datasets: [{
                    label: 'Temuan',
                    data: <?= json_encode($weekStats['values'] ?? []) ?>,
                    backgroundColor: 'rgba(2, 132, 199, 0.7)',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                    x: { ticks: { font: { size: 9 } }, grid: { display: false } }
                }
            }
        });
    }

    // Live Stats Auto-Refresh every 60s
    setInterval(function() {
        fetch('<?= site_url('my-dashboard/api-stats') ?>')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                var m = {
                    'pd-temuan-hari': d.today.temuan_hari_ini,
                    'pd-selesai-hari': d.today.selesai_hari_ini,
                    'pd-belum': d.today.belum_selesai,
                    'pd-points': d.points,
                };
                Object.keys(m).forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el && m[id] !== undefined) el.textContent = m[id];
                });
            }).catch(()=>{});
    }, 60000);
});
</script>

<?= $this->endSection() ?>
