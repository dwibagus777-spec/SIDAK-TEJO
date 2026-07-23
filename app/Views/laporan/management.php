<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Laporan Management Trafo<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Cetak Laporan Management Trafo<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('laporan/temuan') ?>">Pusat Laporan</a></li>
<li class="breadcrumb-item active">Laporan Management Trafo</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-10 offset-lg-1 col-12">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-print mr-2 text-primary"></i> Laporan Management Nameplate Trafo
                </h3>
            </div>
            
            <form id="formLaporan" method="post" target="_blank">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <div class="alert alert-info">
                        <h5><i class="icon fas fa-info-circle"></i> Petunjuk</h5>
                        Pilih ULP dan penyulang. Centang data gardu yang ingin dimasukkan ke dalam laporan pada tabel pilihan di bawah.
                    </div>

                    <!-- 1. ULP Dropdown -->
                    <div class="form-group row mb-3">
                        <label for="ulp_id" class="col-sm-3 col-form-label font-weight-bold">Unit ULP</label>
                        <div class="col-sm-6">
                            <?php if ($isRestricted): ?>
                                <select id="ulp_id" class="form-control select2" disabled>
                                    <option value="<?= $ulps[0]['id'] ?>"><?= esc($ulps[0]['nama_ulp']) ?></option>
                                </select>
                                <input type="hidden" name="ulp_id" id="hidden_ulp_id" value="<?= $ulps[0]['id'] ?>">
                            <?php else: ?>
                                <select name="ulp_id" id="ulp_id" class="form-control select2" required>
                                    <option value="">-- Pilih ULP --</option>
                                    <?php foreach ($ulps as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= esc($u['nama_ulp']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Penyulang Dropdown -->
                    <div class="form-group row mb-3">
                        <label for="penyulang_id" class="col-sm-3 col-form-label font-weight-bold">Penyulang</label>
                        <div class="col-sm-6">
                            <select name="penyulang_id" id="penyulang_id" class="form-control select2" required <?= ($isRestricted ? '' : 'disabled') ?>>
                                <option value="">-- Pilih Penyulang --</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- 3. Range Tanggal -->
                    <div class="form-group row mb-4">
                        <label class="col-sm-3 col-form-label font-weight-bold">Range Tanggal</label>
                        <div class="col-sm-4">
                            <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control">
                            <small class="form-text text-muted">Awal (Boleh Kosong)</small>
                        </div>
                        <div class="col-sm-4">
                            <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control">
                            <small class="form-text text-muted">Akhir (Boleh Kosong)</small>
                        </div>
                    </div>

                    <!-- 4. List Gardu Pilihan -->
                    <div class="form-group row" id="garduSelectionRow" style="display:none;">
                        <label class="col-sm-3 col-form-label font-weight-bold">Pilih Data Gardu</label>
                        <div class="col-sm-9" id="garduListContainer">
                            <!-- Diload via AJAX -->
                        </div>
                    </div>

                </div>
                
                <div class="card-footer bg-white border-top">
                    <div class="row justify-content-center">
                        <div class="col-md-4 mb-2">
                            <button type="button" onclick="submitForm('pdf')" class="btn btn-primary btn-block font-weight-bold py-2">
                                <i class="fas fa-file-pdf mr-1"></i> Cetak PDF / Print
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="button" onclick="submitForm('excel')" class="btn btn-success btn-block font-weight-bold py-2">
                                <i class="fas fa-file-excel mr-1"></i> Excel (.xlsx)
                            </button>
                        </div>
                        <div class="col-md-4 mb-2">
                            <button type="button" onclick="submitForm('csv')" class="btn btn-info btn-block font-weight-bold py-2">
                                <i class="fas fa-file-csv mr-1"></i> CSV
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    $(function() {
        // Cascade ULP -> Penyulang
        $('#ulp_id').change(function() {
            const ulpId = $(this).val();
            const penyulangSelect = $('#penyulang_id');
            
            penyulangSelect.empty().append('<option value="">-- Pilih Penyulang --</option>').prop('disabled', true);
            $('#garduSelectionRow').hide();
            $('#garduListContainer').empty();
            
            if (ulpId) {
                $.ajax({
                    url: '<?= site_url('temuan/ajax-get-penyulang/') ?>' + ulpId,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(data) {
                        if (data.length > 0) {
                            $.each(data, function(index, item) {
                                penyulangSelect.append('<option value="' + item.id + '">' + item.nama_penyulang + '</option>');
                            });
                            penyulangSelect.prop('disabled', false);
                        }
                    }
                });
            }
        });

        // Trigger load data gardu
        $('#penyulang_id, #tanggal_awal, #tanggal_akhir').change(function() {
            fetchGardu();
        });

        // Inisialisasi awal jika restricted
        <?php if ($isRestricted): ?>
            fetchGardu();
        <?php endif; ?>
    });

    function fetchGardu() {
        const uId = $('#ulp_id').val() || $('#hidden_ulp_id').val();
        const pId = $('#penyulang_id').val();
        const tglAwal = $('#tanggal_awal').val();
        const tglAkhir = $('#tanggal_akhir').val();

        if (pId) {
            $('#garduSelectionRow').show();
            $('#garduListContainer').html('<div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat data gardu...</div>');
            
            $.ajax({
                url: '<?= site_url('laporan/ajax-management-data') ?>',
                type: 'POST',
                data: {
                    id_penyulang: pId,
                    tgl_awal: tglAwal,
                    tgl_akhir: tglAkhir
                },
                success: function(response) {
                    $('#garduListContainer').html(response);
                },
                error: function() {
                    $('#garduListContainer').html('<div class="text-danger py-2 text-center">Gagal memuat data gardu.</div>');
                }
            });
        } else {
            $('#garduSelectionRow').hide();
        }
    }

    function submitForm(type) {
        if ($('.checkItem').length > 0 && $('.checkItem:checked').length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Silakan pilih setidaknya satu data gardu yang ingin dimasukkan ke laporan.'
            });
            return;
        }

        const form = $('#formLaporan');
        
        if (type === 'pdf') {
            form.attr('action', '<?= site_url('laporan/export-management-pdf') ?>');
        } else if (type === 'excel') {
            form.attr('action', '<?= site_url('laporan/export-management-excel') ?>');
        } else if (type === 'csv') {
            form.attr('action', '<?= site_url('laporan/export-management-csv') ?>');
        }
        
        form.submit();
    }
</script>
<?= $this->endSection() ?>
