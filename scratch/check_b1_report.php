<?php
$audit = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/GLOBAL_PRODUCTION_DRY_RUN_REPORT.json'), true);
echo "Rejections in B.1 dry run report:\n";
print_r($audit['summary']['rejections_taxonomy'] ?? $audit['rejection_breakdown'] ?? []);
