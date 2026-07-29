<?php

namespace App\Repositories;

use App\Models\SystemSettingModel;

class SystemSettingRepository
{
    private SystemSettingModel $model;

    public function __construct()
    {
        $this->model = new SystemSettingModel();
    }

    /**
     * Ambil nilai setting berdasarkan key
     */
    public function getByKey(string $key, ?string $default = null): ?string
    {
        $setting = $this->model->where('setting_key', $key)->first();
        if ($setting && isset($setting['setting_value'])) {
            return $setting['setting_value'];
        }
        return $default;
    }

    /**
     * Ambil detail setting lengkap (termasuk updated_by & updated_at)
     */
    public function getDetailByKey(string $key): ?array
    {
        return $this->model->where('setting_key', $key)->first() ?: null;
    }

    /**
     * Ambil seluruh data setting sebagai associative array [key => value]
     */
    public function getAllSettings(): array
    {
        $rows = $this->model->findAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    /**
     * Ambil seluruh baris setting lengkap untuk tabel riwayat & audit
     */
    public function getAllSettingRows(): array
    {
        return $this->model->orderBy('updated_at', 'DESC')->findAll();
    }

    /**
     * Simpan atau perbarui nilai setting
     */
    public function set(string $key, string $value, ?string $updatedBy = null): bool
    {
        $existing = $this->model->where('setting_key', $key)->first();
        $data = [
            'setting_value' => $value,
            'updated_by'    => $updatedBy ?: 'Administrator',
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            return (bool)$this->model->update($existing['id'], $data);
        } else {
            $data['setting_key'] = $key;
            return (bool)$this->model->insert($data);
        }
    }
}
