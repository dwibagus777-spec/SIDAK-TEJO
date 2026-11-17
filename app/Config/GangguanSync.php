<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration for Google Sheets Gangguan synchronization.
 *
 * Non-secret defaults live here; optional private URL or bearer token
 * overrides are read strictly from environment variables and never exposed
 * in responses, views, or logs.
 */
class GangguanSync extends BaseConfig
{
    public string $spreadsheetId = '1bbY14--JLP4wnCh7NT7tLYK71ZkBGbZabQ3a7YaUEQg';
    public string $spreadsheetGid = '0';
    public string $sheetName = 'DATA_GANGGUAN';
    public string $sourceLabel = 'Google Sheets (DATA_GANGGUAN)';
    public int $autoSyncTtlSeconds = 60;
    public int $httpTimeoutSeconds = 15;

    protected string $customCsvUrl = '';
    protected string $authBearerToken = '';

    public function __construct()
    {
        parent::__construct();

        $envId = env('gangguan.spreadsheetId');
        if (is_string($envId) && trim($envId) !== '') {
            $this->spreadsheetId = trim($envId);
        }

        $envGid = env('gangguan.spreadsheetGid');
        if (is_string($envGid) && trim($envGid) !== '') {
            $this->spreadsheetGid = trim($envGid);
        }

        $envSheet = env('gangguan.sheetName');
        if (is_string($envSheet) && trim($envSheet) !== '') {
            $this->sheetName = trim($envSheet);
        }

        $envTtl = env('gangguan.autoSyncTtlSeconds');
        if ($envTtl !== null && is_numeric($envTtl)) {
            $this->autoSyncTtlSeconds = max(0, (int) $envTtl);
        }

        $envUrl = env('gangguan.customCsvUrl');
        if (is_string($envUrl) && trim($envUrl) !== '') {
            $this->customCsvUrl = trim($envUrl);
        }

        $envToken = env('gangguan.authBearerToken');
        if (is_string($envToken) && trim($envToken) !== '') {
            $this->authBearerToken = trim($envToken);
        }
    }

    public function getExportCsvUrl(): string
    {
        if ($this->customCsvUrl !== '') {
            return $this->customCsvUrl;
        }

        return sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
            rawurlencode($this->spreadsheetId),
            rawurlencode($this->spreadsheetGid)
        );
    }

    public function getAuthBearerToken(): string
    {
        return $this->authBearerToken;
    }
}
