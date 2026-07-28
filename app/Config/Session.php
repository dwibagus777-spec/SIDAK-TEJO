<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Session\Handlers\FileHandler;

class Session extends BaseConfig
{
    /**
     * Session Driver - FileHandler untuk Hostinger Shared Hosting (Super Cepat)
     */
    public string $driver = FileHandler::class;

    /**
     * Session Cookie Name
     */
    public string $cookieName = 'sidaktejo_session';

    /**
     * Session Expiration - 8 jam (28800 detik)
     */
    public int $expiration = 28800;

    /**
     * Session Save Path - Local writable file storage for Hostinger
     */
    public string $savePath = WRITEPATH . 'session';

    /**
     * Session Match IP - False (prevents mobile disconnection on cellular IP changes)
     */
    public bool $matchIP = false;

    /**
     * Session Time to Update (seconds) - 300 detik
     */
    public int $timeToUpdate = 300;

    /**
     * Session Regenerate Destroy - False (prevents session file deletion race condition)
     */
    public bool $regenerateDestroy = false;

    /**
     * Session Database Group
     */
    public ?string $DBGroup = null;

    /**
     * Lock Retry Interval (microseconds)
     */
    public int $lockRetryInterval = 100_000;

    /**
     * Lock Max Retries
     */
    public int $lockMaxRetries = 300;
}
