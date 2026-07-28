<?php

namespace App\Controllers\Api;

use App\Repositories\TemuanRepository;

class AiApiController extends BaseApiController
{
    private TemuanRepository $temuanRepository;

    public function __construct()
    {
        $this->temuanRepository = new TemuanRepository();
    }

    /**
     * GET /api/v1/ai/dataset
     * Export dataset berstruktur untuk Machine Learning / AI Model Training
     */
    public function dataset()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('temuan t')
            ->select('t.id, t.nomor_temuan, t.ulp_id, u.nama_ulp, t.penyulang_id, p.nama_penyulang, t.section_id, s.nama_section,
                t.jenis_temuan, t.pelaksana, t.prioritas, t.potensi_gangguan, t.latitude, t.longitude,
                t.status, t.tanggal_temuan, t.tanggal_selesai, t.created_at, t.updated_at, t.foto, t.foto_path')
            ->join('ulps u', 'u.id = t.ulp_id', 'left')
            ->join('penyulang p', 'p.id = t.penyulang_id', 'left')
            ->join('sections s', 's.id = t.section_id', 'left')
            ->where('t.deleted_at IS NULL')
            ->orderBy('t.id', 'ASC');

        $rows = $builder->get()->getResultArray();
        $mlFeatures = [];

        foreach ($rows as $r) {
            // Calculated ML Target Features
            $slaData = get_sla_status($r['prioritas'] ?? 'MEDIUM', $r['tanggal_temuan'] ?? date('Y-m-d'), $r['status'] ?? 'BELUM', $r['tanggal_selesai']);
            
            $start = new \DateTime($r['tanggal_temuan']);
            $end = !empty($r['tanggal_selesai']) ? new \DateTime($r['tanggal_selesai']) : new \DateTime();
            $resolutionDays = (float)$start->diff($end)->format('%r%a');

            $photos = json_decode($r['foto'] ?? '', true) ?: [];
            if (is_string($r['foto'] ?? null) && empty($photos) && !empty($r['foto'])) {
                $photos = [$r['foto']];
            }

            // Structured Photo Metadata
            $photoMetadata = [];
            foreach ($photos as $idx => $photoName) {
                $photoMetadata[] = [
                    'index'     => $idx + 1,
                    'filename'  => $photoName,
                    'url'       => get_photo_url($photoName, $r['foto_path'] ?? 'foto/'),
                    'path'      => ($r['foto_path'] ?? 'foto/') . $photoName,
                ];
            }

            $mlFeatures[] = [
                'id'                     => (int)$r['id'],
                'nomor_temuan'           => $r['nomor_temuan'],
                'ulp' => [
                    'id'   => (int)$r['ulp_id'],
                    'nama' => $r['nama_ulp'] ?? 'N/A'
                ],
                'penyulang' => [
                    'id'   => (int)$r['penyulang_id'],
                    'nama' => $r['nama_penyulang'] ?? 'N/A'
                ],
                'section' => [
                    'id'   => (int)$r['section_id'],
                    'nama' => $r['nama_section'] ?? 'N/A'
                ],
                'features' => [
                    'jenis_temuan'     => $r['jenis_temuan'],
                    'pelaksana'        => $r['pelaksana'],
                    'prioritas'        => $r['prioritas'],
                    'potensi_gangguan' => $r['potensi_gangguan'],
                    'status_enum'      => $r['status'], // Enum: BELUM, BUTUH PADAM, SELESAI
                    'location' => [
                        'latitude'  => $r['latitude'] !== null ? (float)$r['latitude'] : null,
                        'longitude' => $r['longitude'] !== null ? (float)$r['longitude'] : null,
                        'has_coordinates' => ($r['latitude'] !== null && $r['longitude'] !== null)
                    ]
                ],
                'sla_analytics' => [
                    'sla_is_overdue'         => (bool)$slaData['is_overdue'],
                    'resolution_days'        => max(0, $resolutionDays),
                    'deadline'               => $slaData['deadline']
                ],
                'photos_metadata' => [
                    'total_photos' => count($photos),
                    'items'        => $photoMetadata
                ],
                'timestamps' => [
                    'tanggal_temuan'  => $r['tanggal_temuan'],
                    'tanggal_selesai' => $r['tanggal_selesai'],
                    'created_at'      => $r['created_at'],
                    'updated_at'      => $r['updated_at']
                ]
            ];
        }

        return $this->respondSuccess([
            'dataset_info' => [
                'total_samples' => count($mlFeatures),
                'generated_at'  => date('Y-m-d H:i:s'),
                'target_labels' => ['is_sla_overdue', 'resolution_days', 'status_enum']
            ],
            'samples' => $mlFeatures
        ], 'Dataset Machine Learning SIDAK TEJO berhasil diekspor.');
    }

    /**
     * GET /api/v1/ai/summary
     * Ringkasan performa dan statistik untuk analisis Machine Learning
     */
    public function summary()
    {
        $db = \Config\Database::connect();
        
        $totalTemuan = $db->table('temuan')->where('deleted_at IS NULL')->countAllResults();
        $totalSelesai = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'SELESAI')->countAllResults();
        $totalBelum = $db->table('temuan')->where('deleted_at IS NULL')->where('status', 'BELUM')->countAllResults();

        $byJenis = $db->table('temuan')
            ->select('jenis_temuan, COUNT(*) as count')
            ->where('deleted_at IS NULL')
            ->groupBy('jenis_temuan')
            ->get()->getResultArray();

        $byPrioritas = $db->table('temuan')
            ->select('prioritas, COUNT(*) as count')
            ->where('deleted_at IS NULL')
            ->groupBy('prioritas')
            ->get()->getResultArray();

        return $this->respondSuccess([
            'overall' => [
                'total_records'    => $totalTemuan,
                'total_completed'  => $totalSelesai,
                'total_pending'    => $totalBelum,
                'completion_rate'  => $totalTemuan > 0 ? round(($totalSelesai / $totalTemuan) * 100, 2) . '%' : '0%'
            ],
            'distribution_by_jenis'     => $byJenis,
            'distribution_by_prioritas' => $byPrioritas
        ], 'Ringkasan analitik AI SIDAK TEJO.');
    }
}
