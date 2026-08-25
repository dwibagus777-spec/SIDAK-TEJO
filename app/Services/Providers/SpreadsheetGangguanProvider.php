<?php

namespace App\Services\Providers;

use App\Contracts\GangguanDataProviderInterface;
use App\Services\HistoricalInterruptionNormalizerService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Spreadsheet Gangguan Provider (Multi-Format Adapter: CSV, XLS, XLSX)
 *
 * Provides on-demand normalization and reading of raw external interruption
 * spreadsheets (CSV, Excel XLS/XLSX) using sealed M-04 normalizer logic without altering
 * database schema or creating runtime dependencies.
 */
class SpreadsheetGangguanProvider implements GangguanDataProviderInterface
{
    protected ?string $filePath;
    protected ?string $selectedSheetName = null;
    protected HistoricalInterruptionNormalizerService $normalizer;
    protected array $inMemoryCache = [];
    protected bool $loaded = false;
    protected array $supportedExtensions = ['csv', 'xlsx', 'xls', 'txt'];

    public function __construct(?string $filePath = null, ?HistoricalInterruptionNormalizerService $normalizer = null)
    {
        $this->filePath   = $filePath;
        $this->normalizer = $normalizer ?? new HistoricalInterruptionNormalizerService();
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        if (empty($this->filePath) || !file_exists($this->filePath) || !is_readable($this->filePath)) {
            return false;
        }

        $ext = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));
        return in_array($ext, $this->supportedExtensions, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceIdentifier(): string
    {
        return 'SPREADSHEET_MULTI_FORMAT_ADAPTER';
    }

    /**
     * Set external file path dynamically and reset cache.
     *
     * @param string $path
     * @param string|null $sheetName
     * @return self
     */
    public function setFilePath(string $path, ?string $sheetName = null): self
    {
        $this->filePath          = $path;
        $this->selectedSheetName = $sheetName;
        $this->inMemoryCache     = [];
        $this->loaded            = false;
        return $this;
    }

    /**
     * Select a specific worksheet name.
     *
     * @param string|null $sheetName
     * @return self
     */
    public function selectSheet(?string $sheetName): self
    {
        $this->selectedSheetName = $sheetName;
        $this->inMemoryCache     = [];
        $this->loaded            = false;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getInterruptions(array $filters = []): array
    {
        $this->ensureLoaded();

        if (empty($this->inMemoryCache)) {
            return [];
        }

        $results = $this->inMemoryCache;

        if (!empty($filters['feeder_name'])) {
            $fName = strtoupper(trim((string)$filters['feeder_name']));
            $results = array_filter($results, fn($r) => str_contains(strtoupper($r['feeder_name'] ?? ''), $fName));
        }

        if (!empty($filters['category'])) {
            $cat = strtoupper(trim((string)$filters['category']));
            $results = array_filter($results, fn($r) => strtoupper($r['interruption_category'] ?? '') === $cat);
        }

        if (!empty($filters['date_from'])) {
            $results = array_filter($results, fn($r) => ($r['event_date'] ?? '') >= $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $results = array_filter($results, fn($r) => ($r['event_date'] ?? '') <= $filters['date_to']);
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        return array_slice(array_values($results), 0, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function getFeederInterruptions(int|string $penyulangIdentifier, array $filters = []): array
    {
        $filters['feeder_name'] = (string)$penyulangIdentifier;
        return $this->getInterruptions($filters);
    }

    /**
     * {@inheritdoc}
     */
    public function getInterruptionStats(array $filters = []): array
    {
        $records = $this->getInterruptions(array_merge($filters, ['limit' => 10000]));
        $total = count($records);
        $permanent = 0;
        $temporary = 0;
        $durations = [];
        $totalEns = 0.0;
        $causes = [];

        foreach ($records as $r) {
            if (($r['interruption_category'] ?? '') === 'PERMANENT') {
                $permanent++;
            } else {
                $temporary++;
            }
            $dur = (float)($r['outage_duration_minutes'] ?? 0);
            if ($dur > 0) $durations[] = $dur;
            $totalEns += (float)($r['energy_not_supplied_kwh'] ?? 0);
            $c = $r['cause_canonical_code'] ?? 'UNKNOWN';
            $causes[$c] = ($causes[$c] ?? 0) + 1;
        }

        sort($durations);
        $cnt = count($durations);
        $median = ($cnt > 0) ? $durations[(int)floor($cnt / 2)] : 0.0;
        arsort($causes);

        return [
            'total_events'        => $total,
            'permanent_count'     => $permanent,
            'temporary_count'     => $temporary,
            'median_duration_min' => $median,
            'total_ens_kwh'       => round($totalEns, 2),
            'dominant_causes'     => array_slice($causes, 0, 5, true),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        $available = $this->isAvailable();
        $ext = $available ? strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION)) : null;

        return [
            'provider_class'     => self::class,
            'source_system'      => 'EXTERNAL_SPREADSHEET_' . strtoupper((string)$ext),
            'is_available'       => $available,
            'file_path'          => $this->filePath,
            'file_format'        => $ext,
            'sheet_name'         => $this->selectedSheetName ?? 'DEFAULT',
            'record_count'       => $available ? count($this->getInterruptions(['limit' => 10000])) : 0,
            'status_description' => $available ? "Loaded spreadsheet [{$ext}]: {$this->filePath}" : "Spreadsheet adapter standby (no valid file loaded).",
        ];
    }

    /**
     * Lazily load and normalize rows from CSV/XLS/XLSX file using M-04 normalizer.
     */
    protected function ensureLoaded(): void
    {
        if ($this->loaded || !$this->isAvailable()) {
            return;
        }

        $this->inMemoryCache = [];
        $ext = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv' || $ext === 'txt') {
            $this->loadFromCsv();
        } else {
            $this->loadFromExcel();
        }

        $this->loaded = true;
    }

    /**
     * Fast native streaming CSV loader.
     */
    protected function loadFromCsv(): void
    {
        if (($handle = fopen($this->filePath, 'r')) === false) {
            return;
        }

        $header = null;
        while (($row = fgetcsv($handle, 8192, ',')) !== false) {
            // Skip empty rows
            if (empty(array_filter($row, fn($val) => $val !== null && trim((string)$val) !== ''))) {
                continue;
            }

            if ($header === null) {
                $header = $row;
                continue;
            }

            // If header exists, map row associatively
            $assoc = [];
            foreach ($header as $idx => $hName) {
                $assoc[trim((string)$hName)] = $row[$idx] ?? null;
            }

            // Normalize row via sealed M-04 normalizer
            $norm = $this->normalizer->normalizeRow(!empty($assoc) ? $assoc : $row);
            $this->inMemoryCache[] = $norm;
        }
        fclose($handle);
    }

    /**
     * Multi-format Excel loader (XLS / XLSX) using PhpSpreadsheet.
     */
    protected function loadFromExcel(): void
    {
        if (!class_exists(IOFactory::class)) {
            return;
        }

        try {
            $reader = IOFactory::createReaderForFile($this->filePath);
            $reader->setReadDataOnly(true);

            if ($this->selectedSheetName && method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$this->selectedSheetName]);
            }

            $spreadsheet = $reader->load($this->filePath);
            $worksheet   = $this->selectedSheetName 
                ? ($spreadsheet->getSheetByName($this->selectedSheetName) ?? $spreadsheet->getActiveSheet())
                : $spreadsheet->getActiveSheet();

            $rows = $worksheet->toArray(null, true, true, false);

            if (empty($rows)) {
                return;
            }

            $header = null;
            foreach ($rows as $row) {
                if (empty(array_filter($row, fn($val) => $val !== null && trim((string)$val) !== ''))) {
                    continue;
                }

                if ($header === null) {
                    $header = $row;
                    continue;
                }

                $assoc = [];
                foreach ($header as $idx => $hName) {
                    $assoc[trim((string)$hName)] = $row[$idx] ?? null;
                }

                $norm = $this->normalizer->normalizeRow(!empty($assoc) ? $assoc : $row);
                $this->inMemoryCache[] = $norm;
            }

            // Free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (\Throwable $e) {
            // Keep memory cache empty on error without crashing
            $this->inMemoryCache = [];
        }
    }
}
