<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sidak Tejo - Mobile Dashboard</title>
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9;
            color: #1e293b;
            padding-bottom: 30px;
            position: relative;
        }
        
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?= base_url("assets/img/logo_sidak.png") ?>');
            background-repeat: no-repeat;
            background-position: center 40%;
            background-size: 280px;
            opacity: 0.035; /* Ultra faint watermark for mobile layout */
            z-index: -1;
            pointer-events: none;
        }
        
        /* Mobile Header Banner */
        .mobile-header {
            background: linear-gradient(135deg, #004d4f 0%, #007275 100%);
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
            padding: 30px 20px 40px 20px;
            color: #ffffff;
            position: relative;
            box-shadow: 0 4px 15px rgba(0, 77, 79, 0.3);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .brand-logo img {
            height: 32px;
        }
        
        .brand-text {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.2;
        }
        
        .btn-logout-mobile {
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            color: #ffffff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: background-color 0.2s;
            text-decoration: none;
        }
        
        .btn-logout-mobile:active {
            background-color: rgba(255, 255, 255, 0.35);
        }
        
        .welcome-text {
            font-size: 0.85rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .user-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.6rem;
            margin: 0;
            letter-spacing: 0.3px;
        }
        
        /* Grid Menu Container */
        .menu-container {
            margin-top: -20px;
            padding: 0 16px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        /* Menu Card Item */
        .menu-card {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 18px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: transform 0.1s ease, box-shadow 0.1s ease;
        }
        
        .menu-card:active {
            transform: scale(0.96);
            box-shadow: 0 2px 3px -1px rgba(0, 0, 0, 0.05);
        }
        
        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        
        .card-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
            line-height: 1.3;
        }
        
        /* Version Toggle Footer */
        .footer-toggle {
            text-align: center;
            margin-top: 30px;
            font-size: 0.8rem;
        }
        
        .btn-toggle-version {
            color: #005eb8;
            text-decoration: underline;
            background: none;
            border: none;
            font-weight: 600;
        }

        @keyframes tickerAnimation {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-100%, 0, 0); }
        }
    </style>
