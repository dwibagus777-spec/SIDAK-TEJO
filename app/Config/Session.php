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
     * Gunakan DatabaseHandler di Railway agar session tidak hilang saat restart container.
     * Gunakan FileHandler di lokal/development.
     *
     * @var class-string<BaseHandler>
     */
    public string $driver = DatabaseHandler::class;

    /**
     * Session Cookie Name
     */
    public string $cookieName = 'sidaktejo_sess';

    /**
     * Session Expiration - 8 jam (28800 detik)
     */
    public int $expiration = 28800;

    /**
     * Session Save Path
     * Untuk DatabaseHandler: nama tabel di database
     * Untuk FileHandler: path direktori writable
     */
    public string $savePath = 'ci_sessions';

    /**
     * Session Match IP
     */
    public bool $matchIP = false;

    /**
     * Session Time to Update (seconds)
     */
    public int $timeToUpdate = 400;

    /**
     * Session Regenerate Destroy
     */
    public bool $regenerateDestroy = false;

    /**
     * Session Database Group
     * Gunakan koneksi database default
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

        // Di lokal / non-Railway: fallback ke FileHandler supaya tetap bisa development
        $isRailway = getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_PROJECT_ID') !== false;
        $isCloud   = $isRailway || (getenv('PORT') && !in_array(getenv('PORT'), ['80', '443', '8080']));

        if (!$isCloud) {
            // Lokal: pakai FileHandler
            $this->driver   = FileHandler::class;
            $this->savePath = WRITEPATH . 'session';
            $this->DBGroup  = null;
        }
    }
}
