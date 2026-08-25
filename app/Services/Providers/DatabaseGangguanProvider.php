<?php

namespace App\Services\Providers;

use App\Contracts\GangguanDataProviderInterface;
use CodeIgniter\Database\BaseConnection;

/**
 * Database Gangguan Provider
 *
 * Primary provider for reading canonical historical interruption records from
 * the `historical_feeder_interruptions` database table with efficient filtering,
 * aggregation, and zero risk to the master asset layer.
 */
class DatabaseGangguanProvider implements GangguanDataProviderInterface
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return false;
        }

        $count = $this->db->table('historical_feeder_interruptions')->countAllResults();
        return $count > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceIdentifier(): string
    {
        return 'DATABASE_HISTORICAL_FEEDER_INTERRUPTIONS';
    }

    /**
     * {@inheritdoc}
     */
    public function getInterruptions(array $filters = []): array
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return [];
        }

        $builder = $this->db->table('historical_feeder_interruptions');

        if (!empty($filters['feeder_name'])) {
            $builder->like('feeder_name', trim((string)$filters['feeder_name']));
        }

        if (!empty($filters['category'])) {
            $builder->where('interruption_category', strtoupper(trim((string)$filters['category'])));
        }

        if (!empty($filters['cause_code'])) {
            $builder->where('cause_canonical_code', trim((string)$filters['cause_code']));
        }

        if (!empty($filters['date_from'])) {
            $builder->where('event_date >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $builder->where('event_date <=', $filters['date_to']);
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        return $builder->orderBy('event_date', 'DESC')
                       ->orderBy('id', 'DESC')
                       ->limit($limit, $offset)
                       ->get()
                       ->getResultArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getFeederInterruptions(int|string $penyulangIdentifier, array $filters = []): array
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return [];
        }

        $builder = $this->db->table('historical_feeder_interruptions');

        if (is_numeric($penyulangIdentifier)) {
            // Find feeder name from penyulang table if numeric ID provided
            $feederRow = $this->db->table('penyulang')
                                  ->select('nama_penyulang')
                                  ->where('id', (int)$penyulangIdentifier)
                                  ->get()
                                  ->getRowArray();

            $feederName = $feederRow['nama_penyulang'] ?? null;
            if ($feederName) {
                $builder->like('feeder_name', trim($feederName));
            } else {
                return [];
            }
        } else {
            $builder->like('feeder_name', trim((string)$penyulangIdentifier));
        }

        if (!empty($filters['category'])) {
            $builder->where('interruption_category', strtoupper(trim((string)$filters['category'])));
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;

        return $builder->orderBy('event_date', 'DESC')
                       ->orderBy('id', 'DESC')
                       ->limit($limit)
                       ->get()
                       ->getResultArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getInterruptionStats(array $filters = []): array
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return [
                'total_events'        => 0,
                'permanent_count'     => 0,
                'temporary_count'     => 0,
                'median_duration_min' => 0.0,
                'total_ens_kwh'       => 0.0,
                'dominant_causes'     => [],
            ];
        }

        $builder = $this->db->table('historical_feeder_interruptions');

        if (!empty($filters['feeder_name'])) {
            $builder->like('feeder_name', trim((string)$filters['feeder_name']));
        }

        $records = $builder->select('interruption_category, outage_duration_minutes, energy_not_supplied_kwh, cause_canonical_code')
                           ->get()
                           ->getResultArray();

        $totalEvents = count($records);
        $permanentCount = 0;
        $temporaryCount = 0;
        $durations = [];
        $totalEns = 0.0;
        $causeCounts = [];

        foreach ($records as $r) {
            $cat = strtoupper((string)($r['interruption_category'] ?? ''));
            if ($cat === 'PERMANENT') {
                $permanentCount++;
            } else {
                $temporaryCount++;
            }

            $dur = (float)($r['outage_duration_minutes'] ?? 0);
            if ($dur > 0) {
                $durations[] = $dur;
            }

            $totalEns += (float)($r['energy_not_supplied_kwh'] ?? 0);

            $cause = trim((string)($r['cause_canonical_code'] ?? 'UNKNOWN'));
            if ($cause !== '') {
                $causeCounts[$cause] = ($causeCounts[$cause] ?? 0) + 1;
            }
        }

        sort($durations);
        $countDur = count($durations);
        $medianDuration = 0.0;
        if ($countDur > 0) {
            $middle = (int)floor($countDur / 2);
            $medianDuration = ($countDur % 2 === 0)
                ? round(($durations[$middle - 1] + $durations[$middle]) / 2, 1)
                : round($durations[$middle], 1);
        }

        arsort($causeCounts);
        $topCauses = array_slice($causeCounts, 0, 5, true);

        return [
            'total_events'        => $totalEvents,
            'permanent_count'     => $permanentCount,
            'temporary_count'     => $temporaryCount,
            'median_duration_min' => $medianDuration,
            'total_ens_kwh'       => round($totalEns, 2),
            'dominant_causes'     => $topCauses,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        $count = $this->db->tableExists('historical_feeder_interruptions')
            ? $this->db->table('historical_feeder_interruptions')->countAllResults()
            : 0;

        $lastRow = $this->db->tableExists('historical_feeder_interruptions') && $count > 0
            ? $this->db->table('historical_feeder_interruptions')->select('updated_at, created_at')->orderBy('id', 'DESC')->get()->getRowArray()
            : null;

        $lastSynced = $lastRow['updated_at'] ?? $lastRow['created_at'] ?? null;

        return [
            'provider_class'     => self::class,
            'source_system'      => 'MYSQL_HISTORICAL_FEEDER_INTERRUPTIONS',
            'is_available'       => ($count > 0),
            'record_count'       => $count,
            'last_synced_at'     => $lastSynced,
            'status_description' => $count > 0 ? "Active database source with {$count} interruption records." : "Database table available but currently empty.",
        ];
    }
}
