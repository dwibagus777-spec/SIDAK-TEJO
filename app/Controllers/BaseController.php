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

        // Auto-heal ci_sessions table structure if using DatabaseHandler
        try {
            static $sessionsChecked = false;
            if (!$sessionsChecked) {
                $sessionsChecked = true;
                $db = \Config\Database::connect();
                if ($db->tableExists('ci_sessions')) {
                    $keys = $db->query("SHOW KEYS FROM ci_sessions WHERE Key_name = 'PRIMARY'")->getResultArray();
                    if (empty($keys)) {
                        $db->query("ALTER TABLE `ci_sessions` ADD PRIMARY KEY (`id`, `ip_address`)");
                    }
                } else {
                    $db->query("CREATE TABLE IF NOT EXISTS `ci_sessions` (
                        `id` varchar(128) NOT NULL,
                        `ip_address` varchar(45) NOT NULL,
                        `timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
                        `data` blob NOT NULL,
                        PRIMARY KEY (`id`, `ip_address`),
                        KEY `ci_sessions_timestamp` (`timestamp`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }
        } catch (\Throwable $e) {
            // Ignore temporary connection failures
        }
    }
}

