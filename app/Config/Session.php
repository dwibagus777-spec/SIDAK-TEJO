<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Session\Handlers\BaseHandler;
use CodeIgniter\Session\Handlers\FileHandler;
use CodeIgniter\Session\Handlers\DatabaseHandler;

class Session extends BaseConfig
{
    /**
     * Session Driver
     * DatabaseHandler untuk Production/Railway agar session tersimpan di MySQL & tidak reset saat redeploy.
     */
    public string $driver = DatabaseHandler::class;

    /**
     * Session Cookie Name
     */
    public string $cookieName = 'sidaktejo_session';

    /**
     * Session Expiration - 24 jam (86400 detik)
     */
    public int $expiration = 86400;

    /**
     * Session Save Path (nama tabel database)
     */
    public string $savePath = 'ci_sessions';

    /**
     * Session Match IP
     */
    public bool $matchIP = false;

    /**
     * Session Time to Update (seconds)
     */
    public int $timeToUpdate = 300;

    /**
     * Session Regenerate Destroy
     */
    public bool $regenerateDestroy = false;

    /**
     * Session Database Group
     */
    public ?string $DBGroup = 'default';

    /**
     * Lock Retry Interval (microseconds)
     */
    public int $lockRetryInterval = 100_000;

    /**
     * Lock Max Retries
     */
    public int $lockMaxRetries = 300;

    public function __construct()
    {
        parent::__construct();

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
        $isCli = (php_sapi_name() === 'cli');

        if ($isLocal && !$isCli) {
            // Development lokal (XAMPP)
            $this->driver   = FileHandler::class;
            $this->savePath = WRITEPATH . 'session';
            $this->DBGroup  = null;
        } else {
            // Cloud / Railway Production
            $this->driver   = DatabaseHandler::class;
            $this->savePath = 'ci_sessions';
            $this->DBGroup  = 'default';
        }
    }
}

