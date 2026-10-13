<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

if ($db->tableExists('operational_work_acceptances')) {
    $fields = $db->getFieldNames('operational_work_acceptances');
    if (!in_array('manager_signoff', $fields)) {
        $db->query("ALTER TABLE operational_work_acceptances ADD COLUMN manager_signoff VARCHAR(50) NULL AFTER closure_rationale");
    }
    if (!in_array('closure_rationale', $fields)) {
        $db->query("ALTER TABLE operational_work_acceptances ADD COLUMN closure_rationale TEXT NULL AFTER acceptance_status");
    }
    echo "operational_work_acceptances manager_signoff & closure_rationale columns updated successfully!\n";
}
