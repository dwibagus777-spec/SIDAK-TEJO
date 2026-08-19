<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SIDAK TEJO - Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo">
    <title>Login | SIDAK TEJO Enterprise</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon_sidak.png') ?>">
    <link rel="shortcut icon" href="<?= base_url('assets/img/favicon_sidak.png') ?>">

    <!-- Local Fonts: Outfit & Inter -->
    <link rel="stylesheet" href="<?= base_url('assets/fonts/fonts.css') ?>">
    <!-- Font Awesome Local -->
    <link rel="stylesheet" href="<?= base_url('plugins/fontawesome-free/css/all.min.css') ?>">
    <!-- Bootstrap Local -->
    <link rel="stylesheet" href="<?= base_url('plugins/bootstrap/css/bootstrap.min.css') ?>">
    <!-- Animate.css -->
    <link rel="stylesheet" href="<?= base_url('plugins/animate.min.css') ?>">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand-navy: #040E1A;
            --brand-blue: #0A223B;
            --brand-teal: #00B5B8;
            --brand-cyan: #38BDF8;
            --brand-gold: #F59E0B;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-500: #64748B;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--brand-navy);
            color: var(--gray-900);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ── 3D Viewport Wrapper for Book Opening Animation ── */
        .auth-viewport-wrapper {
            position: relative;
            width: 100%;
            min-height: 100vh;
            overflow: hidden;
            perspective: 1600px;
            background: #040E1A;
        }

        /* Deep Seamless Backdrop behind Opening Book Covers */
        .book-spine-backdrop {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, #0F2D4A 0%, #081B30 65%, #040E1A 100%);
            z-index: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.4s ease;
            pointer-events: none;
        }

        .book-opening-active .book-spine-backdrop {
            opacity: 1;
        }

        .spine-loading-content {
            text-align: center;
            color: #ffffff;
        }
        .spine-loading-logo {
            font-family: 'Outfit', sans-serif;
            font-size: 2.2rem;
            font-weight: 900;
            letter-spacing: 3px;
            color: var(--brand-teal);
            margin-bottom: 8px;
        }
        .spine-loading-text {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Split Screen Container ──────────────────── */
        .auth-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
            z-index: 2;
            transform-style: preserve-3d;
            transition: transform 0.85s cubic-bezier(0.645, 0.045, 0.355, 1.000);
        }

        /* ── Left Column: Brand & High-Vis Visual Identity ────── */
        .auth-brand-side {
            flex: 1.25;
            background: radial-gradient(circle at 40% 40%, #0F2D4A 0%, #081B30 65%, #040E1A 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 48px 56px;
            position: relative;
            overflow: hidden;
            border-right: 1px solid rgba(56, 189, 248, 0.15);
            text-align: center;
            transform-origin: left center;
            transition: transform 0.85s cubic-bezier(0.645, 0.045, 0.355, 1.000), opacity 0.85s ease;
            backface-visibility: hidden;
        }

        /* Subtle Background Grid Overlay */
        .auth-brand-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(56, 189, 248, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(56, 189, 248, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: 0;
        }

        /* Ambient Glow Effect */
        .auth-brand-side::after {
            content: '';
            position: absolute;
            width: 650px;
            height: 650px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 181, 184, 0.25) 0%, rgba(245, 158, 11, 0.08) 45%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
        }

        .brand-header, .brand-body, .brand-footer {
            position: relative;
            z-index: 2;
            width: 100%;
        }

        /* Top Corporate Badge */
        .pln-corporate-tag {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 24px;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .pln-corporate-text {
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 1.2px;
            color: #ffffff;
            text-transform: uppercase;
        }

        /* High-Visibility Hero Emblem Center */
        .brand-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: auto 0;
            padding: 24px 0;
        }

        .hero-emblem-wrapper {
            position: relative;
            margin-bottom: 24px;
            display: inline-block;
        }

        .emblem-img-prominent {
            max-width: 440px;
            width: 100%;
            height: auto;
            border-radius: 24px;
            border: 2px solid rgba(0, 225, 230, 0.5);
            box-shadow:
                0 20px 60px rgba(0, 181, 184, 0.55),
                0 0 35px rgba(245, 158, 11, 0.3),
                0 0 15px rgba(255, 255, 255, 0.2);
            filter: contrast(1.05) brightness(1.02);
            transition: all 0.35s ease;
        }
        .emblem-img-prominent:hover {
            transform: scale(1.025) translateY(-3px);
            box-shadow:
                0 25px 70px rgba(0, 225, 230, 0.7),
                0 0 45px rgba(245, 158, 11, 0.4);
        }

        .brand-title-prominent {
            font-family: 'Outfit', sans-serif;
            font-size: 2.6rem;
            font-weight: 900;
            letter-spacing: 3px;
            color: #ffffff;
            line-height: 1.1;
            margin-bottom: 6px;
            text-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        .brand-title-prominent span {
            color: var(--brand-teal);
            background: linear-gradient(135deg, #00B5B8 0%, #38BDF8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-subtitle-prominent {
            font-size: 0.88rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.85);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            max-width: 420px;
            line-height: 1.4;
        }

        /* System Version Fingerprint Footer */
        .brand-fingerprint {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 0.74rem;
            color: rgba(255, 255, 255, 0.5);
            font-family: monospace;
        }
        .version-badge {
            background: rgba(0, 181, 184, 0.2);
            color: var(--brand-teal);
            padding: 3px 10px;
            border-radius: 8px;
            font-weight: 700;
            border: 1px solid rgba(0, 181, 184, 0.4);
        }

        /* ── Right Column: Compact Login Form ────────── */
        .auth-form-side {
            flex: 0.95;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 48px;
            position: relative;
            transform-origin: right center;
            transition: transform 0.85s cubic-bezier(0.645, 0.045, 0.355, 1.000), opacity 0.85s ease;
            backface-visibility: hidden;
        }

        .auth-form-card {
            width: 100%;
            max-width: 400px;
            transition: opacity 0.3s ease;
        }

        .form-title-wrap {
            margin-bottom: 32px;
        }
        .status-dot-online {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22C55E;
            margin-right: 6px;
            animation: pulseDot 2s ease-in-out infinite;
        }
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.3); }
        }

        .form-header-heading {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.5px;
            margin-top: 6px;
        }
        .form-header-subtext {
            font-size: 0.88rem;
            color: var(--gray-500);
            margin-top: 4px;
        }

        /* Input Groups */
        .form-group-modern {
            margin-bottom: 22px;
        }
        .form-label-modern {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--gray-800);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 6px;
            display: block;
        }
        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon-left {
            position: absolute;
            left: 16px;
            color: #94A3B8;
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }
        .input-field-custom {
            width: 100%;
            height: 48px;
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            padding: 0 16px 0 46px;
            font-size: 0.92rem;
            color: var(--gray-900);
            background: #ffffff;
            outline: none;
            transition: all 0.2s ease;
        }
        .input-field-custom:focus {
            border-color: var(--brand-teal);
            box-shadow: 0 0 0 3.5px rgba(0, 181, 184, 0.15);
        }
        .input-group-custom:focus-within .input-icon-left {
            color: var(--brand-teal);
        }

        .pw-toggle-btn {
            position: absolute;
            right: 14px;
            color: #94A3B8;
            cursor: pointer;
            padding: 6px;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        .pw-toggle-btn:hover { color: var(--brand-teal); }

        /* CTA Button */
        .btn-submit-modern {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand-teal) 0%, #00878A 100%);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 6px 20px rgba(0, 181, 184, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-submit-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(0, 181, 184, 0.45);
        }
        .btn-submit-modern:active { transform: translateY(0); }

        /* Alerts */
        .alert-enterprise {
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.84rem;
            font-weight: 600;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
        }
        .alert-enterprise-danger { background: #FEF2F2; color: #DC2626; border-left: 4px solid #EF4444; }
        .alert-enterprise-success { background: #F0FDF4; color: #16A34A; border-left: 4px solid #22C55E; }
        .alert-enterprise-info { background: #EFF6FF; color: #2563EB; border-left: 4px solid #3B82F6; }

        .form-footer-copyright {
            margin-top: 36px;
            text-align: center;
            font-size: 0.74rem;
            color: var(--gray-500);
            line-height: 1.5;
        }

        /* ── 3D BOOK OPENING ANIMATION ACTIVE STATES ──── */
        .book-opening-active .auth-brand-side {
            transform: rotateY(-90deg);
            opacity: 0;
        }

        .book-opening-active .auth-form-side {
            transform: rotateY(90deg);
            opacity: 0;
        }

        /* ── Responsive Rules ────────────────────────── */
        @media (max-width: 992px) {
            .auth-container { flex-direction: column; }
            .auth-brand-side {
                padding: 32px 24px;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                transform-origin: top center;
            }
            .auth-form-side {
                transform-origin: bottom center;
            }
            .book-opening-active .auth-brand-side {
                transform: translateY(-100%) scale(0.9);
                opacity: 0;
            }
            .book-opening-active .auth-form-side {
                transform: translateY(100%) scale(0.9);
                opacity: 0;
            }
            .emblem-img-prominent { max-width: 320px; }
            .auth-form-side { padding: 40px 24px; }
        }
        @media (max-width: 480px) {
            .brand-title-prominent { font-size: 1.95rem; }
            .emblem-img-prominent { max-width: 260px; }
            .form-header-heading { font-size: 1.5rem; }
            .auth-form-side { padding: 32px 18px; }
        }

        /* ── CINEMATIC DOOR REVEAL (PURE CSS & GPU ACCELERATED) ── */
        .cinematic-reveal {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: flex;
            pointer-events: none;
        }
        .cinematic-panel {
            width: 50vw;
            height: 100vh;
            background: radial-gradient(circle at 50% 50%, #0F2D4A 0%, #081B30 65%, #040E1A 100%);
            transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: transform;
        }
        .cinematic-panel-left {
            transform: translateX(0);
            border-right: 1px solid rgba(0, 181, 184, 0.2);
        }
        .cinematic-panel-right {
            transform: translateX(0);
            border-left: 1px solid rgba(0, 181, 184, 0.2);
        }

        /* Door Open State */
        body.cinematic-door-open .cinematic-panel-left {
            transform: translateX(-100%);
        }
        body.cinematic-door-open .cinematic-panel-right {
            transform: translateX(100%);
        }

        /* Login Content Reveal */
        .auth-brand-side {
            opacity: 0;
            transform: translateY(-12px) scale(0.985);
            transition: transform 0.65s cubic-bezier(0.16, 1, 0.3, 1) 0.15s, opacity 0.65s ease 0.15s;
        }
        .auth-form-card {
            opacity: 0;
            transform: translateY(16px) scale(0.985);
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.25s, opacity 0.6s ease 0.25s;
        }

        body.cinematic-door-open .auth-brand-side {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        body.cinematic-door-open .auth-form-card {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        @media (prefers-reduced-motion: reduce) {
            .cinematic-panel, .auth-brand-side, .auth-form-card {
                transition: none !important;
                transform: none !important;
                opacity: 1 !important;
            }
            .cinematic-reveal { display: none !important; }
        }
    </style>
</head>
<body>

<div class="auth-viewport-wrapper" id="auth-viewport">
    <!-- Cinematic Door Reveal Overlay (Symmetrical Door Opening) -->
    <div class="cinematic-reveal" id="cinematic-reveal-overlay">
        <div class="cinematic-panel cinematic-panel-left"></div>
        <div class="cinematic-panel cinematic-panel-right"></div>
    </div>

    <!-- Seamless Deep Backdrop Revealed During Book Opening -->
    <div class="book-spine-backdrop" id="spine-backdrop">
        <div class="spine-loading-content animate__animated animate__pulse animate__infinite">
            <div class="spine-loading-logo"><i class="fas fa-bolt text-warning me-2"></i>SIDAK TEJO</div>
            <div class="spine-loading-text"><i class="fas fa-spinner fa-spin me-2"></i>Membuka Dashboard Inspeksi...</div>
        </div>
    </div>

    <!-- ── 3D Split Screen Book Container ── -->
    <div class="auth-container" id="book-container">

        <!-- ──────────── LEFT COLUMN: HIGH-VISIBILITY BRAND IDENTITY ──────────── -->
        <div class="auth-brand-side" id="left-cover">
            <!-- Top Corporate Badge -->
            <div class="brand-header animate__animated animate__fadeInDown">
                <div class="pln-corporate-tag">
                    <i class="fas fa-bolt text-warning" style="font-size: 13px;"></i>
                    <span class="pln-corporate-text">PT PLN (Persero) UP3 Sidoarjo</span>
                </div>
            </div>

            <!-- Prominent Center Logo Emblem & Titles -->
            <div class="brand-body animate__animated animate__zoomIn">
                <div class="hero-emblem-wrapper">
                    <img src="<?= base_url('assets/img/logo_sidak_hd.jpg') ?>?v=<?= time() ?>" alt="SIDAK TEJO Enterprise Emblem" class="emblem-img-prominent">
                </div>
                <h1 class="brand-title-prominent">SIDAK <span>TEJO</span></h1>
                <div class="brand-subtitle-prominent">Sistem Data &amp; Tindak Lanjut Temuan Inspeksi Sidoarjo</div>
            </div>

            <!-- System Version Fingerprint Footer -->
            <div class="brand-fingerprint animate__animated animate__fadeInUp">
                <span class="version-badge"><?= esc(\Config\BuildVersion::SYSTEM_VERSION) ?></span>
                <span>Build: <?= esc(\Config\BuildVersion::BUILD_ID) ?></span>
                <span>•</span>
                <span>Commit: <?= esc(\Config\BuildVersion::COMMIT_ID) ?></span>
            </div>
        </div>

        <!-- ──────────── RIGHT COLUMN: COMPACT LOGIN FORM ──────────── -->
        <div class="auth-form-side" id="right-cover">
            <div class="auth-form-card animate__animated animate__fadeInRight" id="form-card">
                
                <div class="form-title-wrap">
                    <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--brand-teal);">
                        <span class="status-dot-online"></span> Sistem Aktif &amp; Terbuka
                    </div>
                    <h2 class="form-header-heading">Selamat Datang 👋</h2>
                    <p class="form-header-subtext">Masuk dengan akun SIDAK TEJO Anda untuk melanjutkan.</p>
                </div>

                <!-- Ajax & Server Message Container -->
                <div id="ajax-alert" style="display: none;"></div>

                <?php if (isset($error)): ?>
                <div class="alert-enterprise alert-enterprise-danger animate__animated animate__shakeX">
                    <i class="fas fa-exclamation-circle"></i> <?= esc($error) ?>
                </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                <div class="alert-enterprise alert-enterprise-danger animate__animated animate__shakeX">
                    <i class="fas fa-exclamation-circle"></i> <?= esc(session()->getFlashdata('error')) ?>
                </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('success')): ?>
                <div class="alert-enterprise alert-enterprise-success">
                    <i class="fas fa-check-circle"></i> <?= esc(session()->getFlashdata('success')) ?>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['session_expired']) && $_GET['session_expired'] === 'build_update'): ?>
                <div class="alert-enterprise alert-enterprise-info">
                    <i class="fas fa-info-circle"></i> Sesi Anda diperbarui untuk rilis versi sistem terbaru. Silakan login kembali.
                </div>
                <?php endif; ?>

                <form action="<?= site_url('login') ?>" method="post" id="login-form" autocomplete="off">
                    <?= csrf_field() ?>

                    <!-- Username -->
                    <div class="form-group-modern form-stagger-1">
                        <label class="form-label-modern" for="username">Username</label>
                        <div class="input-group-custom">
                            <i class="fas fa-user input-icon-left"></i>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="input-field-custom"
                                placeholder="Masukkan username Anda"
                                required
                                autofocus
                                autocomplete="username"
                                value="<?= old('username') ?>"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group-modern form-stagger-2">
                        <label class="form-label-modern" for="password">Password</label>
                        <div class="input-group-custom">
                            <i class="fas fa-lock input-icon-left"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="input-field-custom"
                                placeholder="Masukkan password Anda"
                                required
                                autocomplete="current-password"
                                style="padding-right: 44px;"
                            >
                            <span class="pw-toggle-btn" id="toggle-pw" title="Tampilkan/sembunyikan password">
                                <i class="fas fa-eye" id="toggle-pw-icon"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Remember Me Token -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember_me" value="1" id="remember_me" checked style="cursor: pointer;">
                            <label class="form-check-label text-dark font-weight-bold small ms-1" for="remember_me" style="cursor: pointer; font-size: 13px;">
                                Ingat Saya (Aktif 30 Hari)
                            </label>
                        </div>
                    </div>

                    <!-- Submit CTA Button -->
                    <button type="submit" class="btn-submit-modern" id="btn-submit">
                        <i class="fas fa-sign-in-alt"></i>
                        <span id="btn-label">MASUK KE SISTEM</span>
                    </button>
                </form>

                <div class="form-footer-copyright">
                    &copy; <?= date('Y') ?> SIDAK TEJO &bull; PT PLN (Persero) UP3 Sidoarjo<br>
                    <span style="font-size: 0.68rem; color: #94A3B8;">Build <?= esc(\Config\BuildVersion::BUILD_ID) ?> &bull; Enterprise Network Inspection</span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="<?= base_url('plugins/jquery/jquery.min.js') ?>"></script>
<script src="<?= base_url('plugins/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>

<script>
    // Password visibility toggle
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

    // Handle Login AJAX & 3D Book Opening Animation Pipeline
    document.getElementById('login-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const btn = document.getElementById('btn-submit');
        const btnLabel = document.getElementById('btn-label');
        const alertBox = document.getElementById('ajax-alert');
        const formCard = document.getElementById('form-card');
        
        // 1. Loading Spinner State
        btn.disabled = true;
        btn.style.opacity = '0.85';
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>MEMVERIFIKASI...</span>';
        alertBox.style.display = 'none';

        const formData = new FormData(form);

        // 2. Perform AJAX Credential Authentication
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            return response.json().then(data => ({ status: response.status, body: data }));
        })
        .then(res => {
            if (res.status === 200 && res.body.success) {
                // AUTHENTICATION SUCCESS!
                btn.innerHTML = '<i class="fas fa-check-circle"></i> <span>OTENTIKASI BERHASIL...</span>';
                formCard.style.opacity = '0.7';

                const redirectUrl = res.body.redirectUrl || '<?= site_url('dashboard') ?>';
                const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (prefersReducedMotion) {
                    window.location.href = redirectUrl;
                    return;
                }

                // 3. Trigger 3D Book Opening Transition
                setTimeout(function() {
                    document.body.classList.add('book-opening-active');
                }, 150);

                // 4. Smooth Redirect After 3D Book Animation Completes (850ms)
                setTimeout(function() {
                    window.location.href = redirectUrl;
                }, 850);

            } else {
                // AUTHENTICATION FAILED: NO BOOK ANIMATION!
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> <span>MASUK KE SISTEM</span>';
                
                const errMsg = (res.body && res.body.message) ? res.body.message : 'Username atau password salah.';
                alertBox.className = 'alert-enterprise alert-enterprise-danger animate__animated animate__shakeX';
                alertBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + errMsg;
                alertBox.style.display = 'flex';
            }
        })
        .catch(err => {
            console.warn('[AUTH_AJAX_FALLBACK] Retrying standard submit:', err);
            form.submit();
        });
    });

    // Trigger Cinematic Door Open Reveal on page load
    document.addEventListener('DOMContentLoaded', function() {
        requestAnimationFrame(function() {
            setTimeout(function() {
                document.body.classList.add('cinematic-door-open');
            }, 50);
        });
    });
</script>
</body>
</html>
