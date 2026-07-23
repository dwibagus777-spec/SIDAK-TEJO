<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIDAK TEJO | Form User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
<?= view('partials/nav') ?>
<div class="container py-4">
    <h2><?= $mode === 'edit' ? 'Edit' : 'Tambah' ?> User</h2>
    <form method="post" action="<?= $mode === 'edit' ? site_url('users/update/' . ($item['id'] ?? '')) : site_url('users/store') ?>">
        <?= csrf_field() ?>
        <div class="mb-3"><label>Nama</label><input type="text" name="nama" class="form-control" value="<?= esc($item['nama'] ?? '') ?>"></div>
        <div class="mb-3"><label>Username</label><input type="text" name="username" class="form-control" value="<?= esc($item['username'] ?? '') ?>"></div>
        <div class="mb-3"><label>Password</label><input type="password" name="password" class="form-control"></div>
        <div class="mb-3"><label>Role</label><select name="role" class="form-select"><option value="administrator" <?= (($item['role'] ?? 'inspeksi') === 'administrator') ? 'selected' : '' ?>>Administrator</option><option value="admin_ulp" <?= (($item['role'] ?? 'inspeksi') === 'admin_ulp') ? 'selected' : '' ?>>Admin ULP</option><option value="inspeksi" <?= (($item['role'] ?? 'inspeksi') === 'inspeksi') ? 'selected' : '' ?>>Inspeksi</option><option value="pdkb" <?= (($item['role'] ?? 'inspeksi') === 'pdkb') ? 'selected' : '' ?>>PDKB</option><option value="har_gardu" <?= (($item['role'] ?? 'inspeksi') === 'har_gardu') ? 'selected' : '' ?>>HAR Gardu</option><option value="har_row" <?= (($item['role'] ?? 'inspeksi') === 'har_row') ? 'selected' : '' ?>>HAR ROW</option><option value="har_crane" <?= (($item['role'] ?? 'inspeksi') === 'har_crane') ? 'selected' : '' ?>>HAR Crane</option><option value="yantek" <?= (($item['role'] ?? 'inspeksi') === 'yantek') ? 'selected' : '' ?>>Yantek</option></select></div>
        <div class="mb-3"><label>ULP</label><select name="ulp_id" class="form-select"><option value="">- Pilih ULP -</option><?php foreach ($ulps as $ulp): ?><option value="<?= esc($ulp['id']) ?>" <?= (($item['ulp_id'] ?? '') == $ulp['id']) ? 'selected' : '' ?>><?= esc($ulp['nama_ulp']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label>Status</label><select name="status" class="form-select"><option value="AKTIF" <?= (($item['status'] ?? 'AKTIF') === 'AKTIF') ? 'selected' : '' ?>>AKTIF</option><option value="NONAKTIF" <?= (($item['status'] ?? 'AKTIF') === 'NONAKTIF') ? 'selected' : '' ?>>NONAKTIF</option></select></div>
        <button class="btn btn-primary" type="submit">Simpan</button>
        <a class="btn btn-secondary" href="<?= site_url('users') ?>">Kembali</a>
    </form>
</div>
</body>
</html>
