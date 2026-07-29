<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// --- Rute Terbuka (Public Routes) ---
$routes->match(['get', 'post'], 'login', 'Auth::login');
$routes->get('/', 'Auth::login');
$routes->match(['get', 'post'], 'logout', 'Auth::logout');
$routes->match(['get', 'post'], 'auth/logout', 'Auth::logout');

// Rute Cron Job Otomatis Harian Backup (Hostinger Compatible)
$routes->match(['get', 'post'], 'backup/cron', 'Backup::cron');


// --- Rute Terproteksi Login (Protected Routes) ---
$routes->group('', ['filter' => 'auth'], function ($routes) {
    
    // AI Predictive Maintenance & AI Center Routes
    $routes->get('ai-center', 'AiPredictiveController::index');
    $routes->get('ai-predictive', 'AiPredictiveController::index');
    $routes->get('ai-predictive/api-data', 'AiPredictiveController::apiData');
    $routes->get('ai-predictive/export-dataset', 'AiPredictiveController::exportDataset');
    $routes->get('executive-dashboard', 'Dashboard::executive');
    $routes->get('dashboard/executive', 'Dashboard::executive');
    $routes->get('dashboard/executive-api', 'Dashboard::executiveApi');
    $routes->get('dashboard/toggle-view', 'Dashboard::toggleView');
    $routes->get('dashboard/analytics-data', 'Dashboard::analyticsData');
    $routes->get('auth/ping', 'Auth::ping');

    // Self Service Change Password & Announcement Ticker
    $routes->get('change-password', 'Auth::changePassword');
    $routes->post('change-password', 'Auth::changePassword');
    $routes->get('setting/announcement', 'Setting::index');
    $routes->match(['get', 'post'], 'setting/update-announcement', 'Setting::updateAnnouncement');

    // Phase 17 - Master Asset Management
    $routes->get('assets', 'AssetController::index');
    $routes->get('assets/create', 'AssetController::create');
    $routes->post('assets/store', 'AssetController::store');
    $routes->get('assets/detail/(:num)', 'AssetController::detail/$1');

    // Phase 17 - Work Order Enterprise
    $routes->get('work-orders', 'WorkOrderController::index');
    $routes->get('work-orders/create', 'WorkOrderController::create');
    $routes->post('work-orders/store', 'WorkOrderController::store');
    $routes->get('work-orders/detail/(:num)', 'WorkOrderController::detail/$1');
    $routes->post('work-orders/update-status/(:num)', 'WorkOrderController::updateStatus/$1');
    $routes->post('work-orders/toggle-checklist/(:num)', 'WorkOrderController::toggleChecklist/$1');
    $routes->post('work-orders/add-material/(:num)', 'WorkOrderController::addMaterial/$1');

    // Phase 18 - Smart GIS & Network Mapping Enterprise
    $routes->get('gis', 'GisController::index');
    $routes->get('peta-jaringan', 'GisController::index');
    $routes->get('gis/api-data', 'GisController::apiData');
    $routes->post('gis/checkin', 'GisController::checkin');

    // Phase 19 - AI Predictive Maintenance & Decision Support
    $routes->get('ai-predictive', 'AiPredictiveController::index');
    $routes->get('ai-predictive/api-data', 'AiPredictiveController::apiData');
    $routes->get('ai-predictive/export-dataset', 'AiPredictiveController::exportDataset');

    // Phase 21 - Smart Notification Center & Automation
    $routes->get('notifications', 'Notification::index');
    $routes->get('notifications/read-all', 'Notification::markAllAsRead');
    $routes->get('notifications/templates', 'Notification::templates');
    $routes->get('notifications/rules', 'Notification::rules');
    $routes->get('notifications/preferences', 'Notification::preferences');
    $routes->get('notifications/api-unread', 'Notification::apiUnread');
    $routes->get('notifications/trigger-escalation', 'Notification::triggerEscalation');

    // Phase 22 - Executive Command Center (ECC)
    $routes->get('ecc', 'ExecutiveCommandCenter::index');
    $routes->get('ecc/tv-mode', 'ExecutiveCommandCenter::tvMode');
    $routes->get('ecc/api-data', 'ExecutiveCommandCenter::apiData');
    $routes->get('ecc/sse-stream', 'ExecutiveCommandCenter::sseStream');

    // Phase 23 - Digital Document Intelligence
    $routes->get('documents', 'DocumentCenter::index');
    $routes->get('documents/create', 'DocumentCenter::create');
    $routes->post('documents/store', 'DocumentCenter::store');
    $routes->get('documents/detail/(:num)', 'DocumentCenter::detail/$1');
    $routes->post('documents/approve/(:num)', 'DocumentCenter::approve/$1');

    // Modul Backup & Restore Database Hostinger (Admin Saja - Terproteksi Role)
    $routes->group('backup-database', ['filter' => 'role:administrator,admin_pusat'], function ($routes) {
        $routes->get('/', 'DatabaseBackup::index');
        $routes->post('create', 'DatabaseBackup::create');
        $routes->get('download/(:segment)', 'DatabaseBackup::download/$1');
        $routes->get('delete/(:segment)', 'DatabaseBackup::delete/$1');
        $routes->get('clean-old', 'DatabaseBackup::cleanOldBackups');
        $routes->post('restore', 'DatabaseBackup::restore');
    });

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
    $routes->match(['GET', 'POST'], 'temuan/delete/(:num)', 'Temuan::delete/$1', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp,inspeksi,pdkb,har_gardu,har_konstruksi,har_row,har_crane,yantek,supervisor_ulp,supervisor_up3']);
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
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'Ulp::delete/$1');
    });

    // Master Data Penyulang (Admin & Admin ULP)
    $routes->group('penyulang', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Penyulang::index');
        $routes->get('create', 'Penyulang::create');
        $routes->post('store', 'Penyulang::store');
        $routes->get('edit/(:num)', 'Penyulang::edit/$1');
        $routes->post('update/(:num)', 'Penyulang::update/$1');
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'Penyulang::delete/$1');
    });

    // Master Data Section (Admin & Admin ULP)
    $routes->group('sections', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'Section::index');
        $routes->get('create', 'Section::create');
        $routes->post('store', 'Section::store');
        $routes->get('edit/(:num)', 'Section::edit/$1');
        $routes->post('update/(:num)', 'Section::update/$1');
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'Section::delete/$1');
    });

    // Master Data User (Admin & Admin ULP)
    $routes->group('users', ['filter' => 'role:administrator,admin,admin_pusat,admin_ulp'], function ($routes) {
        $routes->get('/', 'User::index');
        $routes->get('create', 'User::create');
        $routes->post('store', 'User::store');
        $routes->get('edit/(:num)', 'User::edit/$1');
        $routes->post('update/(:num)', 'User::update/$1');
        $routes->match(['GET', 'POST'], 'delete/(:num)', 'User::delete/$1');
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
        $routes->match(['GET', 'POST'], 'kubikel/delete/(:num)', 'Eviden::kubikelDelete/$1');

        // Trafo
        $routes->get('trafo', 'Eviden::trafo');
        $routes->get('trafo/create', 'Eviden::trafoCreate');
        $routes->post('trafo/store', 'Eviden::trafoStore');
        $routes->get('trafo/edit/(:num)', 'Eviden::trafoEdit/$1');
        $routes->post('trafo/update/(:num)', 'Eviden::trafoUpdate/$1');
        $routes->match(['GET', 'POST'], 'trafo/delete/(:num)', 'Eviden::trafoDelete/$1');

        // Management Trafo
        $routes->get('management', 'Eviden::management');
        $routes->get('management/create', 'Eviden::managementCreate');
        $routes->post('management/store', 'Eviden::managementStore');
        $routes->get('management/edit/(:num)', 'Eviden::managementEdit/$1');
        $routes->post('management/update/(:num)', 'Eviden::managementUpdate/$1');
        $routes->match(['GET', 'POST'], 'management/delete/(:num)', 'Eviden::managementDelete/$1');

        // Delete Single Photo
        $routes->match(['GET', 'POST'], 'delete-foto/(:num)', 'Eviden::deleteFoto/$1');

        // Dynamic AJAX gallery & CSV export
        $routes->get('ajax-get-fotos', 'Eviden::ajaxGetFotos');
        $routes->get('export-kubikel', 'Eviden::exportKubikel');
        $routes->get('export-trafo', 'Eviden::exportTrafo');
        $routes->get('export-management', 'Eviden::exportManagement');
        $routes->post('download-pdf', 'Eviden::downloadPdf');
        $routes->post('download-foto', 'Eviden::downloadFoto');
    });
});

