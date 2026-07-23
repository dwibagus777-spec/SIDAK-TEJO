<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIDAK TEJO | Form ULP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<?= view('partials/nav') ?>
<div class="container py-4">
    <h2><?= $mode === 'edit' ? 'Edit' : 'Tambah' ?> ULP</h2>
    <form method="post" action="<?= $mode === 'edit' ? site_url('ulps/update/' . ($ulp['id'] ?? '')) : site_url('ulps/store') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label>Kode ULP</label>
            <input type="text" name="kode_ulp" class="form-control" value="<?= esc($ulp['kode_ulp'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Nama ULP</label>
            <input type="text" name="nama_ulp" class="form-control" value="<?= esc($ulp['nama_ulp'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <option value="AKTIF" <?= (($ulp['status'] ?? 'AKTIF') === 'AKTIF') ? 'selected' : '' ?>>AKTIF</option>
                <option value="NONAKTIF" <?= (($ulp['status'] ?? 'AKTIF') === 'NONAKTIF') ? 'selected' : '' ?>>NONAKTIF</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Simpan</button>
        <a class="btn btn-secondary" href="<?= site_url('ulps') ?>">Kembali</a>
    </form>
</div>
</body>
</html>
