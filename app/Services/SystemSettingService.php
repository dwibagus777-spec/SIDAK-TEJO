<?php

namespace App\Services;

use App\Repositories\SystemSettingRepository;

class SystemSettingService
{
    private SystemSettingRepository $repository;
    private const CACHE_PREFIX = 'sys_setting_';
    private const CACHE_TTL    = 86400; // 24 jam

    public function __construct()
    {
        $this->repository = new SystemSettingRepository();
    }

    /**
     * Dapatkan nilai setting dari Cache / Database dengan Fallback
     */
    public function get(string $key, ?string $default = null): string
    {
        $cacheKey = self::CACHE_PREFIX . $key;
        $cached = cache($cacheKey);

        if ($cached !== null && $cached !== false) {
            return (string)$cached;
        }

        $val = $this->repository->getByKey($key, $default);
        $finalVal = ($val !== null && $val !== '') ? $val : ($default ?? '');

        // Save to cache
        cache()->save($cacheKey, $finalVal, self::CACHE_TTL);

        return $finalVal;
    }

    /**
     * Ambil data detail setting lengkap beserta riwayat updated_by & updated_at
     */
    public function getDetail(string $key): ?array
    {
        return $this->repository->getDetailByKey($key);
    }

    /**
     * Simpan / Perbarui nilai setting dan bersihkan cache secara otomatis
     */
    public function set(string $key, string $value, ?string $updatedBy = null): bool
    {
        $res = $this->repository->set($key, $value, $updatedBy);
        if ($res) {
            // Invalidate Cache
            $cacheKey = self::CACHE_PREFIX . $key;
            cache()->delete($cacheKey);
        }
        return $res;
    }

    /**
     * Simpan multiple settings sekaligus
     */
    public function setMany(array $settings, ?string $updatedBy = null): bool
    {
        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->set($key, (string)$value, $updatedBy)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Ambil seluruh setting sebagai associative array
     */
    public function getAll(): array
    {
        return $this->repository->getAllSettings();
    }

    /**
     * Ambil seluruh riwayat audit setting
     */
    public function getAuditHistory(): array
    {
        return $this->repository->getAllSettingRows();
    }
}
