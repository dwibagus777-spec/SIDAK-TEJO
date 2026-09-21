<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Tautan Tidak Valid') ?> - SIDAK TEJO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0b132b 0%, #1c2541 100%);
            min-height: 100vh;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-card {
            max-width: 480px;
            width: 100%;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            padding: 36px 28px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="error-card">
    <div class="mb-3 text-danger">
        <i class="fas fa-link-slash fa-4x"></i>
    </div>
    <h4 class="fw-bold text-dark mb-2"><?= esc($title ?? 'Tautan Tidak Ditemukan') ?></h4>
    <p class="text-secondary small mb-4">
        <?= esc($message ?? 'Tautan berbagi temuan ini sudah kedaluwarsa atau tidak valid.') ?>
    </p>
    <a href="<?= site_url('login') ?>" class="btn btn-primary btn-sm px-4 py-2 fw-bold" style="border-radius: 10px;">
        <i class="fas fa-arrow-right-to-bracket me-1"></i> Masuk ke SIDAK TEJO
    </a>
</div>

</body>
</html>
