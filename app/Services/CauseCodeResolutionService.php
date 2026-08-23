<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Cause Code Resolution Service (Phase 7U Maintenance M-04)
 *
 * Responsibilities:
 * - Governed mapping of raw 'PENYEBAB SESUAI KODE GANGGUAN' values to Canonical Cause Codes.
 * - Enforces Governed Invariants:
 *     - RAW_CAUSE_VALUE != SILENTLY_OVERWRITTEN
 *     - UNMAPPED_SOURCE_VALUE = PRESERVED_AND_FLAGGED
 *     - CANONICAL_CAUSE_CODE = GOVERNED_NORMALIZATION_OUTPUT
 *     - NO_ARBITRARY_CAUSE_INVENTION
 */
class CauseCodeResolutionService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->ensureDictionarySeeded();
    }

    /**
     * Resolve a raw cause string into a canonical taxonomy entry.
     *
     * @param string|null $rawCause
     * @return array
     */
    public function resolveCause(?string $rawCause): array
    {
        $cleanedRaw = trim((string)$rawCause);

        if ($cleanedRaw === '' || $cleanedRaw === '-' || $cleanedRaw === '1') {
            return [
                'cause_raw'            => $rawCause ?? '',
                'cause_canonical_code' => 'CAUSE_UNKNOWN_UNDER_INVESTIGATION',
                'cause_category'       => 'UNKNOWN_INVESTIGATION',
                'cause_label'          => 'Belum Diketahui / Dalam Investigasi',
                'cause_mapping_status' => 'RESOLVED',
                'mapping_confidence'   => 0.90,
            ];
        }

        // Direct dictionary lookup (exact match case-insensitive)
        $builder = $this->db->table('cause_code_dictionary');
        $row = $builder->where('LOWER(source_raw_value)', strtolower($cleanedRaw))
                       ->where('is_active', 1)
                       ->get()
                       ->getRowArray();

        if ($row) {
            return [
                'cause_raw'            => $cleanedRaw,
                'cause_canonical_code' => $row['canonical_cause_code'],
                'cause_category'       => $row['cause_category'],
                'cause_label'          => $row['cause_label'],
                'cause_mapping_status' => 'RESOLVED',
                'mapping_confidence'   => (float)$row['mapping_confidence'],
            ];
        }

        // Fuzzy token matching across known categories
        $fuzzyResolution = $this->matchFuzzyCause($cleanedRaw);
        if ($fuzzyResolution) {
            return $fuzzyResolution;
        }

        // If completely unrecognized: PRESERVE_AND_FLAG (Zero invention)
        return [
            'cause_raw'            => $cleanedRaw,
            'cause_canonical_code' => 'CAUSE_UNMAPPED_ADVISORY',
            'cause_category'       => 'UNKNOWN_INVESTIGATION',
            'cause_label'          => 'Unmapped Cause: ' . $cleanedRaw,
            'cause_mapping_status' => 'UNMAPPED',
            'mapping_confidence'   => 0.50,
        ];
    }

    /**
     * Fallback heuristic matching for minor typos or variations in source data
     */
    protected function matchFuzzyCause(string $raw): ?array
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'binatang') || str_contains($lower, 'tikus') || str_contains($lower, 'burung') || str_contains($lower, 'bunglon') || str_contains($lower, 'ular') || str_contains($lower, 'tupai') || str_contains($lower, 'luwak') || str_contains($lower, 'tokek')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_ANIMAL_CONTACT',
                'cause_category'       => 'ANIMAL_CONTACT',
                'cause_label'          => 'Sentuhan Binatang (Tikus/Burung/Bunglon/Ular/Luwak)',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.90,
            ];
        }

        if (str_contains($lower, 'row') || str_contains($lower, 'pohon') || str_contains($lower, 'bambu') || str_contains($lower, 'ranting') || str_contains($lower, 'daun')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_VEGETATION_ROW',
                'cause_category'       => 'VEGETATION_ROW',
                'cause_label'          => 'Pohon / Ranting / Vegetasi (ROW)',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.90,
            ];
        }

        if (str_contains($lower, 'petir') || str_contains($lower, 'force majure') || str_contains($lower, 'force majeure')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_LIGHTNING_SURGE',
                'cause_category'       => 'LIGHTNING_WEATHER',
                'cause_label'          => 'Sambaran Petir / Force Majeure Cuaca',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.90,
            ];
        }

        if (str_contains($lower, 'layang') || str_contains($lower, 'pihak ke 3') || str_contains($lower, 'pihak 3') || str_contains($lower, 'proyek') || str_contains($lower, 'spandek') || str_contains($lower, 'seng') || str_contains($lower, 'reklame') || str_contains($lower, 'umbul') || str_contains($lower, 'foil') || str_contains($lower, 'balon')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_THIRD_PARTY_OBJECT',
                'cause_category'       => 'THIRD_PARTY_OBJECT',
                'cause_label'          => 'Benda Asing / Pihak Ketiga (Layangan/Spandek/Proyek)',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.88,
            ];
        }

        if (str_contains($lower, 'fco') || str_contains($lower, 'fuse') || str_contains($lower, 'arrester') || str_contains($lower, 'isolator') || str_contains($lower, 'trafo') || str_contains($lower, 'lbs') || str_contains($lower, 'pt/ct') || str_contains($lower, 'ct') || str_contains($lower, 'matrial') || str_contains($lower, 'material') || str_contains($lower, 'peralatan')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_EQUIPMENT_FAILURE',
                'cause_category'       => 'EQUIPMENT_FAILURE',
                'cause_label'          => 'Kegagalan Komponen / Material Distribusi (FCO/Arrester/Isolator/Trafo)',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.85,
            ];
        }

        if (str_contains($lower, 'terminating') || str_contains($lower, 'terminasi') || str_contains($lower, 'mvtic') || str_contains($lower, 'xlpe') || str_contains($lower, 'jointing') || str_contains($lower, 'kabel tanah')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_CABLE_TERMINATION_FAULT',
                'cause_category'       => 'CABLE_TERMINATION_FAULT',
                'cause_label'          => 'Kegagalan Isolasi Kabel / Terminasi / Jointing',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.88,
            ];
        }

        if (str_contains($lower, 'konduktor') || str_contains($lower, 'sutm') || str_contains($lower, 'gsw') || str_contains($lower, 'jumper')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_CONDUCTOR_GSW_SNAP',
                'cause_category'       => 'CONDUCTOR_GSW_SNAP',
                'cause_label'          => 'Konduktor SUTM / Jumper / GSW Putus atau Lepas',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.88,
            ];
        }

        if (str_contains($lower, 'iml') || str_contains($lower, 'overload') || str_contains($lower, 'ol') || str_contains($lower, 'ob')) {
            return [
                'cause_raw'            => $raw,
                'cause_canonical_code' => 'CAUSE_CUSTOMER_IML_FAULT',
                'cause_category'       => 'CUSTOMER_IML_FAULT',
                'cause_label'          => 'Instalasi Milik Langganan (IML) / Overload Pelanggan',
                'cause_mapping_status' => 'PARTIALLY_RESOLVED',
                'mapping_confidence'   => 0.90,
            ];
        }

        return null;
    }

    /**
     * Pre-populate standard cause dictionary if empty
     */
    protected function ensureDictionarySeeded(): void
    {
        if (!$this->db->tableExists('cause_code_dictionary')) {
            return;
        }

        $count = $this->db->table('cause_code_dictionary')->countAllResults();
        if ($count > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $dictionary = [
            // Binatang
            ['source_raw_value' => 'Binatang', 'canonical_cause_code' => 'CAUSE_ANIMAL_CONTACT', 'cause_category' => 'ANIMAL_CONTACT', 'cause_label' => 'Sentuhan Binatang (Tikus/Burung/Bunglon/Ular)'],
            ['source_raw_value' => 'binatang', 'canonical_cause_code' => 'CAUSE_ANIMAL_CONTACT', 'cause_category' => 'ANIMAL_CONTACT', 'cause_label' => 'Sentuhan Binatang'],
            ['source_raw_value' => 'ULAR', 'canonical_cause_code' => 'CAUSE_ANIMAL_CONTACT', 'cause_category' => 'ANIMAL_CONTACT', 'cause_label' => 'Ular Melilit Jaringan'],
            ['source_raw_value' => 'burung', 'canonical_cause_code' => 'CAUSE_ANIMAL_CONTACT', 'cause_category' => 'ANIMAL_CONTACT', 'cause_label' => 'Burung Hinggap Jumperan'],
            
            // Petir & Cuaca
            ['source_raw_value' => 'Petir', 'canonical_cause_code' => 'CAUSE_LIGHTNING_SURGE', 'cause_category' => 'LIGHTNING_WEATHER', 'cause_label' => 'Sambaran Petir'],
            ['source_raw_value' => 'PETIR', 'canonical_cause_code' => 'CAUSE_LIGHTNING_SURGE', 'cause_category' => 'LIGHTNING_WEATHER', 'cause_label' => 'Sambaran Petir'],
            ['source_raw_value' => 'Sambaran Petir', 'canonical_cause_code' => 'CAUSE_LIGHTNING_SURGE', 'cause_category' => 'LIGHTNING_WEATHER', 'cause_label' => 'Sambaran Petir Langsung / Induksi'],
            ['source_raw_value' => 'Force Majure', 'canonical_cause_code' => 'CAUSE_LIGHTNING_SURGE', 'cause_category' => 'LIGHTNING_WEATHER', 'cause_label' => 'Force Majeure Cuaca Ekstrem / Badai'],
            ['source_raw_value' => 'Force Majeure', 'canonical_cause_code' => 'CAUSE_LIGHTNING_SURGE', 'cause_category' => 'LIGHTNING_WEATHER', 'cause_label' => 'Force Majeure Cuaca Ekstrem / Badai'],
            ['source_raw_value' => 'Petir SUTM Putus', 'canonical_cause_code' => 'CAUSE_LIGHTNING_SURGE', 'cause_category' => 'LIGHTNING_WEATHER', 'cause_label' => 'SUTM Putus Akibat Sambaran Petir'],

            // Pihak ke-3 & Benda Asing
            ['source_raw_value' => 'Pihak ke 3', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'Benda Asing / Aktivitas Pihak Ketiga'],
            ['source_raw_value' => 'PIHAK KE 3', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'Benda Asing / Aktivitas Pihak Ketiga'],
            ['source_raw_value' => 'Layang-Layang', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'Benang / Kerangka Layang-layang'],
            ['source_raw_value' => 'Layangan', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'Benang / Kerangka Layang-layang'],
            ['source_raw_value' => 'UMBUL UMBUL', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'Umbul-umbul / Spanduk Terbang'],
            ['source_raw_value' => 'Almunium Foil', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'Aluminium Foil / Sampah Terbang'],
            ['source_raw_value' => 'Bangunan', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'Aktivitas Konstruksi Bangunan'],
            ['source_raw_value' => 'SUTM kena PJU', 'canonical_cause_code' => 'CAUSE_THIRD_PARTY_OBJECT', 'cause_category' => 'THIRD_PARTY_OBJECT', 'cause_label' => 'SUTM Bersentuhan dengan Tiang PJU'],

            // Vegetasi / ROW
            ['source_raw_value' => 'ROW', 'canonical_cause_code' => 'CAUSE_VEGETATION_ROW', 'cause_category' => 'VEGETATION_ROW', 'cause_label' => 'Pohon / Ranting / Vegetasi Mendekati JTM'],
            ['source_raw_value' => 'Pohon / ROW', 'canonical_cause_code' => 'CAUSE_VEGETATION_ROW', 'cause_category' => 'VEGETATION_ROW', 'cause_label' => 'Pohon / Ranting Mengenai Jaringan'],
            ['source_raw_value' => 'Pohon Tumbang', 'canonical_cause_code' => 'CAUSE_VEGETATION_ROW', 'cause_category' => 'VEGETATION_ROW', 'cause_label' => 'Pohon Tumbang Menimpa SUTM'],

            // Material & Peralatan
            ['source_raw_value' => 'Matrial', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Kegagalan Material / Komponen Distribusi'],
            ['source_raw_value' => 'Peralatan', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Kegagalan Peralatan Jaringan'],
            ['source_raw_value' => 'FCO', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Fuse Cut Out (FCO) Putus / Rusak'],
            ['source_raw_value' => 'fco', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Fuse Cut Out (FCO) Putus / Rusak'],
            ['source_raw_value' => 'Fuse Putus', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Fuse Link Putus'],
            ['source_raw_value' => 'ARRESTER RUSAK', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Lightning Arrester Pecah / Breakdown'],
            ['source_raw_value' => 'Arrester', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Lightning Arrester Pecah / Breakdown'],
            ['source_raw_value' => 'Trafo', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Trafo Distribusi Rusak'],
            ['source_raw_value' => 'Tiang', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Tiang Retak / Miring / Rusak'],
            ['source_raw_value' => 'tiang roboh', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Tiang Distribusi Roboh'],
            ['source_raw_value' => 'LBSM breakdown', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'LBS Motorized Gagal Isolasi'],

            // Kabel & Terminasi
            ['source_raw_value' => 'Terminating', 'canonical_cause_code' => 'CAUSE_CABLE_TERMINATION_FAULT', 'cause_category' => 'CABLE_TERMINATION_FAULT', 'cause_label' => 'Terminasi Kabel Gagal Isolasi / Tembus'],
            ['source_raw_value' => 'TERMINASI', 'canonical_cause_code' => 'CAUSE_CABLE_TERMINATION_FAULT', 'cause_category' => 'CABLE_TERMINATION_FAULT', 'cause_label' => 'Terminasi Kabel Gagal Isolasi / Tembus'],
            ['source_raw_value' => 'MVTIC', 'canonical_cause_code' => 'CAUSE_CABLE_TERMINATION_FAULT', 'cause_category' => 'CABLE_TERMINATION_FAULT', 'cause_label' => 'Kabel MVTIC Breakdown'],
            ['source_raw_value' => 'mvtic', 'canonical_cause_code' => 'CAUSE_CABLE_TERMINATION_FAULT', 'cause_category' => 'CABLE_TERMINATION_FAULT', 'cause_label' => 'Kabel MVTIC Breakdown'],
            ['source_raw_value' => 'XLPE RUSAK', 'canonical_cause_code' => 'CAUSE_CABLE_TERMINATION_FAULT', 'cause_category' => 'CABLE_TERMINATION_FAULT', 'cause_label' => 'Kabel XLPE Tanah Rusak / Bocor'],
            ['source_raw_value' => 'kabel xlpe', 'canonical_cause_code' => 'CAUSE_CABLE_TERMINATION_FAULT', 'cause_category' => 'CABLE_TERMINATION_FAULT', 'cause_label' => 'Kabel XLPE Tanah Rusak / Bocor'],
            ['source_raw_value' => 'JOINTING', 'canonical_cause_code' => 'CAUSE_CABLE_TERMINATION_FAULT', 'cause_category' => 'CABLE_TERMINATION_FAULT', 'cause_label' => 'Sambungan Jointing Kabel Breakdown'],

            // Konduktor & GSW
            ['source_raw_value' => 'Konduktor', 'canonical_cause_code' => 'CAUSE_CONDUCTOR_GSW_SNAP', 'cause_category' => 'CONDUCTOR_GSW_SNAP', 'cause_label' => 'Konduktor SUTM / Jumperan Putus'],
            ['source_raw_value' => 'SUTM PUTUS', 'canonical_cause_code' => 'CAUSE_CONDUCTOR_GSW_SNAP', 'cause_category' => 'CONDUCTOR_GSW_SNAP', 'cause_label' => 'Kawat Konduktor SUTM Putus'],
            ['source_raw_value' => 'SUTM LEPAS DR PIN', 'canonical_cause_code' => 'CAUSE_CONDUCTOR_GSW_SNAP', 'cause_category' => 'CONDUCTOR_GSW_SNAP', 'cause_label' => 'Konduktor Lepas Dari Baut Pin Isolator'],
            ['source_raw_value' => 'GSW', 'canonical_cause_code' => 'CAUSE_CONDUCTOR_GSW_SNAP', 'cause_category' => 'CONDUCTOR_GSW_SNAP', 'cause_label' => 'Kawat GSW Putus / Lepas'],
            ['source_raw_value' => 'JAMPERAN LBSM PUTUS', 'canonical_cause_code' => 'CAUSE_CONDUCTOR_GSW_SNAP', 'cause_category' => 'CONDUCTOR_GSW_SNAP', 'cause_label' => 'Jumperan LBSM Putus'],

            // IML & Overload
            ['source_raw_value' => 'IML', 'canonical_cause_code' => 'CAUSE_CUSTOMER_IML_FAULT', 'cause_category' => 'CUSTOMER_IML_FAULT', 'cause_label' => 'Instalasi Milik Langganan (IML)'],
            ['source_raw_value' => 'iml', 'canonical_cause_code' => 'CAUSE_CUSTOMER_IML_FAULT', 'cause_category' => 'CUSTOMER_IML_FAULT', 'cause_label' => 'Instalasi Milik Langganan (IML)'],
            ['source_raw_value' => 'OB', 'canonical_cause_code' => 'CAUSE_CUSTOMER_IML_FAULT', 'cause_category' => 'CUSTOMER_IML_FAULT', 'cause_label' => 'Overburden / Beban Lebih Jaringan'],
            ['source_raw_value' => 'OL', 'canonical_cause_code' => 'CAUSE_CUSTOMER_IML_FAULT', 'cause_category' => 'CUSTOMER_IML_FAULT', 'cause_label' => 'Overload Trafo / Feeder'],

            // Belum Diketemukan / Simpatetik
            ['source_raw_value' => 'Belum Diketemukan', 'canonical_cause_code' => 'CAUSE_UNKNOWN_UNDER_INVESTIGATION', 'cause_category' => 'UNKNOWN_INVESTIGATION', 'cause_label' => 'Belum Diketemukan / Nihil Temuan Patroli'],
            ['source_raw_value' => 'Simpatetik', 'canonical_cause_code' => 'CAUSE_UNKNOWN_UNDER_INVESTIGATION', 'cause_category' => 'UNKNOWN_INVESTIGATION', 'cause_label' => 'Trip Simpatetik Akibat Gangguan di Penyulang Lain'],
        ];

        foreach ($dictionary as &$row) {
            $row['mapping_confidence'] = 1.00;
            $row['is_active'] = 1;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        $this->db->table('cause_code_dictionary')->insertBatch($dictionary);
    }
}
