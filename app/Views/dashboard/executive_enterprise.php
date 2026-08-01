<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Top Header & Title -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom" style="gap: 12px;">
        <div>
            <span class="badge bg-warning text-dark font-weight-bold mb-1" style="font-size: 11px;">Selamat Datang, <?= esc($userName) ?>!</span>
            <h4 class="fw-bold mb-1 text-primary d-flex align-items-center" style="font-family: 'Outfit', sans-serif;">
                <i class="fas fa-chart-pie text-warning me-2 fs-3"></i>
                EXECUTIVE DASHBOARD ENTERPRISE (PLN UID SYSTEM)
                <span class="badge bg-primary ms-2 rounded-pill font-weight-normal" style="font-size: 10px;">V18 ENTERPRISE</span>
            </h4>
            <p class="text-muted small mb-0">Executive Analytics, Monitoring Realtime, & Multi-Dimensional Data Visualization</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="live-clock" class="badge bg-white text-dark border p-2 font-monospace fw-bold shadow-xs">
                <i class="fas fa-clock text-primary me-1"></i> <?= date('d M Y H:i:s') ?> WIB
            </span>
        </div>
    </div>

    <!-- GLOBAL FILTER TOOLBAR (ULP, BULAN, TAHUN) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white border-start border-4 border-primary">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0 text-primary d-flex align-items-center" style="font-size: 13px;">
                <i class="fas fa-filter text-warning me-2"></i> FILTER GLOBAL EXECUTIVE DASHBOARD
            </h6>
            <small class="text-muted" id="filter-status-text"><i class="fas fa-sync fa-spin me-1 text-primary"></i> Data Realtime Terhubung</small>
        </div>
        <div class="card-body p-3">
            <form id="global-dashboard-filter-form" class="row g-2 align-items-end">
                <!-- 1. Filter ULP -->
                <div class="col-md-4 col-sm-6">
                    <label for="filter_ulp" class="form-label small fw-bold text-dark mb-1">Unit ULP PLN</label>
                    <select id="filter_ulp" class="form-select form-select-sm border-secondary-subtle font-weight-bold">
                        <option value="">-- Semua ULP (UP3 Sidoarjo) --</option>
                        <?php foreach ($ulps as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (string)$ulpSel === (string)$u['id'] ? 'selected' : '' ?>>
                                <?= esc($u['nama_ulp']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Filter Bulan -->
                <div class="col-md-3 col-sm-6">
                    <label for="filter_bulan" class="form-label small fw-bold text-dark mb-1">Bulan Inspeksi</label>
                    <select id="filter_bulan" class="form-select form-select-sm border-secondary-subtle font-weight-bold">
                        <?php
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                        ];
                        foreach ($months as $num => $name):
                        ?>
                            <option value="<?= $num ?>" <?= (int)$bulanSel === $num ? 'selected' : '' ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Filter Tahun -->
                <div class="col-md-3 col-sm-6">
                    <label for="filter_tahun" class="form-label small fw-bold text-dark mb-1">Tahun</label>
                    <select id="filter_tahun" class="form-select form-select-sm border-secondary-subtle font-weight-bold">
                        <?php
                        $curYr = (int)date('Y');
                        for ($y = $curYr + 1; $y >= $curYr - 3; $y--):
                        ?>
                            <option value="<?= $y ?>" <?= (int)$tahunSel === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Button Reset -->
                <div class="col-md-2 col-sm-6 d-grid">
                    <button type="button" id="btn-reset-global-filter" class="btn btn-sm btn-outline-secondary font-weight-bold rounded-3">
                        <i class="fas fa-undo me-1"></i> Reset Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI CARDS GRID -->
    <div class="row g-3 mb-4">
        <!-- Total Temuan -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">TOTAL TEMUAN</span>
                        <h2 class="fw-bold mb-0 text-dark" id="kpi-total"><?= number_format($kpi['total']) ?></h2>
                    </div>
                    <div class="p-3 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-clipboard-list fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Temuan Selesai -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-success h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">TEMUAN SELESAI</span>
                        <h2 class="fw-bold mb-0 text-success" id="kpi-selesai"><?= number_format($kpi['selesai']) ?></h2>
                        <small class="text-muted" id="kpi-rate-text">Rate: <?= $kpi['rate'] ?>%</small>
                    </div>
                    <div class="p-3 rounded-circle bg-success bg-opacity-10 text-success">
                        <i class="fas fa-circle-check fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dalam Proses -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-warning h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">DALAM PROSES</span>
                        <h2 class="fw-bold mb-0 text-warning" id="kpi-proses"><?= number_format($kpi['proses']) ?></h2>
                    </div>
                    <div class="p-3 rounded-circle bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-spinner fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Belum Ditindaklanjuti -->
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 border-start border-4 border-danger h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-bold d-block">BELUM DITINDAK</span>
                        <h2 class="fw-bold mb-0 text-danger" id="kpi-belum"><?= number_format($kpi['belum']) ?></h2>
                    </div>
                    <div class="p-3 rounded-circle bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-triangle-exclamation fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 1: LINE CHART TEMUAN VS SELESAI & DONUT CHART STATUS -->
    <div class="row g-3 mb-4">
        <!-- Line Chart: Trend Temuan vs Penyelesaian -->
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-primary d-flex align-items-center">
                        <i class="fas fa-chart-line text-primary me-2"></i> Tren Temuan vs Realisasi Penyelesaian
                    </h6>
                    <span class="badge bg-light text-primary border">Bulanan</span>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 320px;">
                        <canvas id="lineChartTemuan"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donut Chart: Status & SLA Breakdown with Total in Center -->
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-chart-pie text-success me-2"></i> Status Completion Breakdown
                    </h6>
                </div>
                <div class="card-body p-3 text-center position-relative">
                    <div style="position: relative; height: 260px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="donutChartStatus"></canvas>
                    </div>
                    <div id="donut-legend-container" class="mt-2 text-center small fw-bold"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2: BAR CHART PERFORMA ULP & PIE CHARTS (JENIS & PRIORITAS) -->
    <div class="row g-3 mb-4">
        <!-- Bar Chart: Performa per ULP (with numerical values on bar ends) -->
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-primary d-flex align-items-center">
                        <i class="fas fa-chart-bar text-warning me-2"></i> Performa Temuan per ULP Unit (PLN UP3 SIDOARJO)
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 280px;">
                        <canvas id="barChartUlp"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart: Breakdown Jenis Temuan -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-bolt text-danger me-2"></i> Jenis Temuan
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 220px;">
                        <canvas id="pieChartJenis"></canvas>
                    </div>
                    <div id="jenis-legend-container" class="mt-2 small"></div>
                </div>
            </div>
        </div>

        <!-- Pie Chart: Breakdown Prioritas -->
        <div class="col-lg-3 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark d-flex align-items-center">
                        <i class="fas fa-shield-cat text-warning me-2"></i> Prioritas Severity
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 220px;">
                        <canvas id="pieChartPrioritas"></canvas>
                    </div>
                    <div id="prio-legend-container" class="mt-2 small"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->section('scripts') ?>
<script>
$(document).ready(function () {
    let chartLine = null;
    let chartStatusDonut = null;
    let chartUlpBar = null;
    let chartJenisPie = null;
    let chartPrioPie = null;

    const initialLine     = <?= json_encode($lineData) ?>;
    const initialBar      = <?= json_encode($ulpBar) ?>;
    const initialStatus   = <?= json_encode($statusDonut) ?>;
    const initialJenis    = <?= json_encode($jenisPie) ?>;
    const initialPrio     = <?= json_encode($prioPie) ?>;

    // 1. Initialize Line Chart (Temuan vs Selesai)
    function initLineChart(data) {
        const ctx = document.getElementById('lineChartTemuan').getContext('2d');
        if (chartLine) chartLine.destroy();

        chartLine = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Temuan Baru',
                        data: data.temuan,
                        borderColor: '#005eb8',
                        backgroundColor: 'rgba(0, 94, 184, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    },
                    {
                        label: 'Realisasi Selesai',
                        data: data.selesai,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' Temuan';
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // 2. Initialize Donut Chart (Status)
    function initDonutChart(data) {
        const ctx = document.getElementById('donutChartStatus').getContext('2d');
        if (chartStatusDonut) chartStatusDonut.destroy();

        chartStatusDonut = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#dc3545', '#ffc107', '#28a745'],
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const val = context.raw;
                                const pct = data.percentages[idx] || 0;
                                return data.labels[idx] + ': ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Render Legend HTML
        let legendHtml = '<div class="d-flex justify-content-center gap-2 flex-wrap">';
        data.labels.forEach((lbl, idx) => {
            const colors = ['#dc3545', '#ffc107', '#28a745'];
            legendHtml += `<span class="badge" style="background-color:${colors[idx]}; font-size:11px;">${lbl}: ${data.values[idx]} (${data.percentages[idx]}%)</span>`;
        });
        legendHtml += `</div><div class="mt-2 fw-bold text-dark">TOTAL TEMUAN: ${data.total}</div>`;
        $('#donut-legend-container').html(legendHtml);
    }

    // 3. Initialize Bar Chart (Performa ULP)
    function initBarChart(data) {
        const ctx = document.getElementById('barChartUlp').getContext('2d');
        if (chartUlpBar) chartUlpBar.destroy();

        chartUlpBar = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Jumlah Temuan',
                    data: data.values,
                    backgroundColor: '#005eb8',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + ' Temuan';
                            }
                        }
                    }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // 4. Initialize Pie Chart (Jenis Temuan)
    function initJenisChart(data) {
        const ctx = document.getElementById('pieChartJenis').getContext('2d');
        if (chartJenisPie) chartJenisPie.destroy();

        chartJenisPie = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#e63946', '#f4a261', '#2a9d8f', '#457b9d', '#9b59b6', '#34495e'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const val = context.raw;
                                const pct = data.percentages[idx] || 0;
                                return data.labels[idx] + ': ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // 5. Initialize Pie Chart (Prioritas)
    function initPrioChart(data) {
        const ctx = document.getElementById('pieChartPrioritas').getContext('2d');
        if (chartPrioPie) chartPrioPie.destroy();

        chartPrioPie = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#6c757d'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const val = context.raw;
                                const pct = data.percentages[idx] || 0;
                                return data.labels[idx] + ': ' + val + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Initial render
    initLineChart(initialLine);
    initDonutChart(initialStatus);
    initBarChart(initialBar);
    initJenisChart(initialJenis);
    initPrioChart(initialPrio);

    // 6. REALTIME AJAX REFRESH ON GLOBAL FILTER CHANGE
    function fetchDashboardData() {
        const ulpId = $('#filter_ulp').val();
        const bulan = $('#filter_bulan').val();
        const tahun = $('#filter_tahun').val();

        $('#filter-status-text').html('<i class="fas fa-spinner fa-spin text-primary me-1"></i> Mengambil data...');

        $.ajax({
            url: '<?= site_url('dashboard/chart-data') ?>',
            type: 'GET',
            data: { ulp_id: ulpId, bulan: bulan, tahun: tahun },
            dataType: 'JSON',
            success: function (res) {
                if (res.success) {
                    // Update KPI Cards
                    $('#kpi-total').text(Number(res.kpi.total).toLocaleString());
                    $('#kpi-selesai').text(Number(res.kpi.selesai).toLocaleString());
                    $('#kpi-proses').text(Number(res.kpi.proses).toLocaleString());
                    $('#kpi-belum').text(Number(res.kpi.belum).toLocaleString());
                    $('#kpi-rate-text').text('Rate: ' + res.kpi.rate + '%');

                    // Refresh Charts
                    initLineChart(res.line_chart);
                    initDonutChart(res.status_donut);
                    initBarChart(res.ulp_bar);
                    initJenisChart(res.jenis_pie);
                    initPrioChart(res.prio_pie);

                    $('#filter-status-text').html('<i class="fas fa-circle-check text-success me-1"></i> Data Diperbarui (' + res.timestamp + ')');
                }
            },
            error: function () {
                $('#filter-status-text').html('<i class="fas fa-exclamation-triangle text-danger me-1"></i> Gagal memperbarui data');
            }
        });
    }

    $('#filter_ulp, #filter_bulan, #filter_tahun').change(function () {
        fetchDashboardData();
    });

    $('#btn-reset-global-filter').click(function () {
        $('#filter_ulp').val('');
        $('#filter_bulan').val('<?= date('n') ?>');
        $('#filter_tahun').val('<?= date('Y') ?>');
        fetchDashboardData();
    });
});
</script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
