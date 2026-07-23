<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Tambah Management Trafo<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Input Management Trafo<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('eviden/management') ?>">Management Trafo</a></li>
<li class="breadcrumb-item active">Tambah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card card-modern">
            <div class="card-header card-header-modern">
                <h3 class="card-title text-white font-weight-bold">
                    <i class="fas fa-plus-circle mr-2"></i> Formulir Management Nameplate Trafo
                </h3>
            </div>
            
            <form action="<?= site_url('eviden/management/store') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <?php if (isset($validation)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle mr-2"></i> Harap perbaiki kesalahan pengisian form berikut.
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- ULP Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="ulp_id" class="font-weight-bold">Unit Layanan Pelanggan (ULP) <span class="text-danger">*</span></label>
                            <select id="ulp_id" name="ulp_id" class="form-control select2" required>
                                <option value="">-- Pilih ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= old('ulp_id') == $u['id'] ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Penyulang Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="id_penyulang" class="font-weight-bold">Penyulang <span class="text-danger">*</span></label>
                            <select id="id_penyulang" name="id_penyulang" class="form-control select2" required disabled>
                                <option value="">-- Pilih Penyulang --</option>
                            </select>
                        </div>

                        <!-- Section Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="id_section" class="font-weight-bold">Section <span class="text-danger">*</span></label>
                            <select id="id_section" name="id_section" class="form-control select2" required disabled>
                                <option value="">-- Pilih Section --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <!-- Nama Gardu -->
                        <div class="col-md-6 form-group">
                            <label for="nama_gardu" class="font-weight-bold">Nama Gardu <span class="text-danger">*</span></label>
                            <input type="text" id="nama_gardu" name="nama_gardu" class="form-control" placeholder="Contoh: PA376" value="<?= old('nama_gardu') ?>" required>
                        </div>

                        <!-- Tanggal Input -->
                        <div class="col-md-6 form-group">
                            <label for="tgl_input" class="font-weight-bold">Tanggal Input <span class="text-danger">*</span></label>
                            <input type="date" id="tgl_input" name="tgl_input" class="form-control" value="<?= old('tgl_input') ?: date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <!-- Keterangan -->
                    <div class="form-group mt-2">
                        <label for="keterangan" class="font-weight-bold">Keterangan / Detail Pekerjaan <span class="text-danger">*</span></label>
                        <textarea id="keterangan" name="keterangan" class="form-control" rows="4" placeholder="Tuliskan keterangan penggantian nameplate trafo..." required><?= old('keterangan') ?></textarea>
                    </div>

                    <!-- Foto Nameplate -->
                    <div class="row mt-4 border-top pt-3">
                        <div class="col-md-6 form-group">
                            <label for="foto_nameplate_lama" class="font-weight-bold">Foto Nameplate Lama <span class="text-danger">*</span></label>
                            <input type="file" id="foto_nameplate_lama" name="foto_nameplate_lama" class="form-control-file" accept="image/*" required>
                            <small class="text-muted">Format: JPG, PNG, WEBP</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="foto_nameplate_baru" class="font-weight-bold">Foto Nameplate Baru <span class="text-danger">*</span></label>
                            <input type="file" id="foto_nameplate_baru" name="foto_nameplate_baru" class="form-control-file" accept="image/*" required>
                            <small class="text-muted">Format: JPG, PNG, WEBP</small>
                        </div>
                    </div>

                </div>
                
                <div class="card-footer bg-white border-top text-right">
                    <a href="<?= site_url('eviden/management') ?>" class="btn btn-secondary font-weight-bold px-4 mr-2">Batal</a>
                    <button type="submit" class="btn btn-primary font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i> Simpan Data
                    </button>
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
            const penyulangSelect = $('#id_penyulang');
            const sectionSelect = $('#id_section');
            
            penyulangSelect.empty().append('<option value="">-- Pilih Penyulang --</option>').prop('disabled', true);
            sectionSelect.empty().append('<option value="">-- Pilih Section --</option>').prop('disabled', true);
            
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

        // Cascade Penyulang -> Section
        $('#id_penyulang').change(function() {
            const penyulangId = $(this).val();
            const sectionSelect = $('#id_section');
            
            sectionSelect.empty().append('<option value="">-- Pilih Section --</option>').prop('disabled', true);
            
            if (penyulangId) {
                $.ajax({
                    url: '<?= site_url('temuan/ajax-get-section/') ?>' + penyulangId,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(data) {
                        if (data.length > 0) {
                            $.each(data, function(index, item) {
                                sectionSelect.append('<option value="' + item.id + '">' + item.nama_section + '</option>');
                            });
                            sectionSelect.prop('disabled', false);
                        }
                    }
                });
            }
        });
    });
</script>
<?= $this->endSection() ?>
