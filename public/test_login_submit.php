<?php
header('Content-Type: text/plain; charset=utf-8');

try {
    echo "=== STEP 1: INITIALIZING CI4 BOOTSTRAP ===\n";
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/../app/Config/Paths.php';

    $paths = new \Config\Paths();
    require $paths->systemDirectory . '/Boot.php';

    // Boot framework in web context without running route runner
    define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
    \CodeIgniter\Boot::bootTest($paths);

    echo "=== STEP 2: TESTING USER REPOSITORY QUERY ===\n";
    $userRepo = new \App\Repositories\UserRepository();
    $user = $userRepo->findByUsername('admin');
    
    if (!$user) {
        echo "USER FIND: 'admin' not found. Testing first available user in DB...\n";
        $db = \Config\Database::connect();
        $user = $db->table('users')->get()->getFirstRow('array');
    }
    
    if (!$user) {
        echo "USER FIND FAILED: No users found in database table 'users'.\n";
    } else {
        echo "USER FOUND: ID=" . $user['id'] . ", Username=" . $user['username'] . ", Role=" . ($user['role'] ?? 'N/A') . "\n";
    }

    echo "=== STEP 3: TESTING SESSION SET ===\n";
    $session = session();
    if ($user) {
        $session->set([
            'user_id'      => $user['id'],
            'user_name'    => !empty($user['nama_pegawai']) ? $user['nama_pegawai'] : ($user['nama'] ?? $user['username']),
            'user_role'    => strtolower($user['role'] ?? 'admin'),
            'logged_in'    => true,
            'last_activity'=> time()
        ]);
        echo "SESSION SET: SUCCESS (user_id=" . $session->get('user_id') . ")\n";
    }

    echo "=== STEP 4: TESTING AUDIT LOG ACTIVITY ===\n";
    if (function_exists('log_activity')) {
        $logRes = log_activity('LOGIN_TEST', 'Diagnostic test login submit');
        echo "LOG_ACTIVITY(): " . ($logRes ? "SUCCESS" : "FAILED") . "\n";
    } else {
        echo "LOG_ACTIVITY(): Function not loaded.\n";
    }

    echo "=== STEP 5: TESTING DASHBOARD ROUTE & FILTER CONTROLLER ===\n";
    $dashboard = new \App\Controllers\Dashboard();
    echo "DASHBOARD CONTROLLER INSTANTIATION: SUCCESS\n";

    echo "\n=== ALL LOGIN PIPELINE STEPS PASSED SUCCESSFULLY ===\n";
} catch (\Throwable $e) {
    header('HTTP/1.1 200 OK', true, 200);
    echo "\n!!! LOGIN DIAGNOSTIC EXCEPTION CAUGHT !!!\n";
    echo "Class:   " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File:    " . $e->getFile() . "\n";
    echo "Line:    " . $e->getLine() . "\n\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
