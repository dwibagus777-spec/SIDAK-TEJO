<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIDAK TEJO | Form Section</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<?= view('partials/nav') ?>
<div class="container py-4">
    <h2><?= $mode === 'edit' ? 'Edit' : 'Tambah' ?> Section</h2>
    <form method="post" action="<?= $mode === 'edit' ? site_url('sections/update/' . ($item['id'] ?? '')) : site_url('sections/store') ?>">
        <?= csrf_field() ?>
        <div class="mb-3"><label>Nama Section</label><input type="text" name="nama_section" class="form-control" value="<?= esc($item['nama_section'] ?? '') ?>"></div>
        <div class="mb-3"><label>Penyulang</label><select name="penyulang_id" class="form-select">
            <?php foreach ($penyulang as $p): ?><option value="<?= esc($p['id']) ?>" <?= (($item['penyulang_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= esc($p['nama_penyulang']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label>Status</label><select name="status" class="form-select"><option value="AKTIF" <?= (($item['status'] ?? 'AKTIF') === 'AKTIF') ? 'selected' : '' ?>>AKTIF</option><option value="NONAKTIF" <?= (($item['status'] ?? 'AKTIF') === 'NONAKTIF') ? 'selected' : '' ?>>NONAKTIF</option></select></div>
        <button class="btn btn-primary" type="submit">Simpan</button>
        <a class="btn btn-secondary" href="<?= site_url('sections') ?>">Kembali</a>
    </form>
</div>
</body>
</html>
