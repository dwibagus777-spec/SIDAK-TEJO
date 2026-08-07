<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class AssetEvent extends BaseConfig
{
    public const CREATED          = 'CREATED';
    public const TEMUAN_CREATED   = 'TEMUAN_CREATED';
    public const TEMUAN_CLOSED    = 'TEMUAN_CLOSED';
    public const WO_CREATED       = 'WO_CREATED';
    public const WO_STARTED      = 'WO_STARTED';
    public const WO_COMPLETED    = 'WO_COMPLETED';
    public const INSPECTION_PASS  = 'INSPECTION_PASS';
    public const INSPECTION_FAIL  = 'INSPECTION_FAIL';
    public const EDIT             = 'EDIT';
    public const DELETE           = 'DELETE';
    public const RESTORE          = 'RESTORE';
    public const PHOTO_UPDATED    = 'PHOTO_UPDATED';
    public const QR_SCANNED       = 'QR_SCANNED';

    /**
     * Get human-readable event labels
     */
    public static function getLabels(): array
    {
        return [
            self::CREATED          => 'Aset Baru Didaftarkan',
            self::TEMUAN_CREATED   => 'Temuan Baru Dilaporkan',
            self::TEMUAN_CLOSED    => 'Temuan Diselesaikan',
            self::WO_CREATED       => 'Work Order Diterbitkan',
            self::WO_STARTED      => 'Pekerjaan HAR Dimulai',
            self::WO_COMPLETED    => 'Pekerjaan HAR Selesai',
            self::INSPECTION_PASS  => 'Inspeksi Supervisor LULUS',
            self::INSPECTION_FAIL  => 'Inspeksi Supervisor GAGAL',
            self::EDIT             => 'Spesifikasi / Data Aset Diubah',
            self::DELETE           => 'Aset Di-Soft Delete',
            self::RESTORE          => 'Aset Di-Restore (Dipulihkan)',
            self::PHOTO_UPDATED    => 'Foto Eviden/Aset Diperbarui',
            self::QR_SCANNED       => 'QR/Barcode Di-Scan Lapangan',
        ];
    }
}
