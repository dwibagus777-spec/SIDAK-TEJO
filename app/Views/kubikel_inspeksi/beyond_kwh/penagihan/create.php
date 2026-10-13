<?php
?>
<?= $this->extend("layouts/admin") ?>
<?= $this->section("content") ?>
<div class="container-fluid py-4">
  <h4 class="mb-4">Penagihan - Rekomendasi #<?= esc($rekomendasi["id"]) ?></h4>
  <?php if (session()->has("success")): ?><div class="alert alert-success"><?= session("success") ?></div><?php endif; ?>
  <?php if (session()->has("error")): ?><div class="alert alert-danger"><?= session("error") ?></div><?php endif; ?>
  <form method="post" action="<?= site_url("kubikel-inspeksi/beyond-kwh/" . $rekomendasi["id"] . "/penagihan/store") ?>">
    <?= csrf_field() ?>
    <div class="card mb-4"><div class="card-body">
      <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Tanggal Penagihan</label><input type="date" name="tanggal_penagihan" class="form-control" required value="<?= date("Y-m-d") ?>"></div>
        <div class="col-md-4"><label class="form-label">SPK/PO</label><select name="spkpo_id" class="form-select" required><option value="">Pilih SPK/PO</option><?php foreach ($spkpoList as $s): ?><option value="<?= $s["id"] ?>"><?= esc($s["nomor_spkpo"] ?? "SPK-".$s["id"]) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Nomor BAPP</label><input type="text" name="nomor_bapp" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Nomor BAST</label><input type="text" name="nomor_bast" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Nominal</label><input type="number" step="0.01" name="nominal" class="form-control"></div>
        <div class="col-12"><label class="form-label">Keterangan</label><textarea name="keterangan" class="form-control" rows="3"></textarea></div>
      </div>
    </div></div>
    <div class="d-flex justify-content-between"><a class="btn btn-secondary" href="<?= site_url("kubikel-inspeksi/beyond-kwh/detail/" . $rekomendasi["id"]) ?>">Kembali</a><button class="btn btn-primary" type="submit">Simpan</button></div>
  </form>
</div>
<?= $this->endSection() ?>
