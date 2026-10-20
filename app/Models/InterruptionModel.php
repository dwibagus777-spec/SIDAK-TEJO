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
        'kategori_raw',
        'interruption_group',
        'up3_name',
        'masuk_total_raw',
        'lama_padam_raw',
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

    protected static ?array $cachedColumns = null;

    /**
     * Check if a column exists in the table safely
     */
    public function hasColumn(string $column): bool
    {
        if (self::$cachedColumns === null) {
            try {
                $db = \Config\Database::connect();
                self::$cachedColumns = $db->getFieldNames($this->table);
            } catch (\Throwable $e) {
                self::$cachedColumns = [];
            }
        }
        return in_array($column, self::$cachedColumns, true);
    }

    /**
     * Get distinct years available in interruption history
     */
    public function getAvailableYears(): array
    {
        try {
            $rows = $this->select("DISTINCT YEAR(event_date) as yr")
                ->where('event_date IS NOT NULL')
                ->orderBy('yr', 'DESC')
                ->findAll();

            $years = array_values(array_filter(array_map(fn($r) => (int)$r['yr'], $rows)));
            return !empty($years) ? $years : [(int)date('Y'), (int)date('Y') - 1];
        } catch (\Throwable $e) {
            return [(int)date('Y'), (int)date('Y') - 1];
        }
    }

    /**
     * Get the latest (max) year with data
     */
    public function getLatestYear(): ?int
    {
        try {
            $db = \Config\Database::connect();
            $row = $db->table($this->table)
                ->selectMax('YEAR(event_date)', 'max_yr')
                ->where('event_date IS NOT NULL')
                ->get()->getRowArray();
            return $row && $row['max_yr'] ? (int)$row['max_yr'] : (int)date('Y');
        } catch (\Throwable $e) {
            return (int)date('Y');
        }
    }

    /**
     * Monthly interruption counts for a given year, optionally filtered by ulp_id and penyulang_id.
     * Returns array indexed 1..12 with count values.
     */
    public function getMonthlyByYear(int $year, ?int $ulpId = null, ?int $penyulangId = null): array
    {
        $monthly = array_fill(1, 12, 0);
        try {
            $db = \Config\Database::connect();
            $builder = $db->table($this->table)
                ->select('MONTH(event_date) as mo, COUNT(*) as cnt')
                ->where('YEAR(event_date)', $year)
                ->where('event_date IS NOT NULL');

            if ($ulpId !== null) {
                if ($this->hasColumn('ulp_id')) {
                    $builder->where('ulp_id', $ulpId);
                } else {
                    $ulpRow = $db->table('ulps')->select('nama_ulp')->where('id', $ulpId)->get()->getRowArray();
                    if ($ulpRow) {
                        $cleanUlp = trim(str_ireplace('ULP', '', $ulpRow['nama_ulp']));
                        $builder->like('ulp_name', $cleanUlp);
                    }
                }
            }

            if ($penyulangId !== null && $penyulangId > 0) {
                if ($this->hasColumn('penyulang_id')) {
                    $builder->where('penyulang_id', $penyulangId);
                } else {
                    $pRow = $db->table('penyulang')->select('nama_penyulang')->where('id', $penyulangId)->get()->getRowArray();
                    if ($pRow) {
                        $builder->where('feeder_name', $pRow['nama_penyulang']);
                    }
                }
            }

            $rows = $builder->groupBy('MONTH(event_date)')->get()->getResultArray();
            foreach ($rows as $r) {
                $monthly[(int)$r['mo']] = (int)$r['cnt'];
            }
        } catch (\Throwable $e) {
            log_message('error', '[InterruptionModel::getMonthlyByYear] ' . $e->getMessage());
        }
        return $monthly;
    }

    /**
     * Top N feeders by interruption count for a given year, optionally filtered by ulp_id.
     * Fully compliant with MySQL 8 ONLY_FULL_GROUP_BY and resilient to missing relation columns.
     */
    public function getTop10ByYear(int $year, ?int $ulpId = null, int $limit = 10): array
    {
        try {
            $db = \Config\Database::connect();
            $hasUlpId = $this->hasColumn('ulp_id');
            $hasPenyulangId = $this->hasColumn('penyulang_id');

            $builder = $db->table($this->table)
                ->select('feeder_name, MAX(ulp_name) as raw_ulp_name, COUNT(*) as cnt')
                ->where('YEAR(event_date)', $year)
                ->where('event_date IS NOT NULL');

            if ($hasPenyulangId) {
                $builder->selectMax('penyulang_id', 'penyulang_id');
            }
            if ($hasUlpId) {
                $builder->selectMax('ulp_id', 'ulp_id');
            }

            if ($ulpId !== null) {
                if ($hasUlpId) {
                    $builder->where('ulp_id', $ulpId);
                } else {
                    $ulpRow = $db->table('ulps')->select('nama_ulp')->where('id', $ulpId)->get()->getRowArray();
                    if ($ulpRow) {
                        $cleanUlp = trim(str_ireplace('ULP', '', $ulpRow['nama_ulp']));
                        $builder->like('ulp_name', $cleanUlp);
                    }
                }
            }

            $rows = $builder->groupBy('feeder_name')
                ->orderBy('cnt', 'DESC')
                ->limit($limit)
                ->get()->getResultArray();

            // Load master catalogs for canonical display
            $feedersMaster = $db->table('penyulang')->select('id, nama_penyulang, ulp_id')->get()->getResultArray();
            $feederMap = [];
            foreach ($feedersMaster as $fm) {
                $feederMap[strtoupper(trim($fm['nama_penyulang']))] = $fm;
            }

            $ulpsMaster = $db->table('ulps')->select('id, nama_ulp')->get()->getResultArray();
            $ulpMap = [];
            foreach ($ulpsMaster as $um) {
                $ulpMap[(int)$um['id']] = $um['nama_ulp'];
            }

            $result = [];
            foreach ($rows as $r) {
                $fName    = trim((string)$r['feeder_name']);
                $fKey     = strtoupper($fName);
                $pId      = !empty($r['penyulang_id']) ? (int)$r['penyulang_id'] : ($feederMap[$fKey]['id'] ?? 0);
                $dispName = $feederMap[$fKey]['nama_penyulang'] ?? $fName;

                $uId = !empty($r['ulp_id']) ? (int)$r['ulp_id'] : ($feederMap[$fKey]['ulp_id'] ?? null);
                $dispUlp = $uId && isset($ulpMap[$uId]) ? $ulpMap[$uId] : (trim((string)($r['raw_ulp_name'] ?? '')) ?: '—');
                if (!empty($dispUlp) && !str_starts_with($dispUlp, 'ULP ') && $dispUlp !== '—') {
                    $dispUlp = 'ULP ' . $dispUlp;
                }

                $result[] = [
                    'penyulang_id'   => $pId,
                    'nama_penyulang' => $dispName,
                    'feeder_name'    => $dispName,
                    'nama_ulp'       => $dispUlp,
                    'cnt'            => (int)$r['cnt'],
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            log_message('error', '[InterruptionModel::getTop10ByYear] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Describe the comparable period for two years (to explain apples-to-apples comparison).
     * Returns ['yearA_period', 'yearB_period', 'comparable_months'] strings.
     */
    public function getComparablePeriod(int $yearA, int $yearB, ?int $ulpId = null, ?int $penyulangId = null): array
    {
        $monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        try {
            $db = \Config\Database::connect();
            $hasUlpId = $this->hasColumn('ulp_id');
            $hasPenyulangId = $this->hasColumn('penyulang_id');

            // Find max month available for yearA
            $builderA = $db->table($this->table)
                ->selectMax('MONTH(event_date)', 'max_mo')
                ->where('YEAR(event_date)', $yearA)
                ->where('event_date IS NOT NULL');
            if ($ulpId !== null) {
                if ($hasUlpId) {
                    $builderA->where('ulp_id', $ulpId);
                } else {
                    $ulpRow = $db->table('ulps')->select('nama_ulp')->where('id', $ulpId)->get()->getRowArray();
                    if ($ulpRow) {
                        $cleanUlp = trim(str_ireplace('ULP', '', $ulpRow['nama_ulp']));
                        $builderA->like('ulp_name', $cleanUlp);
                    }
                }
            }
            if ($penyulangId !== null && $penyulangId > 0) {
                if ($hasPenyulangId) {
                    $builderA->where('penyulang_id', $penyulangId);
                } else {
                    $pRow = $db->table('penyulang')->select('nama_penyulang')->where('id', $penyulangId)->get()->getRowArray();
                    if ($pRow) {
                        $builderA->where('feeder_name', $pRow['nama_penyulang']);
                    }
                }
            }
            $rowA = $builderA->get()->getRowArray();
            $maxMonthA = $rowA && !empty($rowA['max_mo']) ? (int)$rowA['max_mo'] : 12;

            // Find max month available for yearB
            $builderB = $db->table($this->table)
                ->selectMax('MONTH(event_date)', 'max_mo')
                ->where('YEAR(event_date)', $yearB)
                ->where('event_date IS NOT NULL');
            if ($ulpId !== null) {
                if ($hasUlpId) {
                    $builderB->where('ulp_id', $ulpId);
                } else {
                    $ulpRow = $db->table('ulps')->select('nama_ulp')->where('id', $ulpId)->get()->getRowArray();
                    if ($ulpRow) {
                        $cleanUlp = trim(str_ireplace('ULP', '', $ulpRow['nama_ulp']));
                        $builderB->like('ulp_name', $cleanUlp);
                    }
                }
            }
            if ($penyulangId !== null && $penyulangId > 0) {
                if ($hasPenyulangId) {
                    $builderB->where('penyulang_id', $penyulangId);
                } else {
                    $pRow = $db->table('penyulang')->select('nama_penyulang')->where('id', $penyulangId)->get()->getRowArray();
                    if ($pRow) {
                        $builderB->where('feeder_name', $pRow['nama_penyulang']);
                    }
                }
            }
            $rowB = $builderB->get()->getRowArray();
            $maxMonthB = $rowB && !empty($rowB['max_mo']) ? (int)$rowB['max_mo'] : 12;

            $comparableMonths = min(max(1, $maxMonthA), max(1, $maxMonthB));

            $periodA = 'Jan–' . $monthNames[min(11, max(0, $maxMonthA - 1))] . ' ' . $yearA;
            $periodB = 'Jan–' . $monthNames[min(11, max(0, $maxMonthB - 1))] . ' ' . $yearB;
            $periodComp = 'Jan–' . $monthNames[min(11, max(0, $comparableMonths - 1))];

            return [
                'yearA_period'       => $periodA,
                'yearB_period'       => $periodB,
                'comparable_months'  => $comparableMonths,
                'comparable_period'  => $periodComp,
            ];
        } catch (\Throwable $e) {
            log_message('error', '[InterruptionModel::getComparablePeriod] ' . $e->getMessage());
            return [
                'yearA_period'       => "Tahun $yearA",
                'yearB_period'       => "Tahun $yearB",
                'comparable_months'  => 12,
                'comparable_period'  => 'Semua Bulan',
            ];
        }
    }

    /**
     * Total interruptions for a year within the first N months, optionally filtered by ulp_id and penyulang_id.
     */
    public function getTotalByYearUpToMonth(int $year, int $maxMonth, ?int $ulpId = null, ?int $penyulangId = null): int
    {
        try {
            $db = \Config\Database::connect();
            $hasUlpId = $this->hasColumn('ulp_id');
            $hasPenyulangId = $this->hasColumn('penyulang_id');

            $builder = $db->table($this->table)
                ->where('YEAR(event_date)', $year)
                ->where('MONTH(event_date) <=', $maxMonth)
                ->where('event_date IS NOT NULL');

            if ($ulpId !== null) {
                if ($hasUlpId) {
                    $builder->where('ulp_id', $ulpId);
                } else {
                    $ulpRow = $db->table('ulps')->select('nama_ulp')->where('id', $ulpId)->get()->getRowArray();
                    if ($ulpRow) {
                        $cleanUlp = trim(str_ireplace('ULP', '', $ulpRow['nama_ulp']));
                        $builder->like('ulp_name', $cleanUlp);
                    }
                }
            }

            if ($penyulangId !== null && $penyulangId > 0) {
                if ($hasPenyulangId) {
                    $builder->where('penyulang_id', $penyulangId);
                } else {
                    $pRow = $db->table('penyulang')->select('nama_penyulang')->where('id', $penyulangId)->get()->getRowArray();
                    if ($pRow) {
                        $builder->where('feeder_name', $pRow['nama_penyulang']);
                    }
                }
            }

            return (int)$builder->countAllResults();
        } catch (\Throwable $e) {
            log_message('error', '[InterruptionModel::getTotalByYearUpToMonth] ' . $e->getMessage());
            return 0;
        }
    }
}
