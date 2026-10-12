<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Inspeksi Kubikel<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Inspeksi Kubikel<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-clipboard-check me-2"></i>Inspeksi Kubikel
        </h1>
        <a href="<?= site_url('kubikel-inspeksi/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Inspeksi Baru
        </a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata('success')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= esc(session()->getFlashdata('error')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="DRAFT">Draft</option>
                        <option value="IN_PROGRESS">Sedang Berlangsung</option>
                        <option value="SELESAI">Selesai</option>
                        <option value="DITUTUP">Ditutup</option>
                        <option value="DIBATALKAN">Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterTemuan" class="form-select form-select-sm">
                        <option value="">Semua Temuan</option>
                        <option value="ADA">Ada Temuan</option>
                        <option value="TIDAK_ADA">Tidak Ada Temuan</option>
                        <option value="MENUNGGU_VERIFIKASI">Menunggu Verifikasi</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari nomor, penyulang, petugas...">
                </div>
                <div class="col-md-2 text-md-end">
                    <span class="text-muted small" id="recordCount"><?= count($inspeksi) ?> data</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="inspeksiTable">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nomor Inspeksi</th>
                            <th>Tanggal</th>
                            <th>ULP / Penyulang</th>
                            <th>Petugas</th>
                            <th>Status</th>
                            <th>Temuan</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inspeksi)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                    Belum ada data inspeksi kubikel
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($inspeksi as $row): ?>
                                <tr data-status="<?= esc($row['status']) ?>" data-temuan="<?= esc($row['status_temuan']) ?>">
                                    <td><?= $no++ ?></td>
                                    <td class="fw-medium"><?= esc($row['nomor_inspeksi'] ?? '-') ?></td>
                                    <td><?= $row['tgl_inspeksi'] ? date('d M Y', strtotime($row['tgl_inspeksi'])) : '-' ?></td>
                                    <td>
                                        <div class="fw-medium"><?= esc($row['ulp'] ?? 'ULP ' . ($row['ulp_id'] ?? '-')) ?></div>
                                        <small class="text-muted"><?= esc($row['penyulang'] ?? 'Penyulang ' . ($row['penyulang_id'] ?? '-')) ?></small>
                                    </td>
                                    <td><?= esc($row['inspector_nama'] ?? 'Petugas ' . ($row['inspector_user_id'] ?? '-')) ?></td>
                                    <td>
                                        <?php
                                            $statusClass = [
                                                'DRAFT' => 'bg-secondary',
                                                'IN_PROGRESS' => 'bg-info',
                                                'SELESAI' => 'bg-success',
                                                'DITUTUP' => 'bg-primary',
                                                'DIBATALKAN' => 'bg-danger',
                                            ];
                                            $cls = $statusClass[$row['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $cls ?>"><?= esc($row['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                            $temuanClass = [
                                                'ADA' => 'bg-warning text-dark',
                                                'TIDAK_ADA' => 'bg-success',
                                                'MENUNGGU_VERIFIKASI' => 'bg-info',
                                            ];
                                            $tcls = $temuanClass[$row['status_temuan']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $tcls ?>"><?= esc($row['status_temuan']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= site_url('kubikel-inspeksi/detail/' . $row['id']) ?>" class="btn btn-outline-primary" title="Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (in_array($role, ['administrator', 'admin', 'admin_pusat']) || $row['inspector_user_id'] == session()->get('user_id') || ($row['ulp_id'] == session()->get('ulp_id') && in_array($role, ['ulp', 'har_gtt']))): ?>
                                                <?php if ($row['status'] !== 'DITUTUP'): ?>
                                                    <a href="<?= site_url('kubikel-inspeksi/edit/' . $row['id']) ?>" class="btn btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('inspeksiTable');
        const searchInput = document.getElementById('searchInput');
        const filterStatus = document.getElementById('filterStatus');
        const filterTemuan = document.getElementById('filterTemuan');
        const recordCount = document.getElementById('recordCount');

        function filterTable() {
            const search = searchInput.value.toLowerCase();
            const status = filterStatus.value;
            const temuan = filterTemuan.value;
            let visible = 0;

            table.querySelectorAll('tbody tr').forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return; // Skip empty state row

                const text = row.innerText.toLowerCase();
                const rowStatus = row.dataset.status;
                const rowTemuan = row.dataset.temuan;

                const matchSearch = search === '' || text.includes(search);
                const matchStatus = status === '' || rowStatus === status;
                const matchTemuan = temuan === '' || rowTemuan === temuan;

                if (matchSearch && matchStatus && matchTemuan) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            recordCount.textContent = visible + ' data';
        }

        searchInput.addEventListener('input', filterTable);
        filterStatus.addEventListener('change', filterTable);
        filterTemuan.addEventListener('change', filterTable);
    });
</script>
<?= $this->endSection() ?>