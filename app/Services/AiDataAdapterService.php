<?php

namespace App\Services;

class AiDataAdapterService
{
    /**
     * Dictionary of semantic header variations mapped to SIDAK TEJO canonical fields.
     */
    protected array $headerDictionary = [
        'kode_asset' => [
            'kode_asset', 'kode asset', 'asset_code', 'asset code', 'no tiang', 'no. tiang', 
            'nomor tiang', 'id_asset', 'id asset', 'no_asset', 'no asset', 'kode_tiang', 'kode tiang'
        ],
        'nama_asset' => [
            'nama_asset', 'nama asset', 'asset_name', 'asset name', 'nama tiang', 'nama_tiang',
            'tiang_name', 'nama kompoen', 'nama peralatan', 'nama aset', 'nama_aset', 'nama asset jtm', 'nama_asset_jtm'
        ],
        'jenis_asset' => [
            'jenis_asset', 'jenis asset', 'asset_type', 'jenis', 'tipe asset', 'tipe_asset', 'jenis_komponen'
        ],
        'feeder_name' => [
            'penyulang', 'nama penyulang', 'nama_penyulang', 'feeder', 'feeder_name', 'feeder name', 'penyulang_id'
        ],
        'ulp_name' => [
            'ulp', 'nama ulp', 'nama_ulp', 'unit pelayanan', 'ulp_name', 'ulp name', 'ulp_id'
        ],
        'unit_name' => [
            'unit', 'nama unit', 'nama_unit', 'up3', 'nama up3', 'unit_name', 'unit name'
        ],
        'section_name' => [
            'section', 'nama section', 'nama_section', 'section_name', 'sec', 'section_id'
        ],
        'latitude' => [
            'latitude', 'lat', 'y', 'lat_deg', 'lat_dd', 'koordinat_y', 'koordinat y'
        ],
        'longitude' => [
            'longitude', 'long', 'lng', 'x', 'lon', 'long_deg', 'long_dd', 'koordinat_x', 'koordinat x'
        ],
        'kode_konstruksi' => [
            'konstruksi', 'kode_konstruksi', 'kode konstruksi', 'konstruksi tiang', 'construction', 'konstruksi e g tm1'
        ],
        'tanggal_operasi' => [
            'tanggal operasi', 'tanggal_operasi', 'tgl_operasi', 'operasi_date'
        ],
        'kapasitas' => [
            'kapasitas', 'capacity', 'daya', 'kapasitas panjang'
        ],
        'jumlah_pohon' => [
            'jumlah pohon', 'jumlah_pohon', 'pohon'
        ],
    ];

    /**
     * Inspect file structure and generate canonical mapping evidence.
     */
    public function analyzeAndMapHeaders(array $rawHeader): array
    {
        $mappingEvidence = [];
        $mappedCanonical = [];

        foreach ($rawHeader as $colIndex => $rawName) {
            // Clean UTF-8 BOM and unprintable characters
            $cleanName = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', (string)$rawName);
            $normalizedName = strtolower(trim(preg_replace('/[^a-zA-Z0-9_\s]/', '', $cleanName)));

            $matchedField = null;
            $confidence = 0.0;

            foreach ($this->headerDictionary as $canonicalField => $aliases) {
                foreach ($aliases as $alias) {
                    if ($normalizedName === $alias) {
                        $matchedField = $canonicalField;
                        $confidence = 0.99;
                        break 2;
                    } elseif (str_contains($normalizedName, $alias)) {
                        $matchedField = $canonicalField;
                        $confidence = 0.85;
                    }
                }
            }

            if ($matchedField) {
                $mappingEvidence[] = [
                    'source_column'   => $cleanName,
                    'column_index'    => $colIndex,
                    'canonical_field' => $matchedField,
                    'mapping_method'  => 'semantic_header_match',
                    'confidence'      => $confidence,
                ];
                $mappedCanonical[$colIndex] = $matchedField;
            } else {
                $mappingEvidence[] = [
                    'source_column'   => $cleanName,
                    'column_index'    => $colIndex,
                    'canonical_field' => null,
                    'mapping_method'  => 'UNMAPPED_COLUMN',
                    'confidence'      => 0.0,
                ];
            }
        }

        return [
            'mapping_evidence' => $mappingEvidence,
            'column_map'       => $mappedCanonical,
        ];
    }

