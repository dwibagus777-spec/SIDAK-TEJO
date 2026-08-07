<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class AssetStatus extends BaseConfig
{
    public const NORMAL              = 'NORMAL';
    public const BERMASALAH          = 'BERMASALAH';
    public const MAINTENANCE         = 'MAINTENANCE';
    public const MENUNGGU_VERIFIKASI = 'MENUNGGU_VERIFIKASI';
    public const RETIRED             = 'RETIRED';
    public const SCRAP               = 'SCRAP';
    public const NONAKTIF            = 'NONAKTIF';
    public const DIHAPUS             = 'DIHAPUS';

    /**
     * Get human-readable status labels
     */
    public static function getLabels(): array
    {
        return [
            self::NORMAL              => 'Normal (Baik)',
            self::BERMASALAH          => 'Bermasalah (Ada Temuan)',
            self::MAINTENANCE         => 'Dalam Perbaikan (WO Active)',
            self::MENUNGGU_VERIFIKASI => 'Menunggu Verifikasi Supervisor',
            self::RETIRED             => 'Di-Pensiunkan (Retired)',
            self::SCRAP               => 'Afkir (Scrap)',
            self::NONAKTIF            => 'Nonaktif',
            self::DIHAPUS             => 'Dihapus (Soft Delete)',
        ];
    }

    /**
     * Get Bootstrap Badge CSS classes for statuses
     */
    public static function getBadgeClasses(): array
    {
        return [
            self::NORMAL              => 'bg-success',
            self::BERMASALAH          => 'bg-danger text-white',
            self::MAINTENANCE         => 'bg-warning text-dark',
            self::MENUNGGU_VERIFIKASI => 'bg-info text-white',
            self::RETIRED             => 'bg-secondary',
            self::SCRAP               => 'bg-dark text-white',
            self::NONAKTIF            => 'bg-secondary',
            self::DIHAPUS             => 'bg-danger text-white',
        ];
    }
}
