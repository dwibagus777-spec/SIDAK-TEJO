<?php

namespace App\Services;

use Config\Database;
use RuntimeException;

class CaptiveNonPenugasanService
{
    public const CONDUCTORS = ['AAAC', 'AAACS', 'MVTIC', 'XLPE'];
    public const SIZES = ['70 mm²', '110 mm²', '150 mm²', '240 mm²'];

    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->ensureTableExists();
    }

    public function ensureTableExists(): void
    {
        try {
            if (!$this->db->tableExists('captive_non_penugasan')) {
                $migFile = APPPATH . 'Database/Migrations/2026-10-20-000001_AddTranslineToTemuan.php';
                if (file_exists($migFile)) {
                    require_once $migFile;
                    $mig = new \App\Database\Migrations\AddTranslineToTemuan();
                    $mig->up();
                }
            }
        } catch (\Throwable $e) {
            log_message('error', '[CaptiveNonPenugasanService] ensureTableExists: ' . $e->getMessage());
        }
    }

    public function list(?int $ulpId): array
    {
        if (!$this->db->tableExists('captive_non_penugasan')) {
            $this->ensureTableExists();
            if (!$this->db->tableExists('captive_non_penugasan')) {
                return [];
            }
        }
        $builder = $this->db->table('captive_non_penugasan c')
            ->select('c.*, u.nama_ulp, p.nama_penyulang')
            ->join('ulps u', 'u.id = c.ulp_id', 'left')
            ->join('penyulang p', 'p.id = c.penyulang_id', 'left')
            ->where('c.deleted_at IS NULL')
            ->orderBy('c.created_at', 'DESC');
        if ($ulpId) $builder->where('c.ulp_id', $ulpId);
        return $builder->get()->getResultArray();
    }

    public function create(array $input, int $userId, string $userName, ?int $userUlpId): int
    {
        $db = $this->db;
        $translineId = (int)($input['transline_id'] ?? 0);
        $kind = strtoupper(trim((string)($input['jenis'] ?? 'MANUAL')));
        if (!in_array($kind, ['COATING', 'MANUAL'], true)) throw new RuntimeException('Jenis catatan tidak valid.');
        $ulpId = (int)($input['ulp_id'] ?? 0);
        $penyulangId = (int)($input['penyulang_id'] ?? 0);
        $sectionId = (int)($input['section_id'] ?? 0) ?: null;
        $line = null;

        if ($kind === 'COATING') {
            if ($translineId <= 0) throw new RuntimeException('Pilih transline dari GIS.');
            $scope = (new GisTranslineService($db))->getAuthoritativeTranslines(['penyulang_id' => $penyulangId, 'ulp_id' => $ulpId], $userUlpId);
            if (empty($scope['success'])) throw new RuntimeException('Transline di luar cakupan akses Anda.');
            foreach (($scope['data'] ?? []) as $candidate) if ((int)$candidate['id'] === $translineId) { $line = $candidate; break; }
            if (!$line) throw new RuntimeException('Transline aktif tidak ditemukan pada ULP/penyulang tersebut.');
            $ulpId = (int)($scope['scope']['ulp_id'] ?? $ulpId);
            $penyulangId = (int)$line['penyulang_id'];
            $sectionId = (int)($line['section_id'] ?? $line['sa_section_id'] ?? 0) ?: null;
            $duplicate = $db->table('captive_non_penugasan')->where('transline_id', $translineId)->where('jenis', 'COATING')->where('status', 'POTENSI')->where('deleted_at IS NULL')->countAllResults();
            if ($duplicate > 0) throw new RuntimeException('Transline ini sudah tercatat sebagai potensi coating yang masih aktif.');
            $targetType = strtoupper(trim((string)($input['target_conductor_type'] ?? 'AAACS')));
            $targetSize = trim((string)($input['target_conductor_size'] ?? $line['conductor_size'] ?? '150 mm²'));
            if ($targetType !== 'AAACS' || !in_array($targetSize, self::SIZES, true)) throw new RuntimeException('Spesifikasi konduktor coating tidak valid; hasil coating harus AAACS.');
            $source = $line['source_asset'] ?? [];
            $target = $line['target_asset'] ?? [];
            $snapshot = [
                'ulp_id' => $ulpId, 'penyulang_id' => $penyulangId, 'section_id' => $sectionId,
                'source_asset_id' => (int)$line['source_asset_id'], 'target_asset_id' => (int)$line['target_asset_id'],
                'source_label' => trim(($source['kode_asset'] ?? '') . ' ' . ($source['nama_asset'] ?? '')),
                'target_label' => trim(($target['kode_asset'] ?? '') . ' ' . ($target['nama_asset'] ?? '')),
                'source_section_id' => (int)($line['source_section_id'] ?? 0) ?: null, 'target_section_id' => (int)($line['target_section_id'] ?? 0) ?: null,
                'source_latitude' => $source['latitude'] ?? null, 'source_longitude' => $source['longitude'] ?? null,
                'target_latitude' => $target['latitude'] ?? null, 'target_longitude' => $target['longitude'] ?? null,
                'latitude' => $source['latitude'] ?? null, 'longitude' => $source['longitude'] ?? null,
                'distance_meters' => (float)($line['distance_meters'] ?? 0),
                'conductor_type' => $line['conductor_type'] ?? null, 'conductor_size' => $line['conductor_size'] ?? null,
                'target_conductor_type' => $targetType, 'target_conductor_size' => $targetSize,
            ];
        } else {
            if ($ulpId <= 0) throw new RuntimeException('ULP wajib dipilih.');
            if ($userUlpId && $ulpId !== $userUlpId) throw new RuntimeException('ULP di luar cakupan akses Anda.');
            $lat = $this->coordinate($input['latitude'] ?? null, -90, 90, 'Latitude');
            $lng = $this->coordinate($input['longitude'] ?? null, -180, 180, 'Longitude');
            if ($lat === null || $lng === null || $lat == 0.0 || $lng == 0.0) throw new RuntimeException('Pilih atau masukkan koordinat lokasi temuan yang valid.');
            if ($penyulangId > 0) {
                $feeder = $db->table('penyulang')->select('ulp_id')->where('id', $penyulangId)->get()->getRowArray();
                if (!$feeder || (int)$feeder['ulp_id'] !== $ulpId) throw new RuntimeException('Penyulang tidak sesuai dengan ULP yang dipilih.');
                if ($sectionId) {
                    $section = $db->table('sections')->select('penyulang_id')->where('id', $sectionId)->get()->getRowArray();
                    if (!$section || (int)$section['penyulang_id'] !== $penyulangId) throw new RuntimeException('Section tidak sesuai dengan penyulang yang dipilih.');
                }
            } elseif ($sectionId) {
                throw new RuntimeException('Pilih penyulang sebelum memilih section.');
            }
            $snapshot = [
                'ulp_id' => $ulpId, 'penyulang_id' => $penyulangId ?: null, 'section_id' => $sectionId,
                'latitude' => $lat, 'longitude' => $lng,
                'nomor_gangguan' => trim((string)($input['nomor_gangguan'] ?? '')) ?: null,
                'detail_temuan' => trim((string)($input['detail_temuan'] ?? '')),
            ];
        }

        $detail = trim((string)($input['detail_temuan'] ?? ''));
        if ($detail === '') throw new RuntimeException('Detail temuan wajib diisi.');
        $db->table('captive_non_penugasan')->insert(array_merge($snapshot, [
            'nomor' => 'CAP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'transline_id' => $kind === 'COATING' ? $translineId : null,
            'jenis' => $kind,
            'status' => 'POTENSI',
            'nomor_gangguan' => $snapshot['nomor_gangguan'] ?? (trim((string)($input['nomor_gangguan'] ?? '')) ?: null),
            'detail_temuan' => $detail,
            'created_by' => $userId ?: null,
            'created_by_name' => $userName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
        return (int)$db->insertID();
    }

    public function complete(int $id, int $userId, ?int $userUlpId): void
    {
        $db = $this->db;
        $db->transBegin();
        try {
            $item = $db->table('captive_non_penugasan')->where('id', $id)->where('deleted_at IS NULL')->get()->getRowArray();
            if (!$item) throw new RuntimeException('Catatan tidak ditemukan.');
            if ($userUlpId && (int)$item['ulp_id'] !== $userUlpId) throw new RuntimeException('Catatan di luar cakupan akses Anda.');
            if ($item['status'] === 'SELESAI') { $db->transCommit(); return; }
            if ($item['jenis'] === 'COATING' && !empty($item['transline_id'])) {
                $line = $db->table('gis_translines')->where('id', $item['transline_id'])->where('is_active', 1)->where('status', 'ACTIVE')->where('deleted_at IS NULL')->get()->getRowArray();
                if (!$line || (int)$line['penyulang_id'] !== (int)$item['penyulang_id']) throw new RuntimeException('Transline tidak lagi aktif pada penyulang tercatat; status tidak diubah.');
                if (strtoupper((string)$line['conductor_type']) !== strtoupper((string)$item['conductor_type']) || (string)$line['conductor_size'] !== (string)$item['conductor_size']) throw new RuntimeException('Spesifikasi transline berubah sejak catatan dibuat; periksa ulang sebelum menyelesaikan.');
                $db->table('gis_translines')->where('id', $line['id'])->update([
                    'conductor_type' => $item['target_conductor_type'] ?: 'AAACS',
                    'conductor_size' => $item['target_conductor_size'] ?: $line['conductor_size'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            $db->table('captive_non_penugasan')->where('id', $id)->update(['status' => 'SELESAI', 'completed_by' => $userId, 'completed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            if ($db->transStatus() === false) throw new RuntimeException('Perubahan gagal disimpan.');
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    private function coordinate($value, float $min, float $max, string $label): ?float
    {
        if ($value === null || trim((string)$value) === '') return null;
        if (!is_numeric($value) || (float)$value < $min || (float)$value > $max) throw new RuntimeException("{$label} tidak valid.");
        return (float)$value;
    }
}
