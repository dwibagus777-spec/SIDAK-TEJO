<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIDAK TEJO | Form Penyulang</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<?= view('partials/nav') ?>
<div class="container py-4">
    <h2><?= $mode === 'edit' ? 'Edit' : 'Tambah' ?> Penyulang</h2>
    <form method="post" action="<?= $mode === 'edit' ? site_url('penyulang/update/' . ($item['id'] ?? '')) : site_url('penyulang/store') ?>">
        <?= csrf_field() ?>
        <div class="mb-3"><label>ID Unik Penyulang</label><input type="text" name="id_unik_penyulang" class="form-control" value="<?= esc($item['id_unik_penyulang'] ?? '') ?>"></div>
        <div class="mb-3"><label>Kode Penyulang</label><input type="text" name="kode_penyulang" class="form-control" value="<?= esc($item['kode_penyulang'] ?? '') ?>"></div>
        <div class="mb-3"><label>Nama Penyulang</label><input type="text" name="nama_penyulang" class="form-control" value="<?= esc($item['nama_penyulang'] ?? '') ?>"></div>
        <div class="mb-3"><label>ULP</label><select name="ulp_id" class="form-select">
            <?php foreach ($ulps as $ulp): ?><option value="<?= esc($ulp['id']) ?>" <?= (($item['ulp_id'] ?? '') == $ulp['id']) ? 'selected' : '' ?>><?= esc($ulp['nama_ulp']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label>Status</label><select name="status" class="form-select"><option value="AKTIF" <?= (($item['status'] ?? 'AKTIF') === 'AKTIF') ? 'selected' : '' ?>>AKTIF</option><option value="NONAKTIF" <?= (($item['status'] ?? 'AKTIF') === 'NONAKTIF') ? 'selected' : '' ?>>NONAKTIF</option></select></div>
        <button class="btn btn-primary" type="submit">Simpan</button>
        <a class="btn btn-secondary" href="<?= site_url('penyulang') ?>">Kembali</a>
    </form>
</div>
</body>
</html>