// --- Rute REST API v1 (Flutter Mobile App Backend with JWT) ---
$routes->group('api/v1', function ($routes) {
    
    // Auth & Sync (Unprotected / Direct API)
    $routes->post('auth/login', 'Api\AuthController::login');
    $routes->post('voice-ai/process', 'Api\VoiceAIApiController::process');
    $routes->get('voice-ai/summary', 'Api\VoiceAIApiController::summary');
    $routes->get('voice-ai/notifications', 'Api\VoiceAIApiController::notifications');
    $routes->get('voice-ai/logs', 'Api\VoiceAIApiController::logs');
    $routes->post('sync/bulk-records', 'Api\SyncApiController::bulkRecords');
    $routes->post('sync/upload-photo', 'Api\SyncApiController::uploadPhoto');

    // Protected via JWT Bearer Token Filter ('filter' => 'jwt')
    $routes->group('', ['filter' => 'jwt'], function ($routes) {
        
        // Auth Self Service
        $routes->get('auth/me', 'Api\AuthController::me');
        $routes->post('auth/change-password', 'Api\AuthController::changePassword');

        // Master Data Dropdowns
        $routes->get('master/ulps', 'Api\MasterApiController::ulps');
        $routes->get('master/penyulangs', 'Api\MasterApiController::penyulangs');
        $routes->get('master/sections', 'Api\MasterApiController::sections');

        // Temuan REST API CRUD
        $routes->get('temuan', 'Api\TemuanApiController::index');
        $routes->get('temuan/terdekat', 'Api\TemuanApiController::terdekat');
        $routes->get('temuan/(:num)', 'Api\TemuanApiController::show/$1');
        $routes->post('temuan', 'Api\TemuanApiController::create');
        $routes->post('temuan/update/(:num)', 'Api\TemuanApiController::update/$1');
        $routes->delete('temuan/delete/(:num)', 'Api\TemuanApiController::delete/$1');
        $routes->post('temuan/tindak-lanjut/(:num)', 'Api\TemuanApiController::tindakLanjut/$1');

        // Eviden REST API
        $routes->get('eviden/kubikel', 'Api\EvidenApiController::kubikelList');
        $routes->get('eviden/trafo', 'Api\EvidenApiController::trafoList');

        // Machine Learning & AI Integration REST API
        $routes->get('ai/dataset', 'Api\AiApiController::dataset');
        $routes->get('ai/summary', 'Api\AiApiController::summary');
        // AI Assistant REST API
        $routes->post('ai/query', 'Api\AiApiController::query');
    });
});

