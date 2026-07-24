<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// --- Rute Terbuka (Public Routes) ---
$routes->get('/', 'Auth::login');
$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::login');
$routes->get('/logout', 'Auth::logout');
$routes->get('auth/logout', 'Auth::logout');
$routes->post('auth/logout', 'Auth::logout');

// --- Rute Terproteksi Login (Protected Routes) ---
$routes->group('', ['filter' => 'auth'], function ($routes) {
    
    // Dashboard
    $routes->get('dashboard', 'Dashboard::index');
    $routes->get('dashboard/toggle-view', 'Dashboard::toggleView');

    // Self Service Change Password & Announcement Ticker
    $routes->get('change-password', 'Auth::changePassword');
    $routes->post('change-password', 'Auth::changePassword');
    $routes->get('setting/announcement', 'Setting::index');
    $routes->match(['get', 'post'], 'setting/update-announcement', 'Setting::updateAnnouncement');

    // Import CSV (Admin saja)
    $routes->group('import', ['filter' => 'role:administrator,admin_ulp'], function ($routes) {
        $routes->get('/', 'Import::index');
        $routes->get('template/(:segment)', 'Import::template/$1');
        $routes->get('template-section', 'Import::templateSectionDynamic');
        $routes->get('template-penyulang', 'Import::templatePenyulangDynamic');
        $routes->get('ajax-penyulang', 'Import::ajaxGetPenyulang');
        $routes->get('export-penyulang', 'Import::exportPenyulang');
        $routes->get('export-section', 'Import::exportSection');
        $routes->post('process', 'Import::process');
    });

    // Temuan & AJAX Cascades
    $routes->get('temuan', 'Temuan::index');
    $routes->get('temuan/terdekat', 'Temuan::terdekat');
    $routes->get('temuan/ajax-terdekat', 'Temuan::ajaxTerdekat');
    $routes->get('temuan/ajax-detail/(:num)', 'Temuan::ajaxDetail/$1');
    $routes->get('temuan/create', 'Temuan::create', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->post('temuan/store', 'Temuan::store', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->get('temuan/detail/(:num)', 'Temuan::detail/$1');
    $routes->post('temuan/tindak-lanjut/(:num)', 'Temuan::tindakLanjut/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->match(['get', 'post'], 'temuan/delete/(:num)', 'Temuan::delete/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->get('temuan/edit/(:num)', 'Temuan::edit/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->post('temuan/update/(:num)', 'Temuan::update/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
    $routes->get('temuan/update-pekerjaan', 'Temuan::updatePekerjaan');
    $routes->post('temuan/ajax-update-pekerjaan', 'Temuan::ajaxUpdatePekerjaan');
    
    // AJAX data loading
    $routes->get('temuan/ajax-penyulang/(:num)', 'Temuan::ajaxGetPenyulang/$1');
    $routes->get('temuan/ajax-section/(:num)', 'Temuan::ajaxGetSection/$1');
    $routes->post('temuan/ajax-datatables', 'Temuan::ajaxDataTables');

    // Master Data ULP (Admin & Admin ULP saja)
    $routes->group('ulps', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Ulp::index');
        $routes->get('create', 'Ulp::create');
        $routes->post('store', 'Ulp::store');
        $routes->get('edit/(:num)', 'Ulp::edit/$1');
        $routes->post('update/(:num)', 'Ulp::update/$1');
        $routes->match(['get', 'post'], 'delete/(:num)', 'Ulp::delete/$1');
    });

    // Master Data Penyulang (Admin & Admin ULP)
    $routes->group('penyulang', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Penyulang::index');
        $routes->get('create', 'Penyulang::create');
        $routes->post('store', 'Penyulang::store');
        $routes->get('edit/(:num)', 'Penyulang::edit/$1');
        $routes->post('update/(:num)', 'Penyulang::update/$1');
        $routes->match(['get', 'post'], 'delete/(:num)', 'Penyulang::delete/$1');
    });

    // Master Data Section (Admin & Admin ULP)
    $routes->group('sections', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Section::index');
        $routes->get('create', 'Section::create');
        $routes->post('store', 'Section::store');
        $routes->get('edit/(:num)', 'Section::edit/$1');
        $routes->post('update/(:num)', 'Section::update/$1');
        $routes->match(['get', 'post'], 'delete/(:num)', 'Section::delete/$1');
    });

    // Master Data User (Admin & Admin ULP)
    $routes->group('users', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'User::index');
        $routes->get('create', 'User::create');
        $routes->post('store', 'User::store');
        $routes->get('edit/(:num)', 'User::edit/$1');
        $routes->post('update/(:num)', 'User::update/$1');
        $routes->match(['get', 'post'], 'delete/(:num)', 'User::delete/$1');
        $routes->post('reset-password/(:num)', 'User::resetPassword/$1');
    });

    // Pusat Laporan
    $routes->group('laporan', function ($routes) {
        $routes->get('/', 'Laporan::index');
        $routes->get('temuan', 'Laporan::temuan');
        $routes->post('preview', 'Laporan::preview');
        $routes->post('print', 'Laporan::print');
        $routes->post('excel', 'Laporan::excel');
        $routes->post('csv', 'Laporan::csv');

        // Laporan Eviden
        $routes->get('eviden', 'Laporan::eviden');
        $routes->post('ajax-eviden-data', 'Laporan::ajaxEvidenData');
        $routes->post('export-eviden-pdf', 'Laporan::exportEvidenPdf');
        $routes->post('export-eviden-excel', 'Laporan::exportEvidenExcel');
        $routes->post('export-eviden-csv', 'Laporan::exportEvidenCsv');
        $routes->post('export-eviden-ppt', 'Laporan::exportEvidenPpt');

        // Laporan Management Trafo
        $routes->get('management', 'Laporan::management');
        $routes->post('ajax-management-data', 'Laporan::ajaxManagementData');
        $routes->post('export-management-pdf', 'Laporan::exportManagementPdf');
        $routes->post('export-management-excel', 'Laporan::exportManagementExcel');
        $routes->post('export-management-csv', 'Laporan::exportManagementCsv');
    });

    // Identifikasi Gangguan Penyulang
    $routes->get('identifikasi', 'Identifikasi::index');
    $routes->post('identifikasi/analisis', 'Identifikasi::analisis');
    $routes->post('identifikasi/export-pdf', 'Identifikasi::exportPdf');
    $routes->post('identifikasi/export-excel', 'Identifikasi::exportExcel');
    $routes->post('identifikasi/export-csv', 'Identifikasi::exportCsv');
    $routes->post('identifikasi/export-ppt', 'Identifikasi::exportPpt');

    // Eviden Lapangan (Kubikel & Trafo) - HAR Gardu, PDKB, Admin ULP & Admin
    $routes->group('eviden', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,har_gardu,har_konstruksi,har_row,har_crane,pdkb,yantek,inspeksi,supervisor_ulp,supervisor_up3'], function ($routes) {
        // Kubikel
        $routes->get('kubikel', 'Eviden::kubikel');
        $routes->get('kubikel/create', 'Eviden::kubikelCreate');
        $routes->post('kubikel/store', 'Eviden::kubikelStore');
        $routes->get('kubikel/edit/(:num)', 'Eviden::kubikelEdit/$1');
        $routes->post('kubikel/update/(:num)', 'Eviden::kubikelUpdate/$1');
        $routes->match(['get', 'post'], 'kubikel/delete/(:num)', 'Eviden::kubikelDelete/$1');

        // Trafo
        $routes->get('trafo', 'Eviden::trafo');
        $routes->get('trafo/create', 'Eviden::trafoCreate');
        $routes->post('trafo/store', 'Eviden::trafoStore');
        $routes->get('trafo/edit/(:num)', 'Eviden::trafoEdit/$1');
        $routes->post('trafo/update/(:num)', 'Eviden::trafoUpdate/$1');
        $routes->match(['get', 'post'], 'trafo/delete/(:num)', 'Eviden::trafoDelete/$1');

        // Management Trafo
        $routes->get('management', 'Eviden::management');
        $routes->get('management/create', 'Eviden::managementCreate');
        $routes->post('management/store', 'Eviden::managementStore');
        $routes->get('management/edit/(:num)', 'Eviden::managementEdit/$1');
        $routes->post('management/update/(:num)', 'Eviden::managementUpdate/$1');
        $routes->match(['get', 'post'], 'management/delete/(:num)', 'Eviden::managementDelete/$1');

        // Delete Single Photo
        $routes->match(['get', 'post'], 'delete-foto/(:num)', 'Eviden::deleteFoto/$1');

        // Dynamic AJAX gallery & CSV export
        $routes->get('ajax-get-fotos', 'Eviden::ajaxGetFotos');
        $routes->get('export-kubikel', 'Eviden::exportKubikel');
        $routes->get('export-trafo', 'Eviden::exportTrafo');
        $routes->get('export-management', 'Eviden::exportManagement');
        $routes->post('download-pdf', 'Eviden::downloadPdf');
        $routes->post('download-foto', 'Eviden::downloadFoto');
    });
});

// --- Rute REST API (Android/Sistem PLN Lainnya) ---
$routes->group('api', function ($routes) {
    $routes->post('auth/login', 'Api::login');
    $routes->post('auth/change-password', 'Api::changePassword');
    $routes->get('options', 'Api::getOptions');
    $routes->get('penyulangs/(:num)', 'Api::getPenyulangsByUlp/$1');
    $routes->get('sections/(:num)', 'Api::getSectionsByPenyulang/$1');
    $routes->get('temuan', 'Api::getTemuan');
    $routes->get('temuan/terdekat', 'Api::getTemuanTerdekat');
    $routes->get('temuan/(:num)', 'Api::detailTemuan/$1');
    $routes->post('temuan/create', 'Api::createTemuan');
    $routes->post('temuan/tindak-lanjut', 'Api::tindakLanjut');
});
