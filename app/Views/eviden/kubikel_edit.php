<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Ubah Eviden Kubikel<?= $this->endSection() ?>
<?= $this->section('page_title') ?>Edit Eviden Kubikel<?= $this->endSection() ?>

<?= $this->section('breadcrumb') ?>
<li class="breadcrumb-item"><a href="<?= site_url('eviden/kubikel') ?>">Eviden Kubikel</a></li>
<li class="breadcrumb-item active">Ubah</li>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card card-modern">
            <div class="card-header card-header-modern">
                <h3 class="card-title text-white font-weight-bold">
                    <i class="fas fa-edit mr-2"></i> Edit Formulir Eviden Pemeliharaan Kubikel
                </h3>
            </div>
            
            <form action="<?= site_url('eviden/kubikel/update/' . $kubikel['id_kubikel']) ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="card-body">
                    
                    <div class="row">
                        <!-- ULP Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="ulp_id" class="font-weight-bold">Unit Layanan Pelanggan (ULP) <span class="text-danger">*</span></label>
                            <select id="ulp_id" name="ulp_id" class="form-control select2" required>
                                <option value="">-- Pilih ULP --</option>
                                <?php foreach ($ulps as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (old('ulp_id', $currentUlpId) == $u['id']) ? 'selected' : '' ?>><?= esc($u['nama_ulp']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Penyulang Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="id_penyulang" class="font-weight-bold">Penyulang <span class="text-danger">*</span></label>
                            <select id="id_penyulang" name="id_penyulang" class="form-control select2" required>
                                <option value="">-- Pilih Penyulang --</option>
                                <?php foreach ($penyulangs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= (old('id_penyulang', $kubikel['id_penyulang']) == $p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Section Dropdown -->
                        <div class="col-md-4 form-group">
                            <label for="id_section" class="font-weight-bold">Section <span class="text-danger">*</span></label>
                            <select id="id_section" name="id_section" class="form-control select2" required>
                                <option value="">-- Pilih Section --</option>
                                <?php foreach ($sections as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= (old('id_section', $kubikel['id_section']) == $s['id']) ? 'selected' : '' ?>><?= esc($s['nama_section']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <!-- Nama Gardu -->
                        <div class="col-md-4 form-group">
                            <label for="nama_gardu" class="font-weight-bold">Nama Gardu <span class="text-danger">*</span></label>
                            <input type="text" id="nama_gardu" name="nama_gardu" class="form-control" value="<?= esc(old('nama_gardu', $kubikel['nama_gardu'])) ?>" required>
                        </div>

                        <!-- ID Pelanggan -->
                        <div class="col-md-4 form-group">
                            <label for="id_pel" class="font-weight-bold">ID Pelanggan</label>
                            <input type="text" id="id_pel" name="id_pel" class="form-control" value="<?= esc(old('id_pel', $kubikel['id_pel'])) ?>">
                        </div>

                        <!-- Tanggal Input -->
                        <div class="col-md-4 form-group">
                            <label for="tgl_input" class="font-weight-bold">Tanggal Input <span class="text-danger">*</span></label>
                            <input type="date" id="tgl_input" name="tgl_input" class="form-control" value="<?= esc(old('tgl_input', $kubikel['tgl_input'])) ?>" required>
                        </div>
                    </div>

                    <!-- Keterangan -->
                    <div class="form-group mt-2">
                        <label for="keterangan" class="font-weight-bold">Keterangan Pemeliharaan <span class="text-danger">*</span></label>
                        <textarea id="keterangan" name="keterangan" class="form-control" rows="3" required><?= esc(old('keterangan', $kubikel['keterangan'])) ?></textarea>
                    </div>

                    <!-- FOTO SAAT INI -->
                    <div class="card card-outline card-secondary mt-4 shadow-sm">
                        <div class="card-header bg-secondary py-2">
                            <h6 class="mb-0 font-weight-bold text-white"><i class="fas fa-images mr-1"></i> Galeri Foto Terupload Saat Ini</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($fotos)): ?>
                                <p class="text-muted text-center mb-0 py-3">Belum ada foto yang diunggah.</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($fotos as $f): ?>
                                        <div class="col-md-2 col-sm-4 text-center mb-3">
                                            <div class="img-thumbnail position-relative bg-dark p-1" style="height: 100px; display: flex; align-items: center; justify-content: center; border-color: #444;">
                                                <img src="<?= base_url('foto/' . $f['nama_file']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                            </div>
                                            <span class="d-block small text-truncate mt-1 text-info font-weight-bold"><?= esc($f['jenis_foto']) ?></span>
                                            <a href="<?= site_url('eviden/delete-foto/' . $f['id_foto']) ?>" class="btn btn-xs btn-danger mt-1" onclick="return confirm('Hapus foto ini dari server secara permanen?')">
                                                <i class="fas fa-trash-alt mr-1"></i> Hapus
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- UPLOAD FOTO TAMBAHAN -->
                    <h5 class="mt-4 border-top pt-3 font-weight-bold text-info"><i class="fas fa-cloud-upload-alt mr-1"></i> Upload Foto Eviden Tambahan</h5>
                    
                    <div class="row mt-2">
                        <!-- 1. Foto Kubikel -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto Kubikel</label>
                            <input type="file" name="foto_kubikel[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                        
                        <!-- 2. Foto Merek -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto Merek Kubikel</label>
                            <input type="file" name="foto_merek[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <!-- 3. Foto CT -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto CT</label>
                            <input type="file" name="foto_ct[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                        
                        <!-- 4. Foto VT -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto VT</label>
                            <input type="file" name="foto_vt[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <!-- 5. Nameplate -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto Nameplate</label>
                            <input type="file" name="foto_nameplate[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                        
                        <!-- 6. Foto Relay -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto Merk Relay</label>
                            <input type="file" name="foto_relay[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <!-- 7. Foto Temuan -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto Temuan</label>
                            <input type="file" name="foto_temuan[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                        
                        <!-- 8. Foto Perbaikan -->
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Tambah Foto Perbaikan</label>
                            <input type="file" name="foto_perbaikan[]" class="form-control-file border p-1 rounded bg-white" accept="image/*" multiple>
                        </div>
                    </div>

                </div>
                
                <div class="card-footer bg-white border-top text-right">
                    <a href="<?= site_url('eviden/kubikel') ?>" class="btn btn-secondary font-weight-bold px-4 mr-2">Batal</a>
                    <button type="submit" class="btn btn-primary font-weight-bold px-4">
                        <i class="fas fa-save mr-1"></i> Perbarui Eviden
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
            
            penyulangSelect.empty().append('<option value="">-- Pilih Penyulang --</option>');
            sectionSelect.empty().append('<option value="">-- Pilih Section --</option>').prop('disabled', true);
            
            if (ulpId) {
                $.ajax({
                    url: '<?= site_url('temuan/ajax-get-penyulang/') ?>' + ulpId,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(data) {
                        $.each(data, function(index, item) {
                            penyulangSelect.append('<option value="' + item.id + '">' + item.nama_penyulang + '</option>');
                        });
                        penyulangSelect.prop('disabled', false);
                    }
                });
            }
        });

        // Cascade Penyulang -> Section
        $('#id_penyulang').change(function() {
            const penyulangId = $(this).val();
            const sectionSelect = $('#id_section');
            
            sectionSelect.empty().append('<option value="">-- Pilih Section --</option>');
            
            if (penyulangId) {
                $.ajax({
                    url: '<?= site_url('temuan/ajax-get-section/') ?>' + penyulangId,
                    type: 'GET',
                    dataType: 'JSON',
                    success: function(data) {
                        $.each(data, function(index, item) {
                            sectionSelect.append('<option value="' + item.id + '">' + item.nama_section + '</option>');
                        });
                        sectionSelect.prop('disabled', false);
                    }
                });
            }
        });
    });
</script>
<?= $this->endSection() ?>
