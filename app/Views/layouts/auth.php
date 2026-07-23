<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SIDAK TEJO - Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo">
    <title>Login | SIDAK TEJO</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon_sidak.png') ?>">
    <link rel="shortcut icon" href="<?= base_url('assets/img/favicon_sidak.png') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Local -->
    <link rel="stylesheet" href="<?= base_url('plugins/fontawesome-free/css/all.min.css') ?>">
    <!-- Bootstrap Local -->
    <link rel="stylesheet" href="<?= base_url('plugins/bootstrap/css/bootstrap.min.css') ?>">
    <!-- Animate.css -->
    <link rel="stylesheet" href="<?= base_url('plugins/animate.min.css') ?>">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --teal-dark:  #00707A;
            --teal-main:  #00B5B8;
            --teal-light: #E0F7F7;
            --accent:     #FF6B35;
            --green:      #22C55E;
            --navy:       #0A1628;
            --navy-mid:   #0D1F3C;
        }

        html, body { height: 100%; font-family: 'Inter', sans-serif; }

        /* ── Layout ─────────────────────────────────── */
        .login-wrap {
            display: flex;
            min-height: 100vh;
        }

        /* ── Left Panel ─────────────────────────────── */
        .login-hero {
            flex: 1;
            background: linear-gradient(145deg, #0A1628 0%, #00313A 45%, #004d4f 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 56px;
            position: relative;
            overflow: hidden;
        }

        /* Animated mesh grid overlay */
        .login-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(0,181,184,0.07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0,181,184,0.07) 1px, transparent 1px);
            background-size: 48px 48px;
            animation: gridMove 20s linear infinite;
            z-index: 0;
        }
        @keyframes gridMove {
            0%   { background-position: 0 0; }
            100% { background-position: 48px 48px; }
        }

        /* Glowing orbs */
        .login-hero::after {
            content: '';
            position: absolute;
            width: 550px;
            height: 550px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0,181,184,0.15) 0%, transparent 70%);
            top: -150px;
            right: -150px;
            z-index: 0;
        }

        .orb-bottom {
            position: absolute;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,107,53,0.12) 0%, transparent 70%);
            bottom: -80px;
            left: -60px;
            z-index: 0;
        }

        .hero-content { position: relative; z-index: 2; }

        /* Logo Block */
        .hero-logo-wrap {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 64px;
        }
        .hero-logo-img {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: rgba(0,181,184,0.15);
            border: 1.5px solid rgba(0,181,184,0.4);
            padding: 8px;
            box-shadow: 0 0 24px rgba(0,181,184,0.25);
        }
        .hero-logo-text { line-height: 1; }
        .hero-brand-name {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: 2.5px;
            background: linear-gradient(135deg, #ffffff 0%, #7ee8ea 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-brand-sub {
            font-size: 0.55rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            color: rgba(0,181,184,0.75);
            text-transform: uppercase;
            margin-top: 3px;
        }

        /* Headline */
        .hero-headline {
            font-family: 'Outfit', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            margin-bottom: 20px;
        }
        .hero-headline span {
            background: linear-gradient(135deg, #00B5B8 0%, #22C55E 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-desc {
            font-size: 0.92rem;
            color: rgba(255,255,255,0.55);
            line-height: 1.7;
            max-width: 380px;
            margin-bottom: 44px;
        }

        /* Feature badges */
        .feature-badges {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .badge-item {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 12px 18px;
            backdrop-filter: blur(8px);
            transition: all 0.3s;
        }
        .badge-item:hover {
            background: rgba(0,181,184,0.1);
            border-color: rgba(0,181,184,0.3);
            transform: translateX(4px);
        }
        .badge-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .badge-icon.teal  { background: rgba(0,181,184,0.2); color: #00B5B8; }
        .badge-icon.green { background: rgba(34,197,94,0.2);  color: #22C55E; }
        .badge-icon.orange{ background: rgba(255,107,53,0.2); color: #FF6B35; }
        .badge-text-title { font-size: 0.78rem; font-weight: 700; color: #ffffff; }
        .badge-text-desc  { font-size: 0.68rem; color: rgba(255,255,255,0.45); }

        /* Marquee ticker at bottom */
        .marquee-wrap {
            margin-top: 40px;
            border-top: 1px solid rgba(255,255,255,0.07);
            padding-top: 18px;
            overflow: hidden;
        }
        .marquee-label {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: rgba(0,181,184,0.7);
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .marquee-track { overflow: hidden; white-space: nowrap; }
        .marquee-inner {
            display: inline-block;
            animation: marquee 28s linear infinite;
        }
        .marquee-inner:hover { animation-play-state: paused; }
        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .marquee-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 40px;
            font-size: 0.72rem;
            color: rgba(255,255,255,0.5);
            font-weight: 500;
        }
        .marquee-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #00B5B8;
            flex-shrink: 0;
        }

        /* ── Right Panel (Form) ──────────────────────── */
        .login-form-panel {
            width: 480px;
            flex-shrink: 0;
            background: #f8fafb;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 52px;
            position: relative;
        }
        .login-form-panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #00B5B8, #22C55E, #FF6B35);
        }

        .form-header { margin-bottom: 36px; }
        .form-header-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            font-weight: 800;
            color: #0A1628;
            letter-spacing: -0.5px;
        }
        .form-header-sub {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 6px;
        }

        .form-label-modern {
            font-size: 0.78rem;
            font-weight: 700;
            color: #374151;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            margin-bottom: 7px;
            display: block;
        }
        .input-modern {
            width: 100%;
            height: 52px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 0 16px 0 48px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            color: #1e293b;
            outline: none;
            transition: all 0.25s;
        }
        .input-modern:focus {
            border-color: #00B5B8;
            box-shadow: 0 0 0 3.5px rgba(0,181,184,0.15);
        }
        .input-wrap {
            position: relative;
            margin-bottom: 22px;
        }
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 0.9rem;
            pointer-events: none;
            transition: color 0.25s;
        }
        .input-wrap:focus-within .input-icon { color: #00B5B8; }

        /* password toggle */
        .input-toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .input-toggle-pw:hover { color: #00B5B8; }

        /* Alert */
        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.84rem;
            font-weight: 600;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger-modern { background: #fef2f2; color: #dc2626; }
        .alert-success-modern { background: #f0fdf4; color: #16a34a; }

        /* Button */
        .btn-login-modern {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #00B5B8 0%, #00707A 100%);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(0,181,184,0.35);
            margin-top: 8px;
        }
        .btn-login-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0,181,184,0.5);
        }
        .btn-login-modern:active { transform: translateY(0); }

        /* Footer */
        .form-footer {
            position: absolute;
            bottom: 28px;
            left: 52px;
            right: 52px;
            text-align: center;
            font-size: 0.72rem;
            color: #94a3b8;
        }

        /* Status bar dot */
        .status-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22C55E;
            margin-right: 5px;
            animation: pulse-dot 2s ease-in-out infinite;
            vertical-align: middle;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.4); }
        }

        /* ── Responsive ─────────────────────────────── */
        @media (max-width: 900px) {
            .login-hero { display: none; }
            .login-form-panel {
                width: 100%;
                padding: 48px 32px;
            }
        }
        @media (max-width: 480px) {
            .login-form-panel { padding: 40px 24px; }
        }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- ──────────── LEFT HERO PANEL ──────────── -->
    <div class="login-hero animate__animated animate__fadeIn">
        <div class="orb-bottom"></div>

        <div class="hero-content">
            <!-- Logo -->
            <div class="hero-logo-wrap animate__animated animate__fadeInDown">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="SIDAK TEJO" class="hero-logo-img">
                <div class="hero-logo-text">
                    <div class="hero-brand-name">SIDAK TEJO</div>
                    <div class="hero-brand-sub">Sistem Data & Tindak Lanjut<br>Temuan Inspeksi Sidoarjo</div>
                </div>
            </div>

            <!-- Headline -->
            <h1 class="hero-headline animate__animated animate__fadeInUp">
                Inspeksi Lebih<br><span>Cerdas & Terstruktur</span>
            </h1>
            <p class="hero-desc animate__animated animate__fadeIn animate__delay-1s">
                Platform digital terintegrasi untuk manajemen, pemantauan, dan tindak lanjut temuan inspeksi jaringan distribusi PLN Sidoarjo secara real-time.
            </p>

            <!-- Feature Badges -->
            <div class="feature-badges animate__animated animate__fadeInUp animate__delay-1s">
                <div class="badge-item">
                    <div class="badge-icon teal"><i class="fas fa-map-marked-alt"></i></div>
                    <div>
                        <div class="badge-text-title">Peta Temuan Interaktif</div>
                        <div class="badge-text-desc">Visualisasi lokasi temuan berbasis GPS secara real-time</div>
                    </div>
                </div>
                <div class="badge-item">
                    <div class="badge-icon green"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="badge-text-title">Dashboard Analitik</div>
                        <div class="badge-text-desc">Laporan & tren temuan per ULP, penyulang, dan pelaksana</div>
                    </div>
                </div>
                <div class="badge-item">
                    <div class="badge-icon orange"><i class="fas fa-bolt"></i></div>
                    <div>
                        <div class="badge-text-title">Update Pekerjaan Cepat</div>
                        <div class="badge-text-desc">Tindak lanjut dan bukti eviden langsung dari lapangan</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Marquee ticker -->
        <div class="marquee-wrap animate__animated animate__fadeIn animate__delay-2s">
            <div class="marquee-label">&#9654; Info Sistem</div>
            <div class="marquee-track">
                <div class="marquee-inner">
                    <?php
                    $items = [
                        'Inspeksi Jaringan Distribusi Sidoarjo',
                        'ULP Sidoarjo Kota · ULP Krian · ULP Porong',
                        'Pantau Status Temuan Real-Time',
                        'Eviden Kubikel & Trafo Terdokumentasi',
                        'Laporan Eksekutif & Cetak PDF',
                        'Temuan Terdekat Berbasis GPS',
                        'Identifikasi Gangguan Terintegrasi',
                        'Sistem Inspeksi Digital PLN Sidoarjo',
                    ];
                    // Duplicate items for seamless loop
                    $all = array_merge($items, $items);
                    foreach ($all as $item):
                    ?>
                    <span class="marquee-item">
                        <span class="marquee-dot"></span><?= esc($item) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ──────────── RIGHT FORM PANEL ──────────── -->
    <div class="login-form-panel">
        <div class="form-header animate__animated animate__fadeInDown">
            <div style="font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#00B5B8; margin-bottom:8px;">
                <span class="status-dot"></span> Sistem Aktif
            </div>
            <div class="form-header-title">Selamat Datang 👋</div>
            <div class="form-header-sub">Masuk ke akun SIDAK TEJO Anda untuk melanjutkan.</div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert-modern alert-danger-modern animate__animated animate__shakeX">
            <i class="fas fa-exclamation-circle"></i> <?= esc($error) ?>
        </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
        <div class="alert-modern alert-danger-modern animate__animated animate__shakeX">
            <i class="fas fa-exclamation-circle"></i> <?= esc(session()->getFlashdata('error')) ?>
        </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
        <div class="alert-modern alert-success-modern">
            <i class="fas fa-check-circle"></i> <?= esc(session()->getFlashdata('success')) ?>
        </div>
        <?php endif; ?>

        <form action="<?= site_url('login') ?>" method="post" id="login-form">
            <?= csrf_field() ?>

            <!-- Username -->
            <label class="form-label-modern" for="username">Username</label>
            <div class="input-wrap">
                <i class="fas fa-user input-icon"></i>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="input-modern"
                    placeholder="Masukkan username Anda"
                    required
                    autofocus
                    autocomplete="username"
                    value="<?= old('username') ?>"
                >
            </div>

            <!-- Password -->
            <label class="form-label-modern" for="password">Password</label>
            <div class="input-wrap">
                <i class="fas fa-lock input-icon"></i>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="input-modern"
                    placeholder="Masukkan password Anda"
                    required
                    autocomplete="current-password"
                    style="padding-right: 48px;"
                >
                <span class="input-toggle-pw" id="toggle-pw" title="Tampilkan/sembunyikan password">
                    <i class="fas fa-eye" id="toggle-pw-icon"></i>
                </span>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-login-modern" id="btn-submit">
                <i class="fas fa-sign-in-alt"></i>
                <span id="btn-label">MASUK KE SISTEM</span>
            </button>
        </form>

        <div class="form-footer">
            &copy; <?= date('Y') ?> SIDAK TEJO &mdash; Inspektorat Sidoarjo
        </div>
    </div>

</div>

<!-- jQuery & Bootstrap JS Local -->
<script src="<?= base_url('plugins/jquery/jquery.min.js') ?>"></script>
<script src="<?= base_url('plugins/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>

<script>
    // Password toggle
    document.getElementById('toggle-pw').addEventListener('click', function() {
        const pw = document.getElementById('password');
        const icon = document.getElementById('toggle-pw-icon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            pw.type = 'password';
            icon.className = 'fas fa-eye';
        }
    });

    // Loading state on submit
    document.getElementById('login-form').addEventListener('submit', function() {
        const btn = document.getElementById('btn-submit');
        const lbl = document.getElementById('btn-label');
        btn.disabled = true;
        btn.style.opacity = '0.8';
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>MEMVERIFIKASI...</span>';
    });
</script>
</body>
</html>
