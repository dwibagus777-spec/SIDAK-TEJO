<?php

namespace App\Services\VoiceAI\Providers;

use App\Services\VoiceAI\Contracts\SearchProviderInterface;
use Config\Database;

class SystemSearchProvider implements SearchProviderInterface
{
    public function searchSystemData(string $query, array $filters = [], int $limit = 10): array
    {
        $db = Database::connect();
        $results = [];
        $queryClean = trim($query);
        if (empty($queryClean)) {
            return [];
        }

        // Global Database Multi-Table Multi-Column Search Engine
        $builder = $db->table('temuan')
            ->select('temuan.id, temuan.nomor_temuan, temuan.jenis_temuan, temuan.pelaksana, temuan.prioritas, temuan.status, temuan.detail_temuan, temuan.alamat, temuan.foto, temuan.created_at, ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section')
            ->join('ulps', 'ulps.id = temuan.ulp_id', 'left')
            ->join('penyulang', 'penyulang.id = temuan.penyulang_id', 'left')
            ->join('sections', 'sections.id = temuan.section_id', 'left')
            ->where('temuan.deleted_at IS NULL');

        // Extract search terms (support multi-word OR matching)
        $terms = array_filter(explode(' ', $queryClean), fn($t) => strlen($t) > 2);

        $builder->groupStart();
            $builder->like('temuan.nomor_temuan', $queryClean)
                ->orLike('temuan.detail_temuan', $queryClean)
                ->orLike('temuan.alamat', $queryClean)
                ->orLike('temuan.jenis_temuan', $queryClean)
                ->orLike('temuan.pelaksana', $queryClean)
                ->orLike('temuan.prioritas', $queryClean)
                ->orLike('temuan.status', $queryClean)
                ->orLike('ulps.nama_ulp', $queryClean)
                ->orLike('penyulang.nama_penyulang', $queryClean)
                ->orLike('sections.nama_section', $queryClean);

            foreach ($terms as $term) {
                $builder->orLike('temuan.detail_temuan', $term)
                        ->orLike('temuan.nomor_temuan', $term)
                        ->orLike('penyulang.nama_penyulang', $term)
                        ->orLike('ulps.nama_ulp', $term);
            }
        $builder->groupEnd();

        if (!empty($filters['ulp_id'])) {
            $builder->where('temuan.ulp_id', (int)$filters['ulp_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('temuan.status', strtoupper($filters['status']));
        }
        if (!empty($filters['prioritas'])) {
            $builder->where('temuan.prioritas', strtoupper($filters['prioritas']));
        }

        $temuanRows = $builder->orderBy('temuan.id', 'DESC')->limit($limit)->get()->getResultArray();
        foreach ($temuanRows as $r) {
            $results[] = [
                'type'        => 'TEMUAN',
                'id'          => $r['id'],
                'nomor'       => $r['nomor_temuan'],
                'title'       => "Temuan {$r['nomor_temuan']} ({$r['jenis_temuan']})",
                'description' => "Status {$r['status']} [Prioritas {$r['prioritas']}]. Pelaksana {$r['pelaksana']}. {$r['detail_temuan']} di {$r['alamat']}",
                'ulp'         => $r['nama_ulp'] ?? '-',
                'penyulang'   => $r['nama_penyulang'] ?? '-',
                'section'     => $r['nama_section'] ?? '-',
                'url'         => site_url('temuan/detail/' . $r['id'])
            ];
        }

        // Also search Eviden Kubikel & Trafo tables if exists
        try {
            if ($db->tableExists('eviden_kubikel')) {
                $kubikelRows = $db->table('eviden_kubikel')
                    ->select('id, nama_kubikel, lokasi, kondisi')
                    ->groupStart()
                        ->like('nama_kubikel', $queryClean)
                        ->orLike('lokasi', $queryClean)
                        ->orLike('kondisi', $queryClean)
                    ->groupEnd()
                    ->limit(3)
                    ->get()
                    ->getResultArray();

                foreach ($kubikelRows as $kr) {
                    $results[] = [
                        'type'        => 'EVIDEN_KUBIKEL',
                        'id'          => $kr['id'],
                        'nomor'       => 'KUBIKEL-' . $kr['id'],
                        'title'       => "Eviden Kubikel: {$kr['nama_kubikel']}",
                        'description' => "Lokasi: {$kr['lokasi']}. Kondisi: {$kr['kondisi']}",
                        'url'         => site_url('eviden')
                    ];
                }
            }
        } catch (\Throwable $e) {
            log_message('debug', 'Eviden kubikel search skip: ' . $e->getMessage());
        }

        return $results;
    }

    public function searchKnowledgeBase(string $query): array
    {
        return [
            [
                'topic'   => 'SLA Penanganan Temuan SIDAK TEJO',
                'snippet' => 'Standard SLA: EMERGENCY (24 Jam / 1 Hari), HIGH (3 Hari), MEDIUM (7 Hari), LOW (14 Hari).'
            ],
            [
                'topic'   => 'Jenis & Pelaksana Inspeksi',
                'snippet' => 'Jenis temuan terbagi atas KONSTRUKSI, HOTSPOT, ROW, dan GARDU dengan pelaksana YANTEK, PDKB, HAR GARDU, HAR KONSTRUKSI, HAR ROW, HAR CRANE, dan INSPEKSI.'
            ]
        ];
    }

    public function getProviderName(): string
    {
        return 'system_global';
    }
}
