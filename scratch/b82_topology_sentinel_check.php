<?php

declare(strict_types=1);

define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
defined('CI_DEBUG') || define('CI_DEBUG', true);

require COMPOSER_PATH;
require SYSTEMPATH . 'Common.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \CodeIgniter\Database\Config::connect('default', false);

$activeTl   = $db->table('translines')->where('status', 'ACTIVE')->countAllResults();
$physicalTl = $db->table('translines')->countAllResults();
$activeAs   = $db->table('assets')->where('status', 'ACTIVE')->countAllResults();
$physicalAs = $db->table('assets')->countAllResults();

$spanQuery = $db->query("SELECT SUM(ST_Length(geometry)) as total_span FROM translines WHERE status = 'ACTIVE'")->getRowArray();
$span = (float)($spanQuery['total_span'] ?? 0.0);

$sentinelPass = ($activeTl === 245 && $physicalTl === 254 && $activeAs === 5236 && $physicalAs === 5549);

echo json_encode([
    'gate'                => 'B8_2_TOPOLOGY_SENTINEL',
    'active_translines'   => $activeTl,
    'physical_translines' => $physicalTl,
    'active_assets'       => $activeAs,
    'physical_assets'     => $physicalAs,
    'network_span_m'      => round($span, 2),
    'topology_snapshot'   => 'TOPOLOGY-20260928-245-81c43a7f',
    'sentinel_status'     => $sentinelPass ? 'PASS_AUTHORITATIVE' : 'DELTA_DETECTED',
], JSON_PRETTY_PRINT);

exit($sentinelPass ? 0 : 1);