    /**
     * Parse raw file content (CSV/XLSX) and transform into Canonical SIDAK TEJO Rows.
     */
    public function parseAndTransformToCanonical(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException("Source file does not exist or is not readable: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $rawRows = [];

        if ($extension === 'csv' || $extension === 'txt') {
            $content = file_get_contents($filePath);
            // Remove UTF-8 BOM if present
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

            $lines = explode("\n", str_replace("\r\n", "\n", $content));
            $lines = array_filter($lines, fn($line) => trim($line) !== '');

            if (empty($lines)) {
                throw new \RuntimeException("Uploaded CSV file is empty.");
            }

            // Auto-detect CSV delimiter (comma, semicolon, or tab)
            $firstLine = reset($lines);
            $commaCount = substr_count($firstLine, ',');
            $semicolonCount = substr_count($firstLine, ';');
            $tabCount = substr_count($firstLine, "\t");

            $delimiter = ',';
            if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
                $delimiter = ';';
            } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
                $delimiter = "\t";
            }

            foreach ($lines as $line) {
                $rawRows[] = str_getcsv($line, $delimiter);
            }
        } else {
            // For non-csv/xlsx text fallback
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $rawRows[] = str_getcsv($line, ',');
            }
        }

        if (empty($rawRows)) {
            throw new \RuntimeException("Uploaded file is empty.");
        }

        $rawHeader = array_shift($rawRows);
        $headerAnalysis = $this->analyzeAndMapHeaders($rawHeader);
        $columnMap = $headerAnalysis['column_map'];

        $canonicalRows = [];
        $rowNumber = 1;

        foreach ($rawRows as $row) {
            $rowNumber++;
            if (empty($row) || (count($row) === 1 && trim((string)$row[0]) === '')) {
                continue;
            }

            $canonical = [
                'kode_asset'       => null,
                'nama_asset'       => null,
                'jenis_asset'      => 'JTM_COMPONENT',
                'unit_name'        => null,
                'ulp_name'         => null,
                'feeder_name'      => null,
                'section_name'     => null,
                'latitude'         => null,
                'longitude'        => null,
                'kode_konstruksi'  => null,
                'tanggal_operasi'  => null,
                'kapasitas'        => null,
                'jumlah_pohon'     => null,
                'parent_asset_id'  => null, // Preserved NULL - AI NEVER Fabricates
                'section_id'       => null, // Preserved NULL - AI NEVER Fabricates
                'sequence_no'      => null, // Preserved NULL - AI NEVER Fabricates
            ];

            foreach ($row as $colIdx => $val) {
                $val = trim((string)$val);
                if ($val === '') continue;

                if (isset($columnMap[$colIdx])) {
                    $targetField = $columnMap[$colIdx];
                    if ($targetField === 'latitude' || $targetField === 'longitude') {
                        // Normalize Indonesian decimal comma to dot
                        $numStr = str_replace(',', '.', $val);
                        $canonical[$targetField] = is_numeric($numStr) ? (float)$numStr : null;
                    } else {
                        $canonical[$targetField] = $val;
                    }
                }
            }

            // Normalization & Safety Rule: Do not invent missing physical attributes
            if ($canonical['nama_asset'] !== null) {
                $canonical['nama_asset'] = preg_replace('/\s+/', ' ', trim($canonical['nama_asset']));
            }
            if ($canonical['feeder_name'] !== null) {
                $canonical['feeder_name'] = mb_strtoupper(trim($canonical['feeder_name']), 'UTF-8');
            }

            // Canonical Identity Fallback:
            // 1. If kode_asset is NULL but nama_asset is present, derive deterministic kode_asset
            if (empty($canonical['kode_asset']) && !empty($canonical['nama_asset'])) {
                $feederPrefix = !empty($canonical['feeder_name']) ? preg_replace('/[^A-Z0-9]/', '', $canonical['feeder_name']) : 'GEN';
                $cleanName = preg_replace('/[^A-Z0-9_\-]/', '', strtoupper($canonical['nama_asset']));
                $canonical['kode_asset'] = "AST-{$feederPrefix}-{$cleanName}";
            }
            // 2. If nama_asset is NULL but kode_asset is present, set nama_asset = kode_asset
            if (empty($canonical['nama_asset']) && !empty($canonical['kode_asset'])) {
                $canonical['nama_asset'] = $canonical['kode_asset'];
            }

            $canonicalRows[] = $canonical;
        }

        return [
            'source_file'       => basename($filePath),
            'source_rows'       => count($canonicalRows),
            'mapping_evidence'  => $headerAnalysis['mapping_evidence'],
            'canonical_rows'    => $canonicalRows,
            'source_file_sha256'=> hash_file('sha256', $filePath),
        ];
    }

    /**
     * Save canonical dataset to intermediate server staging area.
     */
    public function saveCanonicalStaging(string $batchUuid, array $canonicalRows): string
    {
        $dir = WRITEPATH . 'ingest/' . $batchUuid;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $canonicalFile = $dir . '/canonical_rows.json';
        file_put_contents($canonicalFile, json_encode($canonicalRows, JSON_PRETTY_PRINT));
        file_put_contents($dir . '/canonical.json', json_encode($canonicalRows, JSON_PRETTY_PRINT));

        return $canonicalFile;
    }
}