// --- Clean REST API Endpoints (/api/...) ---
$routes->group('api', function ($routes) {
    // 1. POST /api/login
    $routes->post('login', 'Api\AuthController::login');
    
    // 2. GET /api/sync
    $routes->get('sync', 'Api\SyncApiController::getSyncMeta');

    // 3. GET /api/user
    $routes->get('user', 'Api\AuthController::me');

    // 4. GET /api/temuan
    $routes->get('temuan', 'Api\TemuanApiController::index');

    // 5. POST /api/temuan
    $routes->post('temuan', 'Api\TemuanApiController::create');

    // 6. PUT /api/temuan/{id}
    $routes->put('temuan/(:num)', 'Api\TemuanApiController::update/$1');
    $routes->post('temuan/(:num)', 'Api\TemuanApiController::update/$1');

    // 7. DELETE /api/temuan/{id}
    $routes->delete('temuan/(:num)', 'Api\TemuanApiController::delete/$1');

    // 8. GET /api/history
    $routes->get('history', 'Api\TemuanApiController::history');

    // 9. GET /api/dashboard
    $routes->get('dashboard', 'Api\TemuanApiController::dashboard');

    // 10. GET /api/chart
    $routes->get('chart', 'Api\TemuanApiController::chart');

    // 11. GET /api/notifikasi
    $routes->get('notifikasi', 'Api\TemuanApiController::notifikasi');
});

// --- Rute REST API Legacy (Kompatibilitas Sistem PLN) ---
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

// Phase 23: Public QR Code Document Verification (No Login Required)
$routes->get('documents/verify/(:segment)', 'DocumentCenter::verify/$1');

// Phase 24: Enterprise Integration Platform (EIP) Routes
$routes->group('integration', ['filter' => 'role:administrator,admin,admin_pusat'], function ($routes) {
    $routes->get('/', 'IntegrationCenter::index');
    $routes->post('generate-key', 'IntegrationCenter::generateApiKey');
    $routes->post('register-webhook', 'IntegrationCenter::registerWebhook');
    $routes->get('test-webhook/(:num)', 'IntegrationCenter::testWebhook/$1');
    $routes->get('export', 'IntegrationCenter::exportData');
});

// Phase 24: EIP OpenAPI / Health / Multi-version REST API
$routes->get('api/health', 'Api\HealthController::index');
$routes->get('api/docs/json', 'Api\DocsController::json');
$routes->get('api/docs/ui', 'Api\DocsController::ui');

// Phase 25 & 31.2: Production Hardening, Live Operations & Health Endpoints
$routes->get('health', 'StatusController::health');
$routes->get('status', 'StatusController::status');
$routes->get('status/live-metrics', 'StatusController::liveMetrics');
$routes->get('status/optimize-database', 'StatusController::optimizeDatabase');

// API Versioning: v1, v2, v3
foreach (['v1', 'v2', 'v3'] as $version) {
    $routes->group("api/{$version}", function ($routes) {
        $routes->post('auth/login', 'Api\v1\ApiController::login');
        $routes->post('auth/refresh', 'Api\v1\ApiController::refreshToken');
        $routes->get('temuan', 'Api\v1\ApiController::getTemuan');
        $routes->get('temuan/(:num)', 'Api\v1\ApiController::getTemuanDetail/$1');
        $routes->get('work-orders', 'Api\v1\ApiController::getWorkOrders');
        $routes->get('work-orders/(:num)', 'Api\v1\ApiController::getWorkOrderDetail/$1');
        $routes->get('assets', 'Api\v1\ApiController::getAssets');
        $routes->get('users', 'Api\v1\ApiController::getUsers');
        $routes->get('dashboard', 'Api\v1\ApiController::getDashboardStats');
        $routes->get('notifications', 'Api\v1\ApiController::getNotifications');
        $routes->get('documents', 'Api\v1\ApiController::getDocuments');
    });
}

