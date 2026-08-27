<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    protected $helpers = ['form', 'url', 'app'];

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);


        // Auto-heal ci_sessions table & performance indexes
        try {
            static $dbInitialized = false;
            if (!$dbInitialized) {
                $dbInitialized = true;
                $db = \Config\Database::connect();
                if ($db->tableExists('ci_sessions')) {
                    $keys = $db->query("SHOW KEYS FROM ci_sessions WHERE Key_name = 'PRIMARY'")->getResultArray();
                    if (empty($keys)) {
                        @$db->query("ALTER TABLE `ci_sessions` ADD PRIMARY KEY (`id`, `ip_address`)");
                    }
                } else {
                    @$db->query("CREATE TABLE IF NOT EXISTS `ci_sessions` (
                        `id` varchar(128) NOT NULL,
                        `ip_address` varchar(45) NOT NULL,
                        `timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
                        `data` blob NOT NULL,
                        PRIMARY KEY (`id`, `ip_address`),
                        KEY `ci_sessions_timestamp` (`timestamp`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }

                if (!$db->tableExists('assets')) {
                    new \App\Models\AssetModel();
                }

                if (!$db->tableExists('work_orders')) {
                    new \App\Models\WorkOrderModel();
                }

                // Create Hostinger MySQL Performance Indexes
                $indexes = [
                    'temuan' => [
                        'idx_temuan_composite'    => "CREATE INDEX idx_temuan_composite ON temuan (deleted_at, ulp_id, jenis_temuan, status, prioritas, tanggal_temuan)",
                        'idx_temuan_nomor'        => "CREATE INDEX idx_temuan_nomor ON temuan (nomor_temuan)",
                        'idx_temuan_gis'          => "CREATE INDEX idx_temuan_gis ON temuan (deleted_at, latitude, longitude, ulp_id)",
                        'idx_temuan_penyulang_sec'=> "CREATE INDEX idx_temuan_penyulang_sec ON temuan (penyulang_id, section_id)"
                    ],
                    'foto_eviden' => [
                        'idx_foto_eviden_parent'  => "CREATE INDEX idx_foto_eviden_parent ON foto_eviden (id_parent, kategori)"
                    ],
                    'penyulang' => [
                        'idx_penyulang_status'    => "CREATE INDEX idx_penyulang_status ON penyulang (ulp_id, status)"
                    ],
                    'sections' => [
                        'idx_section_status'      => "CREATE INDEX idx_section_status ON sections (penyulang_id, status)"
                    ],
                    'users' => [
                        'idx_users_auth'          => "CREATE INDEX idx_users_auth ON users (username, status)",
                        'idx_users_ulp'           => "CREATE INDEX idx_users_ulp ON users (ulp_id, role)"
                    ]
                ];

                foreach ($indexes as $table => $tableIndexes) {
                    if ($db->tableExists($table)) {
                        $existing = array_column($db->query("SHOW KEYS FROM `{$table}`")->getResultArray(), 'Key_name');
                        foreach ($tableIndexes as $indexName => $sql) {
                            if (!in_array($indexName, $existing, true)) {
                                try { @$db->query($sql); } catch (\Throwable $ex) {}
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore connection errors
        }
    }

    /**
     * Anti-Cache JSON Response Engine
     */
    protected function jsonResponse($data, int $statusCode = 200): ResponseInterface
    {
        $response = $this->response ?? \Config\Services::response();
        return $response
            ->setStatusCode($statusCode)
            ->setHeader('X-CSRF-TOKEN', csrf_hash())
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setJSON($data);
    }
}

