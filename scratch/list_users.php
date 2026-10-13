<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;

$db = Database::connect('default');
if ($db->tableExists('users')) {
    $rows = $db->table('users')->select('id, username, role, status')->get()->getResultArray();
    foreach ($rows as $r) {
        echo "User: ID={$r['id']}, Username={$r['username']}, Role={$r['role']}, Status={$r['status']}\n";
    }
}
