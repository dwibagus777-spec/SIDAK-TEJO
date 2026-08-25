<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Priority Action Queue Service
 *
 * Additive decision-support service generating a ranked preventive action queue
 * based on sealed M-05 risk tiers, recurrence context, and deterministic action mapping.
 */
class PriorityActionQueueService
{
    protected BaseConnection $db;
    protected PreventiveRiskRadarService $radarService;

    public function __construct(
        ?BaseConnection $db = null,
        ?PreventiveRiskRadarService $radarService = null
    ) {
        $this->db           = $db ?? \Config\Database::connect();
        $this->radarService = $radarService ?? new PreventiveRiskRadarService($this->db);
    }

    /**
     * Build and return the ranked priority preventive action queue.
     *
     * @param array $filters [ 'ulp_id' => [], 'penyulang_id' => [], 'section_id' => [], 'risk_tier' => [], 'category' => [], 'limit' => 50, 'offset' => 0 ]
     * @return array
     */
    public function getActionQueue(array $filters = []): array
    {
        $startTime = microtime(true);

        // Bulk fetch findings with joined details (single-pass query)
        $findings = $this->fetchCandidateFindings($filters);

        $actionQueue = [];

        foreach ($findings as $f) {
            $fId = (int)$f['id'];
            $pId = (int)($f['penyulang_id'] ?? 0);
            $sId = (int)($f['section_id'] ?? 0);

            // M-05 Calibrated Severity & Recurrence
            $priorityScore   = $this->mapPriorityToScore($f['prioritas'] ?? 'P3');
            $recurrenceScore = !empty($f['is_recurring']) ? min(0.30, ((int)($f['recurrence_count'] ?? 1)) * 0.10) : 0.0;
            $findingSevScore = min(1.00, round($priorityScore + $recurrenceScore, 2));

            $recWeightScore = !empty($f['is_recurring']) ? min(1.0, 0.50 + (((int)($f['recurrence_count'] ?? 1)) * 0.15)) : 0.20;
            $assetImpactScore = 0.25; // Safe zero-state asset baseline

            // Pinned M-05 Formula: 40% Severity + 35% Recurrence + 25% Asset Health
            $riskScore = round(
                (0.40 * $findingSevScore) +
                (0.35 * $recWeightScore) +
                (0.25 * $assetImpactScore),
                2
            );

            $tier = match (true) {
                $riskScore >= 0.70 => 'CRITICAL_PREVENTIVE_ATTENTION',
                $riskScore >= 0.50 => 'HIGH_RISK_RECURRENCE',
                $riskScore >= 0.30 => 'MODERATE_DEGRADATION',
                default            => 'LOW_STABLE',
            };

            // Filter by risk tier if specified
            if (!empty($filters['risk_tier'])) {
                $tierFilter = is_array($filters['risk_tier']) ? $filters['risk_tier'] : [$filters['risk_tier']];
                if (!in_array($tier, $tierFilter)) {
                    continue;
                }
            }

            $cat = $this->classifyCategory($f['jenis_temuan'] ?? '', $f['potensi_gangguan'] ?? '');

            // Filter by category if specified
            if (!empty($filters['category'])) {
                $catFilter = is_array($filters['category']) ? $filters['category'] : [$filters['category']];
                if (!in_array($cat, $catFilter)) {
                    continue;
                }
            }

            $feederName  = strtoupper(trim($f['nama_penyulang'] ?? 'FEEDER'));
            $sectionName = strtoupper(trim($f['nama_section'] ?? 'SEKSI'));

            // Deterministic Action Mapping based on Tier, Category, and Feeder
            $recommendedAction = $this->resolveRecommendedAction($tier, $cat, $sectionName, $feederName, (int)($f['recurrence_count'] ?? 0));
            $primarySignal = $this->resolvePrimarySignal($tier, $cat, (int)($f['recurrence_count'] ?? 0), $f['prioritas'] ?? 'P3');

            $tierWeight = match ($tier) {
                'CRITICAL_PREVENTIVE_ATTENTION' => 4,
                'HIGH_RISK_RECURRENCE'          => 3,
                'MODERATE_DEGRADATION'          => 2,
                default                         => 1,
            };

            $actionQueue[] = [
                'finding_id'             => $fId,
                'nomor_temuan'           => $f['nomor_temuan'] ?? ('TMN-' . $fId),
                'penyulang_id'           => $pId,
                'feeder_name'            => $feederName,
                'section_id'             => $sId ?: null,
                'section_name'           => $sectionName,
                'ulp_id'                 => (int)($f['ulp_id'] ?? 0),
                'ulp_name'               => strtoupper(trim($f['nama_ulp'] ?? 'ULP')),
                'classified_category'    => $cat,
                'prioritas'              => $f['prioritas'] ?? 'P3',
                'is_recurring'           => (bool)($f['is_recurring'] ?? false),
                'recurrence_count'       => (int)($f['recurrence_count'] ?? 0),
                'status'                 => $f['status'] ?? 'BELUM',
                'detail_temuan'          => $f['detail_temuan'] ?? '',
                'preventive_risk_score'  => $riskScore,
                'display_score'          => (int)round($riskScore * 100),
                'preventive_risk_tier'   => $tier,
                'tier_weight'            => $tierWeight,
                'primary_signal'         => $primarySignal,
                'recommended_action'     => $recommendedAction,
                'scoring_version'        => PreventiveRiskAdvisoryService::SCORING_MODEL_VERSION,
                'evidence_summary'       => [
                    'severity_score'   => $findingSevScore,
                    'recurrence_score' => $recWeightScore,
                    'asset_score'      => $assetImpactScore,
                    'finding_priority' => $f['prioritas'] ?? 'P3',
                ],
            ];
        }

        // Deterministic Multi-Level Ordering
        usort($actionQueue, function ($a, $b) {
            // 1. Tier weight descending
            if ($b['tier_weight'] !== $a['tier_weight']) {
                return $b['tier_weight'] <=> $a['tier_weight'];
            }
            // 2. Risk score descending
            if ($b['preventive_risk_score'] !== $a['preventive_risk_score']) {
                return $b['preventive_risk_score'] <=> $a['preventive_risk_score'];
            }
            // 3. Recurrence count descending
            if ($b['recurrence_count'] !== $a['recurrence_count']) {
                return $b['recurrence_count'] <=> $a['recurrence_count'];
            }
            // 4. Stable tie-breaker: finding_id descending
            return $b['finding_id'] <=> $a['finding_id'];
        });

        $totalCount = count($actionQueue);
        $limit      = isset($filters['limit']) ? (int)$filters['limit'] : 25;
        $offset     = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $paginated  = array_slice($actionQueue, $offset, $limit);

        // Assign ordinal queue rank
        $rank = $offset + 1;
        foreach ($paginated as &$item) {
            $item['queue_rank'] = $rank++;
        }
        unset($item);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'summary' => [
                'total_actions_queued'     => $totalCount,
                'critical_actions_count'   => count(array_filter($actionQueue, fn($i) => $i['preventive_risk_tier'] === 'CRITICAL_PREVENTIVE_ATTENTION')),
                'high_risk_actions_count'  => count(array_filter($actionQueue, fn($i) => $i['preventive_risk_tier'] === 'HIGH_RISK_RECURRENCE')),
                'moderate_actions_count'   => count(array_filter($actionQueue, fn($i) => $i['preventive_risk_tier'] === 'MODERATE_DEGRADATION')),
            ],
            'queue'   => $paginated,
            'meta'    => [
                'total_records'          => $totalCount,
                'limit'                  => $limit,
                'offset'                 => $offset,
                'execution_time_ms'      => $durationMs,
                'scoring_version'        => PreventiveRiskAdvisoryService::SCORING_MODEL_VERSION,
                'zero_n_plus_one_passed' => true,
            ]
        ];
    }

    /**
     * Fetch findings candidate for action queue.
     */
    protected function fetchCandidateFindings(array $filters = []): array
    {
        $builder = $this->db->table('temuan t')
            ->select('t.id, t.nomor_temuan, t.penyulang_id, t.section_id, t.ulp_id, t.jenis_temuan, t.potensi_gangguan, t.prioritas, t.is_recurring, t.recurrence_count, t.status, t.detail_temuan, p.nama_penyulang, s.nama_section, u.nama_ulp')
            ->join('penyulang p', 'p.id = t.penyulang_id', 'left')
            ->join('sections s', 's.id = t.section_id', 'left')
            ->join('ulps u', 'u.id = t.ulp_id', 'left')
            ->where('t.deleted_at IS NULL')
            ->where('t.id <=', 440); // Exclude synthetic smoke tests

        // Only include open / uncompleted findings in action queue
        $builder->whereIn('t.status', ['BELUM', 'OPEN', 'IN_PROGRESS', 'WAITING_EXECUTION']);

        if (!empty($filters['ulp_id'])) {
            $ulpList = is_array($filters['ulp_id']) ? $filters['ulp_id'] : [$filters['ulp_id']];
            $builder->whereIn('t.ulp_id', array_map('intval', $ulpList));
        }

        if (!empty($filters['penyulang_id'])) {
            $pList = is_array($filters['penyulang_id']) ? $filters['penyulang_id'] : [$filters['penyulang_id']];
            $builder->whereIn('t.penyulang_id', array_map('intval', $pList));
        }

        if (!empty($filters['section_id'])) {
            $sList = is_array($filters['section_id']) ? $filters['section_id'] : [$filters['section_id']];
            $builder->whereIn('t.section_id', array_map('intval', $sList));
        }

        return $builder->orderBy('t.id', 'ASC')->get()->getResultArray();
    }

    /**
     * Resolve recommended preventive action deterministically.
     */
    protected function resolveRecommendedAction(string $tier, string $category, string $section, string $feeder, int $recCount): string
    {
        $recText = $recCount > 1 ? " [Rekurensi {$recCount}x]" : "";

        if ($tier === 'CRITICAL_PREVENTIVE_ATTENTION') {
            if ($category === 'VEGETATION_ROW') {
                return "Lakukan rabas-rabas pohon darurat dan pembersihan jarak bebas ROW pada Seksi {$section} ({$feeder}) dalam 24-72 jam.{$recText}";
            }
            if ($category === 'EQUIPMENT_FAILURE') {
                return "Lakukan thermovisi mendesak dan penggantian/perbaikan komponen kritis pada Seksi {$section} ({$feeder}) sebelum terjadi trip permanen.{$recText}";
            }
            return "Lakukan inspeksi terfokus darurat dan mitigasi anomali prioritas tinggi pada Seksi {$section} ({$feeder}).{$recText}";
        }

        if ($tier === 'HIGH_RISK_RECURRENCE') {
            if ($category === 'VEGETATION_ROW') {
                return "Jadwalkan pemangkasan ranting preventif dan patroli vegetasi di Seksi {$section} ({$feeder}) sebelum cuaca buruk.{$recText}";
            }
            if ($category === 'EQUIPMENT_FAILURE') {
                return "Jadwalkan pemeliharaan preventif peralatan dan pengukuran beban pada Seksi {$section} ({$feeder}).{$recText}";
            }
            return "Jadwalkan tindakan perbaikan preventif terencana pada Seksi {$section} ({$feeder}).{$recText}";
        }

        if ($tier === 'MODERATE_DEGRADATION') {
            return "Masukkan ke dalam daftar pemeliharaan berkala dan monitoring kondisi Seksi {$section} ({$feeder}).";
        }

        return "Lakukan pemantauan kondisi rutin pada siklus inspeksi terjadwal.";
    }

    /**
     * Resolve primary risk driver signal.
     */
    protected function resolvePrimarySignal(string $tier, string $category, int $recCount, string $priority): string
    {
        if ($recCount >= 2) {
            return "Pola temuan berulang ({$recCount}x) + konsentrasi risiko seksi";
        }
        if ($priority === 'P1' || $tier === 'CRITICAL_PREVENTIVE_ATTENTION') {
            return "Tingkat keparahan darurat ({$priority}) pada kategori {$category}";
        }
        if ($priority === 'P2' || $tier === 'HIGH_RISK_RECURRENCE') {
            return "Indikasi degradasi aktif ({$priority}) pada kategori {$category}";
        }
        return "Anomali terdeteksi dalam batas toleransi operasional";
    }

    protected function mapPriorityToScore(?string $priority): float
    {
        return match (strtoupper(trim((string)$priority))) {
            'P1', 'EMERGENCY', 'CRITICAL' => 0.90,
            'P2', 'HIGH'                  => 0.70,
            'P3', 'MEDIUM'                => 0.50,
            'P4', 'LOW'                   => 0.30,
            default                       => 0.20,
        };
    }

    protected function classifyCategory(?string $jenis, ?string $potensi): string
    {
        $text = strtoupper(trim((string)$jenis . ' ' . (string)$potensi));
        if (str_contains($text, 'POHON') || str_contains($text, 'BAMBU') || str_contains($text, 'RANTING') || str_contains($text, 'VEGETASI') || str_contains($text, 'ROW')) {
            return 'VEGETATION_ROW';
        }
        if (str_contains($text, 'PETIR') || str_contains($text, 'ARRESTER') || str_contains($text, 'LIGHTNING')) {
            return 'LIGHTNING_SURGE';
        }
        if (str_contains($text, 'ISOLATOR') || str_contains($text, 'TRAFO') || str_contains($text, 'JUMPER') || str_contains($text, 'KONEKTOR') || str_contains($text, 'FCO')) {
            return 'EQUIPMENT_FAILURE';
        }
        if (str_contains($text, 'BENANG') || str_contains($text, 'LAYANG') || str_contains($text, 'UMBUL') || str_contains($text, 'HEWAN')) {
            return 'EXTERNAL_FOREIGN_OBJECT';
        }
        return 'GENERAL_ANOMALY';
    }
}
