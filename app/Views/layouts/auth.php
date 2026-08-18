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
            --brand-navy: #09172A;
            --brand-blue: #0F294A;
            --brand-teal: #00B5B8;
            --brand-cyan: #38BDF8;
            --brand-accent: #F59E0B;
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
        }

        /* ── Split Screen Container ──────────────────── */
        .auth-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            overflow: hidden;
        }

        /* ── Left Column: Brand & Visual Identity ────── */
        .auth-brand-side {
            flex: 1.1;
            background: linear-gradient(135deg, var(--brand-navy) 0%, var(--brand-blue) 60%, #061120 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 56px 64px;
            position: relative;
            overflow: hidden;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Subtle Background Grid & Ambient Glow */
        .auth-brand-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(56, 189, 248, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(56, 189, 248, 0.04) 1px, transparent 1px);
            background-size: 36px 36px;
            z-index: 0;
        }

        .auth-brand-side::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 181, 184, 0.18) 0%, rgba(15, 41, 74, 0.05) 60%, transparent 80%);
            top: 40%;
            left: 40%;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
        }

        .brand-header, .brand-body, .brand-footer {
            position: relative;
            z-index: 2;
        }

        /* Top Corporate PLN Header */
        .pln-corporate-tag {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 6px 14px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        .pln-logo-mini {
            height: 18px;
            width: auto;
        }
        .pln-corporate-text {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.9);
            text-transform: uppercase;
        }

        /* Center Brand Identity & Emblem */
        .brand-body {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin: auto 0;
            padding: 32px 0;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: 3px;
            color: #ffffff;
            line-height: 1.1;
            margin-bottom: 8px;
        }
        .brand-title span {
            color: var(--brand-teal);
        }

        .brand-subtitle {
            font-size: 0.88rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 28px;
        }

        /* Controlled Enterprise Badge Logo */
        .emblem-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1.5px solid rgba(56, 189, 248, 0.25);
            border-radius: 20px;
            padding: 12px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4), 0 0 25px rgba(0, 181, 184, 0.15);
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
            max-width: 290px;
            width: 100%;
        }
        .emblem-card:hover {
            border-color: rgba(56, 189, 248, 0.45);
            transform: translateY(-2px);
            box-shadow: 0 20px 50px rgba(0, 181, 184, 0.3);
        }
        .emblem-img {
            width: 100%;
            height: auto;
            border-radius: 12px;
            display: block;
        }

        /* System Version Fingerprint Footer */
        .brand-fingerprint {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.45);
            font-family: monospace;
        }
        .version-badge {
            background: rgba(0, 181, 184, 0.15);
            color: var(--brand-teal);
            padding: 2px 8px;
            border-radius: 6px;
            font-weight: 700;
            border: 1px solid rgba(0, 181, 184, 0.3);
        }

        /* ── Right Column: Compact Form Panel ────────── */
        .auth-form-side {
            flex: 0.9;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 48px;
            position: relative;
        }

        .auth-form-card {
            width: 100%;
            max-width: 400px;
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

        /* Modern Compact Input Groups */
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

        /* Modern CTA Button */
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

        /* Alert Styling */
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

        .form-footer-copyright {
            margin-top: 36px;
            text-align: center;
            font-size: 0.74rem;
            color: var(--gray-500);
            line-height: 1.5;
        }

        /* ── Responsive Rules ────────────────────────── */
        @media (max-width: 992px) {
            .auth-container { flex-direction: column; }
            .auth-brand-side {
                padding: 32px 24px;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
            .brand-body { padding: 16px 0; align-items: center; text-align: center; }
            .emblem-card { max-width: 220px; }
            .auth-form-side { padding: 40px 24px; }
        }
        @media (max-width: 480px) {
            .brand-title { font-size: 1.85rem; }
            .form-header-heading { font-size: 1.5rem; }
            .auth-form-side { padding: 32px 18px; }
        }
    </style>
</head>
<body>

<div class="auth-container">

    <!-- ──────────── LEFT COLUMN: BRAND & VISUAL IDENTITY ──────────── -->
    <div class="auth-brand-side">
        <!-- Top Corporate Badge -->
        <div class="brand-header animate__animated animate__fadeInDown">
            <div class="pln-corporate-tag">
                <i class="fas fa-bolt text-warning" style="font-size: 12px;"></i>
                <span class="pln-corporate-text">PT PLN (Persero) UP3 Sidoarjo</span>
            </div>
        </div>

        <!-- Center Identity & Controlled Emblem -->
        <div class="brand-body animate__animated animate__fadeIn">
            <h1 class="brand-title">SIDAK <span>TEJO</span></h1>
            <div class="brand-subtitle">Sistem Data &amp; Tindak Lanjut Temuan Inspeksi Sidoarjo</div>

            <div class="emblem-card">
                <img src="<?= base_url('assets/img/logo_sidak_hd.jpg') ?>?v=<?= time() ?>" alt="SIDAK TEJO Emblem" class="emblem-img">
            </div>
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
    <div class="auth-form-side">
        <div class="auth-form-card animate__animated animate__fadeInRight">
            
            <div class="form-title-wrap">
                <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--brand-teal);">
                    <span class="status-dot-online"></span> Sistem Aktif &amp; Terbuka
                </div>
                <h2 class="form-header-heading">Selamat Datang 👋</h2>
                <p class="form-header-subtext">Masuk dengan akun SIDAK TEJO Anda untuk melanjutkan.</p>
            </div>

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

            <form action="<?= site_url('login') ?>" method="post" id="login-form" autocomplete="off">
                <?= csrf_field() ?>

                <!-- Username -->
                <div class="form-group-modern">
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
                <div class="form-group-modern">
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

    // Submit loading state
    document.getElementById('login-form').addEventListener('submit', function() {
        const btn = document.getElementById('btn-submit');
        btn.disabled = true;
        btn.style.opacity = '0.85';
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>MEMVERIFIKASI...</span>';
    });
</script>
</body>
</html>
