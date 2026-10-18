<?php

namespace App\Models;

use CodeIgniter\Model;

class InterruptionModel extends Model
{
    protected $table = 'historical_feeder_interruptions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'batch_id',
        'source_system',
        'source_sheet_name',
        'source_row_number',
        'source_record_hash',
        'raw_payload_json',
        'event_date',
        'interruption_started_at',
        'interruption_ended_at',
        'outage_duration_minutes',
        'substation_name',
        'ulp_name',
        'ulp_id',
        'feeder_name',
        'penyulang_id',
        'switching_device_type',
        'device_name',
        'relay_trip_type',
        'faulted_phase',
        'weather_condition',
        'fault_current_amperes',
        'energy_not_supplied_kwh',
        'interruption_category',
        'interruption_group',
        'cause_raw',
        'cause_canonical_code',
        'cause_category',
        'cause_mapping_status',
        'field_narrative_raw',
        'restoration_action_raw',
        'extracted_zone_section',
        'extracted_distance_kms',
        'asset_reference_raw',
        'asset_id',
        'section_id',
        'data_quality_score',
        'ingestion_status',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get distinct years available in interruption history
     */
    public function getAvailableYears(): array
    {
        $rows = $this->select("DISTINCT YEAR(event_date) as yr")
            ->where('event_date IS NOT NULL')
            ->orderBy('yr', 'DESC')
            ->findAll();

        return array_values(array_filter(array_map(fn($r) => (int)$r['yr'], $rows)));
    }

    /**
     * Get the latest (max) year with data
     */
    public function getLatestYear(): ?int
    {
        $db = \Config\Database::connect();
        $row = $db->table($this->table)
            ->selectMax('YEAR(event_date)', 'max_yr')
            ->where('event_date IS NOT NULL')
            ->get()->getRowArray();
        return $row && $row['max_yr'] ? (int)$row['max_yr'] : null;
    }

    /**
     * Monthly interruption counts for a given year, optionally filtered by ulp_id.
     * Returns array indexed 1..12 with count values.
     */
    public function getMonthlyByYear(int $year, ?int $ulpId = null): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table($this->table)
            ->select('MONTH(event_date) as mo, COUNT(*) as cnt')
            ->where('YEAR(event_date)', $year)
            ->where('event_date IS NOT NULL');

        if ($ulpId !== null) {
            $builder->where('ulp_id', $ulpId);
        }

        $rows = $builder->groupBy('MONTH(event_date)')->get()->getResultArray();

        $monthly = array_fill(1, 12, 0);
        foreach ($rows as $r) {
            $monthly[(int)$r['mo']] = (int)$r['cnt'];
        }
        return $monthly;
    }

    /**
     * Top N feeders by interruption count for a given year, optionally filtered by ulp_id.
     * Returns array of ['penyulang_id', 'nama_penyulang', 'nama_ulp', 'cnt']
     */
    public function getTop10ByYear(int $year, ?int $ulpId = null, int $limit = 10): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table($this->table . ' h')
            ->select('h.penyulang_id, COALESCE(p.nama_penyulang, h.feeder_name) as nama_penyulang, COALESCE(u.nama_ulp, h.ulp_name) as nama_ulp, COUNT(*) as cnt')
            ->join('penyulang p', 'p.id = h.penyulang_id', 'left')
            ->join('ulps u', 'u.id = h.ulp_id', 'left')
            ->where('YEAR(h.event_date)', $year)
            ->where('h.event_date IS NOT NULL')
            ->groupBy('h.penyulang_id, nama_penyulang, nama_ulp')
            ->orderBy('cnt', 'DESC')
            ->limit($limit);

        if ($ulpId !== null) {
            $builder->where('h.ulp_id', $ulpId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Describe the comparable period for two years (to explain apples-to-apples comparison).
     * Returns ['yearA_period', 'yearB_period', 'comparable_months'] strings.
     */
    public function getComparablePeriod(int $yearA, int $yearB, ?int $ulpId = null): array
    {
        $db = \Config\Database::connect();
        $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        // Find max month available for yearA
        $builderA = $db->table($this->table)
            ->selectMax('MONTH(event_date)', 'max_mo')
            ->where('YEAR(event_date)', $yearA)
            ->where('event_date IS NOT NULL');
        if ($ulpId) $builderA->where('ulp_id', $ulpId);
        $rowA = $builderA->get()->getRowArray();
        $maxMonthA = $rowA ? (int)($rowA['max_mo'] ?? 12) : 12;

        // Find max month available for yearB
        $builderB = $db->table($this->table)
            ->selectMax('MONTH(event_date)', 'max_mo')
            ->where('YEAR(event_date)', $yearB)
            ->where('event_date IS NOT NULL');
        if ($ulpId) $builderB->where('ulp_id', $ulpId);
        $rowB = $builderB->get()->getRowArray();
        $maxMonthB = $rowB ? (int)($rowB['max_mo'] ?? 12) : 12;

        // Comparable = min of the two max months
        $comparableMonths = min($maxMonthA, $maxMonthB);

        $periodA = 'Jan–' . $monthNames[$maxMonthA - 1] . ' ' . $yearA;
        $periodB = 'Jan–' . $monthNames[$maxMonthB - 1] . ' ' . $yearB;
        $periodComp = 'Jan–' . $monthNames[$comparableMonths - 1];

        return [
            'yearA_period'       => $periodA,
            'yearB_period'       => $periodB,
            'comparable_months'  => $comparableMonths,
            'comparable_period'  => $periodComp,
        ];
    }

    /**
     * Total interruptions for a year within the first N months, optionally filtered by ulp_id.
     */
    public function getTotalByYearUpToMonth(int $year, int $maxMonth, ?int $ulpId = null): int
    {
        $db = \Config\Database::connect();
        $builder = $db->table($this->table)
            ->where('YEAR(event_date)', $year)
            ->where('MONTH(event_date) <=', $maxMonth)
            ->where('event_date IS NOT NULL');
        if ($ulpId !== null) {
            $builder->where('ulp_id', $ulpId);
        }
        return (int)$builder->countAllResults();
    }
}
