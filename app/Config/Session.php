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
     * Session Expiration - 24 jam (86400 detik)
     */
    public int $expiration = 86400;

    /**
     * Session Save Path
     */
    public string $savePath = WRITEPATH . 'session';

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
