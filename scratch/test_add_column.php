<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');
$forge = \Config\Database::forge('default');

echo "Fields before:\n";
print_r($db->getFieldNames('fault_cases'));

$fieldsToAdd = [
    'priority' => [
        'type'       => 'VARCHAR',
        'constraint' => 16,
        'default'    => 'MEDIUM',
    ],
    'opened_at' => [
        'type'  => 'DATETIME',
        'null'  => true,
    ],
    'closed_at' => [
        'type'  => 'DATETIME',
        'null'  => true,
    ],
    'created_by' => [
        'type'       => 'INT',
        'constraint' => 11,
        'unsigned'   => true,
        'null'       => true,
    ],
];

try {
    $res = $forge->addColumn('fault_cases', $fieldsToAdd);
    var_dump($res);
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "Fields after:\n";
print_r($db->getFieldNames('fault_cases'));
