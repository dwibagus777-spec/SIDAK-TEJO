<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Pusat Laporan<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Pusat Laporan Temuan & Tindaklanjut<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item active">Pusat Laporan</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8 col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter text-primary mr-1"></i> Filter Ekspor & Cetak Laporan</h3>
            </div>
            <!-- Target default targets preview page -->
            <form action="<?= site_url('laporan/preview') ?>" method="post" id="form-laporan" target="_blank">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <div class="row">
                        <!-- Tanggal Awal -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="tanggal_awal">Tanggal Awal Temuan</label>
                            <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control">
                        </div>
                        <!-- Tanggal Akhir -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="tanggal_akhir">Tanggal Akhir Temuan</label>
                            <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <!-- ULP -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="ulp_id">ULP</label>
                            <select name="ulp_id" id="ulp_id" class="form-control select2">
                                <?php if (count($ulps) > 1): ?>
                                    <option value="">-- Semua ULP --</option>
                                <?php endif; ?>
                                <?php foreach ($ulps as $ulp): ?>
                                    <option value="<?= $ulp['id'] ?>"><?= esc($ulp['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Penyulang -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="penyulang_id">Penyulang</label>
                            <select name="penyulang_id" id="penyulang_id" class="form-control select2">
                                <option value="">-- Semua Penyulang --</option>
                                <?php foreach ($penyulangs as $penyulang): ?>
                                    <option value="<?= $penyulang['id'] ?>"><?= esc($penyulang['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Section -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="section_id">Section</label>
                            <select name="section_id" id="section_id" class="form-control select2">
                                <option value="">-- Semua Section --</option>
                            </select>
                        </div>

                        <!-- Pelaksana -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="pelaksana">Pelaksana Pekerjaan</label>
                            <select name="pelaksana" id="pelaksana" class="form-control select2">
                                <option value="">-- Semua Pelaksana --</option>
                                <option value="PDKB">PDKB</option>
                                <option value="HAR GARDU">HAR GARDU</option>
                                <option value="HAR KONSTRUKSI">HAR KONSTRUKSI</option>
                                <option value="HAR ROW">HAR ROW</option>
                                <option value="HAR CRANE">HAR CRANE</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Prioritas -->
                        <div class="col-md-4 form-group mb-3">
                            <label for="prioritas">Prioritas</label>
                            <select name="prioritas" id="prioritas" class="form-control select2">
                                <option value="">-- Semua Prioritas --</option>
                                <option value="EMERGENCY">EMERGENCY</option>
                                <option value="HIGH">HIGH</option>
                                <option value="MEDIUM">MEDIUM</option>
                            </select>
                        </div>

                        <!-- Jenis Temuan -->
                        <div class="col-md-4 form-group mb-3">
                            <label for="jenis_temuan">Jenis Temuan</label>
                            <select name="jenis_temuan" id="jenis_temuan" class="form-control select2">
                                <option value="">-- Semua Jenis --</option>
                                <option value="KONSTRUKSI">KONSTRUKSI</option>
                                <option value="HOTSPOT">HOTSPOT</option>
                                <option value="ROW">ROW</option>
                            </select>
                        </div>

                        <!-- Potensi Gangguan -->
                        <div class="col-md-4 form-group mb-3">
                            <label for="potensi_gangguan">Potensi Gangguan</label>
                            <select name="potensi_gangguan" id="potensi_gangguan" class="form-control select2">
                                <option value="">-- Semua Potensi --</option>
                                <option value="DGR">DGR</option>
                                <option value="OCR">OCR</option>
                                <option value="OCRDGR">OCRDGR</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Status -->
                        <div class="col-md-6 form-group mb-3">
                            <label for="status">Status Pekerjaan</label>
                            <select name="status" id="status" class="form-control select2">
                                <option value="">-- Semua Status --</option>
                                <option value="BELUM">BELUM SELESAI</option>
                                <option value="SELESAI">SUDAH SELESAI</option>
                            </select>
                        </div>
                    </div>

                </div>
                <div class="card-footer d-flex gap-2 justify-content-start">
                    <button type="submit" class="btn btn-info text-white mr-1" id="btn-preview"><i class="fas fa-eye mr-1"></i> Preview Laporan</button>
                    <button type="button" class="btn btn-success mr-1" id="btn-print"><i class="fas fa-print mr-1"></i> Cetak / PDF</button>
                    <button type="button" class="btn btn-warning text-dark mr-1" id="btn-excel"><i class="fas fa-file-excel mr-1"></i> Unduh Excel</button>
                    <button type="button" class="btn btn-secondary text-white mr-1" id="btn-csv"><i class="fas fa-file-csv mr-1"></i> Unduh CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function() {
        
        // Dynamic Cascade ULP -> Penyulang -> Section
        $('#ulp_id').change(function() {
            const ulpId = $(this).val();
            if (!ulpId) {
                $('#penyulang_id').html('<option value="">-- Semua Penyulang --</option>');
                $('#section_id').html('<option value="">-- Semua Section --</option>');
                return;
            }

            $.ajax({
                url: "<?= site_url('temuan/ajax-penyulang/') ?>" + ulpId,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    let html = '<option value="">-- Semua Penyulang --</option>';
                    data.forEach(function(item) {
                        html += `<option value="${item.id}">${item.nama_penyulang}</option>`;
                    });
                    $('#penyulang_id').html(html);
                    $('#section_id').html('<option value="">-- Semua Section --</option>');
                }
            });
        });

        $('#penyulang_id').change(function() {
            const penyulangId = $(this).val();
            if (!penyulangId) {
                $('#section_id').html('<option value="">-- Semua Section --</option>');
                return;
            }

            $.ajax({
                url: "<?= site_url('temuan/ajax-section/') ?>" + penyulangId,
                type: "GET",
                dataType: "json",
                success: function(data) {
                    let html = '<option value="">-- Semua Section --</option>';
                    data.forEach(function(item) {
                        html += `<option value="${item.id}">${item.nama_section}</option>`;
                    });
                    $('#section_id').html(html);
                }
            });
        });

        // Button Click handlers to route Form target action
        $('#btn-print').click(function() {
            const form = $('#form-laporan');
            form.attr('action', '<?= site_url('laporan/print') ?>');
            form.submit();
        });

        $('#btn-excel').click(function() {
            const form = $('#form-laporan');
            form.attr('action', '<?= site_url('laporan/excel') ?>');
            // Remove target blank temporarily for download
            form.removeAttr('target');
            form.submit();
            // Restore target blank
            form.attr('target', '_blank');
        });

        $('#btn-csv').click(function() {
            const form = $('#form-laporan');
            form.attr('action', '<?= site_url('laporan/csv') ?>');
            // Remove target blank temporarily for download
            form.removeAttr('target');
            form.submit();
            // Restore target blank
            form.attr('target', '_blank');
        });

        $('#btn-preview').click(function() {
            const form = $('#form-laporan');
            form.attr('action', '<?= site_url('laporan/preview') ?>');
            form.submit();
        });
    });
</script>
<?= $this->endSection() ?>
