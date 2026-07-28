<?php

namespace App\Services\VoiceAI\Providers;

use App\Services\VoiceAI\Contracts\SearchProviderInterface;
use Config\Database;

class SystemSearchProvider implements SearchProviderInterface
{
    public function searchSystemData(string $query, array $filters = [], int $limit = 5): array
    {
        $db = Database::connect();
        $results = [];

        $queryClean = trim($query);
        if (empty($queryClean)) {
            return [];
        }

        // Search Temuan
        $builder = $db->table('temuan')
            ->select('temuan.id, temuan.nomor_temuan, temuan.jenis_temuan, temuan.detail_temuan, temuan.alamat, temuan.status, temuan.prioritas, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = temuan.ulp_id', 'left')
            ->groupStart()
                ->like('temuan.nomor_temuan', $queryClean)
                ->orLike('temuan.detail_temuan', $queryClean)
                ->orLike('temuan.alamat', $queryClean)
                ->orLike('ulps.nama_ulp', $queryClean)
            ->groupEnd();

        if (!empty($filters['ulp_id'])) {
            $builder->where('temuan.ulp_id', $filters['ulp_id']);
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
                'title'       => "Temuan {$r['nomor_temuan']} ({$r['jenis_temuan']})",
                'description' => "Status {$r['status']} [Prioritas {$r['prioritas']}]. {$r['detail_temuan']} di {$r['alamat']}",
                'ulp'         => $r['nama_ulp'] ?? '-'
            ];
        }

        return $results;
    }

    public function searchKnowledgeBase(string $query): array
    {
        return [
            [
                'topic'   => 'SLA Penanganan Temuan',
                'snippet' => 'Prioritas UTAMA SLA 3 Hari, HIGH 7 Hari, MEDIUM 14 Hari, LOW 30 Hari.'
            ],
            [
                'topic'   => 'Jenis Temuan',
                'snippet' => 'Jenis temuan terbagi 3 kategori: KONSTRUKSI (har gardu/konstruksi), HOTSPOT (pdkb/gardu), dan ROW (perantingan/pohon).'
            ]
        ];
    }

    public function getProviderName(): string
    {
        return 'system';
    }
}