</head>
<body>

    <!-- Header Banner -->
    <div class="mobile-header">
        <div class="header-top">
            <a href="<?= site_url('dashboard') ?>" class="brand-logo text-decoration-none text-white" style="cursor: pointer;" title="Ke Dashboard">
                <img src="<?= base_url('assets/img/logo_sidak.png') ?>" alt="Logo">
                <div class="brand-text">
                    SIDAK TEJO<br>
                    <span style="font-size: 0.7rem; font-weight: 500; opacity: 0.9;">PLN UP3 SIDOARJO</span>
                </div>
            </a>
            <a href="<?= site_url('auth/logout') ?>" class="btn-logout-mobile" title="Keluar">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        <div>
            <p class="welcome-text">Selamat Datang,</p>
            <h2 class="user-name"><?= esc($userName) ?></h2>
        </div>
        
        <!-- Running Motivational Ticker Mobile -->
        <div class="mt-3 p-2 rounded-3 d-flex align-items-center" style="background: rgba(255,255,255,0.18); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.3); border-radius: 10px; overflow: hidden;">
            <i class="fas fa-bullhorn text-warning me-2" style="font-size: 13px; flex-shrink: 0;"></i>
            <div style="overflow: hidden; flex-grow: 1; display: flex; align-items: center;">
                <marquee scrollamount="4" behavior="scroll" direction="left" style="font-size: 11px; font-weight: 700; color: #ffffff; margin: 0; line-height: 1.2;">
                    <?= esc(get_daily_announcement()) ?>
                </marquee>
            </div>
        </div>
    </div>

    <!-- Menu Grid -->
    <div class="menu-container">
        <div class="menu-grid">
            
            <?php 
            $userRole = session()->get('user_role');
            $canInput = in_array($userRole, ['administrator', 'admin_ulp', 'inspeksi']);
            ?>

            <!-- 1. Input Temuan -->
            <?php if ($canInput): ?>
            <a href="<?= site_url('temuan/create') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fef3c7;">
                    <i class="fas fa-plus-circle" style="color: #d97706; font-size: 20px;"></i>
                </div>
                <div class="card-label">Input Temuan</div>
            </a>
            <?php endif; ?>
            
            <!-- 2. Data Temuan -->
            <a href="<?= site_url('temuan') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #dbeafe;">
                    <i class="fas fa-list-check" style="color: #2563eb; font-size: 20px;"></i>
                </div>
                <div class="card-label">Data Temuan</div>
            </a>
            
            <!-- Update Pekerjaan -->
            <a href="<?= site_url('temuan/update-pekerjaan') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fff7ed;">
                    <i class="fas fa-edit" style="color: #ea580c; font-size: 20px;"></i>
                </div>
                <div class="card-label">Update Pekerjaan</div>
            </a>
            
            <!-- 3. Temuan Terdekat -->
            <a href="<?= site_url('temuan/terdekat') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #e0f2fe;">
                    <i class="fas fa-map-location-dot" style="color: #0284c7; font-size: 20px;"></i>
                </div>
                <div class="card-label">Temuan Terdekat</div>
            </a>
            
            <!-- 4. Eviden Kubikel -->
            <a href="<?= site_url('eviden/kubikel') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #ffedd5;">
                    <i class="fas fa-cubes" style="color: #ea580c; font-size: 20px;"></i>
                </div>
                <div class="card-label">Eviden Kubikel</div>
            </a>
            
            <!-- 5. Eviden Trafo -->
            <a href="<?= site_url('eviden/trafo') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #f3e8ff;">
                    <i class="fas fa-bolt" style="color: #9333ea; font-size: 20px;"></i>
                </div>
                <div class="card-label">Eviden Trafo</div>
            </a>
            
            <!-- 6. Management Trafo -->
            <a href="<?= site_url('eviden/management') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #dcfce7;">
                    <i class="fas fa-folder-tree" style="color: #16a34a; font-size: 20px;"></i>
                </div>
                <div class="card-label">Management Trafo</div>
            </a>
            
            <!-- 7. Lap. Temuan -->
            <a href="<?= site_url('laporan/temuan') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #f1f5f9;">
                    <i class="fas fa-print" style="color: #475569; font-size: 20px;"></i>
                </div>
                <div class="card-label">Lap. Temuan</div>
            </a>
            
            <!-- 8. Lap. Eviden -->
            <a href="<?= site_url('laporan/eviden') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fee2e2;">
                    <i class="fas fa-images" style="color: #dc2626; font-size: 20px;"></i>
                </div>
                <div class="card-label">Lap. Eviden</div>
            </a>
            
            <!-- 9. Lap. Management Trafo -->
            <a href="<?= site_url('laporan/management') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #ecfeff;">
                    <i class="fas fa-file-invoice" style="color: #0891b2; font-size: 20px;"></i>
                </div>
                <div class="card-label">Lap. Management</div>
            </a>
            
            <!-- 10. Identifikasi Gangguan -->
            <a href="<?= site_url('identifikasi') ?>" class="menu-card">
                <div class="icon-circle" style="background-color: #fdf2f8;">
                    <i class="fas fa-bolt-lightning" style="color: #db2777; font-size: 20px;"></i>
                </div>
                <div class="card-label">Identifikasi Gangguan</div>
            </a>

        </div>
    </div>

    <!-- Top 10 Leaderboard Section Mobile -->
    <div class="px-3 mt-4 mb-2">
        <h6 class="text-secondary font-weight-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px; font-family: 'Outfit', sans-serif;"><i class="fas fa-trophy text-warning me-1"></i> Rekap Kinerja & Top 10</h6>
    </div>
    <div class="px-3 mb-3">
        <!-- Top 10 Input -->
        <div class="card border-0 shadow-sm rounded-3 mb-3 overflow-hidden">
            <div class="card-header py-2 px-3" style="background: linear-gradient(135deg, #004D4F 0%, #007275 100%); color: #ffffff;">
                <span class="font-weight-bold" style="font-size: 12px;"><i class="fas fa-file-signature text-warning me-1"></i> Top 10 Input Temuan</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topInputOfficers)): ?>
                    <div class="text-center py-3 text-muted small">Belum ada data input pada periode ini.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush" style="font-size: 12px;">
                        <?php foreach ($topInputOfficers as $idx => $officer): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <span class="fw-bold me-1"><?= $idx + 1 ?>.</span>
                                    <span class="fw-bold text-dark"><?= esc($officer['created_by_name']) ?></span>
                                    <small class="text-muted d-block" style="font-size: 10px;">NIP: <?= esc($officer['created_by_nip'] ?: '-') ?></small>
                                </div>
                                <span class="badge bg-primary rounded-pill"><?= number_format($officer['total_input']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top 10 Update -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header py-2 px-3" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff;">
                <span class="font-weight-bold" style="font-size: 12px;"><i class="fas fa-check-circle text-white me-1"></i> Top 10 Update & Eksekusi</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topUpdateOfficers)): ?>
                    <div class="text-center py-3 text-muted small">Belum ada data update pada periode ini.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush" style="font-size: 12px;">
                        <?php foreach ($topUpdateOfficers as $idx => $officer): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <span class="fw-bold me-1"><?= $idx + 1 ?>.</span>
                                    <span class="fw-bold text-dark"><?= esc($officer['updated_by_name']) ?></span>
                                    <small class="text-muted d-block" style="font-size: 10px;">NIP: <?= esc($officer['updated_by_nip'] ?: '-') ?></small>
                                </div>
                                <span class="badge bg-success rounded-pill" style="background-color: #059669 !important;"><?= number_format($officer['total_update']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Data Master Section (For Admins) -->
    <?php if (check_role(['administrator', 'admin', 'admin_pusat', 'admin_ulp'])): ?>
        <div class="px-3 mt-4 mb-2">
            <h6 class="text-secondary font-weight-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px; font-family: 'Outfit', sans-serif;"><i class="fas fa-database mr-1"></i> Data Master</h6>
        </div>
        <div class="menu-container">
            <div class="menu-grid">
                <?php if (session()->get('user_role') === 'administrator'): ?>
                    <a href="<?= site_url('ulps') ?>" class="menu-card py-3">
                        <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                            <i class="fas fa-building text-secondary" style="font-size: 14px;"></i>
                        </div>
                        <div class="card-label" style="font-size: 0.75rem;">Data ULP</div>
                    </a>
                <?php endif; ?>
                
                <a href="<?= site_url('penyulang') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                        <i class="fas fa-network-wired text-secondary" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Data Penyulang</div>
                </a>
                
                <a href="<?= site_url('sections') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                        <i class="fas fa-route text-secondary" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Data Section</div>
                </a>
                
                <a href="<?= site_url('users') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f1f5f9;">
                        <i class="fas fa-users text-secondary" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Data User</div>
                </a>

                <a href="<?= site_url('import') ?>" class="menu-card py-3">
                    <div class="icon-circle mb-2" style="width: 36px; height: 36px; margin-bottom: 8px; background-color: #f0fdf4;">
                        <i class="fas fa-file-excel text-success" style="font-size: 14px;"></i>
                    </div>
                    <div class="card-label" style="font-size: 0.75rem;">Impor Excel</div>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Toggle Version Footer -->
    <div class="footer-toggle text-muted px-3">
        <p class="mb-2">Menampilkan versi mobile khusus lapangan.</p>
        <a href="<?= site_url('dashboard/toggle-view?t=' . time()) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-4 fw-bold text-primary shadow-sm" style="font-size: 11px; border-color: #005eb8;">
            <i class="fas fa-desktop mr-1"></i> Beralih ke Versi Desktop
        </a>
    </div>

    <!-- Scripts for SweetAlert2 & Ticker Editing -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function promptEditAnnouncement() {
            var currentMsg = $('.running-announcement-text-target').first().text().trim();
            Swal.fire({
                title: 'Edit Kata-Kata Motivasi Harian',
                text: 'Masukkan pesan motivasi atau pengumuman harian untuk seluruh tim:',
                input: 'textarea',
                inputValue: currentMsg,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Simpan & Tampilkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#004D4F',
                inputValidator: (value) => {
                    if (!value || value.trim().length === 0) {
                        return 'Kata-kata motivasi harian tidak boleh kosong!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "<?= site_url('setting/update-announcement') ?>",
                        type: 'POST',
                        data: {
                            message: result.value,
                            "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
                        },
                        dataType: 'json',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        success: function(res) {
                            if (res.success) {
                                var $targets = $('.running-announcement-text-target');
                                $targets.text(res.announcement);
                                $targets.css('animation', 'none');
                                $targets.each(function() { void this.offsetWidth; });
                                $targets.css('animation', 'tickerAnimation 20s linear infinite');

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: res.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire('Gagal', res.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Gagal menyimpan kata-kata motivasi harian.', 'error');
                        }
                    });
                }
            });
        }
    </script>

    <!-- Floating Voice Assistant (Mobile Dashboard) -->
    <style>
        #global-voice-container {
            position: fixed !important;
            bottom: 30px !important;
            right: 18px !important;
            z-index: 999999 !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            pointer-events: auto !important;
            touch-action: manipulation !important;
        }
        #btn-global-mic {
            pointer-events: auto !important;
            cursor: pointer !important;
            touch-action: manipulation !important;
            -webkit-tap-highlight-color: rgba(0,0,0,0) !important;
        }
        #btn-global-mic.listening {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            border-color: #fca5a5 !important;
            animation: pulseMicAnimationMobile 1.2s infinite;
        }
        @keyframes pulseMicAnimationMobile {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 14px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
    <div id="global-voice-container">
        <div id="global-voice-bubble" class="shadow-sm d-none animate__animated animate__fadeInRight" 
             style="background: #0f172a; color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); padding: 8px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: bold; white-space: nowrap; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);">
            <i class="fas fa-circle-notch fa-spin mr-1"></i> <span id="global-voice-text">Mendengarkan...</span>
        </div>
        
        <button type="button" id="btn-global-mic" class="btn btn-primary" title="Perintah Suara"
                style="width: 58px; height: 58px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.45rem; border: 2px solid rgba(255,255,255,0.25); box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45) !important; background: linear-gradient(135deg, #005eb8 0%, #003f8a 100%) !important;">
            <i class="fas fa-microphone" id="global-mic-icon"></i>
        </button>
    </div>

    <!-- SweetAlert2 JS (Failsafe) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
