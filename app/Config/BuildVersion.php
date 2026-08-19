<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class BuildVersion extends BaseConfig
{
    // Public Instance Properties (Required for CI4 config() helper)
    public string $SYSTEM_VERSION = 'v2.5.0-ENTERPRISE';
    public string $BUILD_ID       = '20260819.007';
    public string $COMMIT_ID     = 'cd5f064';
    public string $DEPLOYED_AT   = '2026-08-19 10:33:00';
    public string $ENVIRONMENT   = 'production';
    public string $SYSTEM_NAME   = 'SIDAK TEJO';
    public string $SYSTEM_DESC   = 'Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo';

    // Public Class Constants (Required for direct Class::CONST access)
    public const SYSTEM_VERSION  = 'v2.5.0-ENTERPRISE';
    public const BUILD_ID        = '20260819.007';
    public const COMMIT_ID      = 'cd5f064';
    public const DEPLOYED_AT    = '2026-08-19 10:33:00';
    public const ENVIRONMENT    = 'production';
    public const SYSTEM_NAME    = 'SIDAK TEJO';
    public const SYSTEM_DESC    = 'Sistem Data dan Tindak Lanjut Temuan Inspeksi Sidoarjo';
}
