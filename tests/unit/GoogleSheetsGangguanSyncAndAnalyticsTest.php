<?php

namespace Tests\Unit;

use App\Services\GangguanAnalyticsService;
use App\Services\GoogleSheetsGangguanSyncService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\GangguanSync;

/**
 * Verifies Google Sheets sync, cause resolution, 12-item Gangguan analytics,
 * Dashboard Utama data parity, empty/failed sync handling, and secret hygiene.
 */
class GoogleSheetsGangguanSyncAndAnalyticsTest extends CIUnitTestCase
{
    protected BaseConnection $conn;
    protected string $statusFile;

    protected function setUp(): void
    {
        parent::setUp();
        helper(['form', 'url', 'app', 'auth']);

        $this->conn = \Config\Database::connect();
        $forge    = \Config\Database::forge();
        cache()->clean();

        $this->statusFile = sys_get_temp_dir() . '/gangguan_sync_test_' . uniqid('', true) . '.json';

        $forge->dropTable('historical_feeder_interruptions', true);
        $forge->dropTable('interruption_import_batches', true);
        $forge->dropTable('cause_code_dictionary', true);
        $forge->dropTable('temuan', true);
        $forge->dropTable('penyulang', true);
        $forge->dropTable('ulps', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'ulp_id'     => ['type' => 'INTEGER', 'default' => 1],
            'prioritas'  => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'LOW'],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'OPEN'],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('temuan', true);

        $forge->addField([
            'id'       => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
        ]);
        $forge->createTable('ulps', true);
        $this->conn->table('ulps')->insertBatch([
            ['id' => 1, 'kode_ulp' => 'KRN', 'nama_ulp' => 'ULP Krian', 'status' => 'AKTIF'],
            ['id' => 2, 'kode_ulp' => 'SDA', 'nama_ulp' => 'ULP Sidoarjo Kota', 'status' => 'AKTIF'],
            ['id' => 3, 'kode_ulp' => 'PRG', 'nama_ulp' => 'ULP Porong', 'status' => 'AKTIF'],
        ]);

        $forge->addField([
            'id'             => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'ulp_id'         => ['type' => 'INTEGER', 'default' => 1],
            'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
        ]);
        $forge->createTable('penyulang', true);
        $this->conn->table('penyulang')->insertBatch([
            ['id' => 10, 'ulp_id' => 1, 'kode_penyulang' => 'WNY', 'nama_penyulang' => 'WONOAYU'],
            ['id' => 11, 'ulp_id' => 1, 'kode_penyulang' => 'SML', 'nama_penyulang' => 'SAMALEAK'],
            ['id' => 12, 'ulp_id' => 2, 'kode_penyulang' => 'HVL', 'nama_penyulang' => 'HEAVEN LAND'],
            ['id' => 13, 'ulp_id' => 3, 'kode_penyulang' => 'JKN', 'nama_penyulang' => 'JIKEN'],
        ]);

        $forge->addField([
            'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'source_raw_value'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'canonical_cause_code' => ['type' => 'VARCHAR', 'constraint' => 100],
            'cause_category'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'cause_label'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'mapping_confidence'   => ['type' => 'REAL', 'default' => 1.00],
            'is_active'            => ['type' => 'INTEGER', 'default' => 1],
        ]);
        $forge->createTable('cause_code_dictionary', true);
        $this->conn->table('cause_code_dictionary')->insertBatch([
            ['source_raw_value' => 'BINATANG', 'canonical_cause_code' => 'CAUSE_ANIMAL_CONTACT', 'cause_category' => 'ANIMAL_CONTACT', 'cause_label' => 'Binatang', 'mapping_confidence' => 1.0, 'is_active' => 1],
            ['source_raw_value' => 'ROW', 'canonical_cause_code' => 'CAUSE_VEGETATION_ROW', 'cause_category' => 'VEGETATION_ROW', 'cause_label' => 'Pohon / ROW', 'mapping_confidence' => 1.0, 'is_active' => 1],
            ['source_raw_value' => 'MATERIAL', 'canonical_cause_code' => 'CAUSE_EQUIPMENT_FAILURE', 'cause_category' => 'EQUIPMENT_FAILURE', 'cause_label' => 'Material Distribusi', 'mapping_confidence' => 1.0, 'is_active' => 1],
        ]);

        $forge->addField([
            'id'                   => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'batch_code'           => ['type' => 'VARCHAR', 'constraint' => 80],
            'source_system'        => ['type' => 'VARCHAR', 'constraint' => 80],
            'source_filename'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'source_sheet_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'total_rows_read'      => ['type' => 'INTEGER', 'default' => 0],
            'total_rows_imported'  => ['type' => 'INTEGER', 'default' => 0],
            'total_rows_duplicate' => ['type' => 'INTEGER', 'default' => 0],
            'total_rows_flagged'   => ['type' => 'INTEGER', 'default' => 0],
            'batch_checksum'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'status'               => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'COMPLETED'],
            'imported_by'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'notes'                => ['type' => 'TEXT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('interruption_import_batches', true);

        $forge->addField([
            'id'                      => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
            'batch_id'                => ['type' => 'INTEGER', 'default' => 0],
            'source_system'           => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_sheet_name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'source_row_number'       => ['type' => 'INTEGER', 'default' => 0],
            'source_record_hash'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'raw_payload_json'        => ['type' => 'TEXT', 'null' => true],
            'event_date'              => ['type' => 'DATE', 'null' => true],
            'interruption_started_at' => ['type' => 'DATETIME', 'null' => true],
            'interruption_ended_at'   => ['type' => 'DATETIME', 'null' => true],
            'masuk_total_raw'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'outage_duration_minutes' => ['type' => 'REAL', 'null' => true],
            'lama_padam_raw'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'substation_name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'up3_name'                => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'ulp_name'                => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'ulp_id'                  => ['type' => 'INTEGER', 'null' => true],
            'feeder_name'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'penyulang_id'            => ['type' => 'INTEGER', 'null' => true],
            'switching_device_type'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'device_name'             => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'relay_trip_type'         => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'faulted_phase'           => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'weather_condition'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'fault_current_amperes'   => ['type' => 'REAL', 'null' => true],
            'energy_not_supplied_kwh' => ['type' => 'REAL', 'null' => true],
            'interruption_category'   => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'TEMPORARY'],
            'kategori_raw'            => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'interruption_group'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'cause_raw'               => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'cause_canonical_code'    => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'cause_category'          => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'cause_mapping_status'    => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'RESOLVED'],
            'field_narrative_raw'     => ['type' => 'TEXT', 'null' => true],
            'restoration_action_raw'  => ['type' => 'TEXT', 'null' => true],
            'extracted_zone_section'  => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'extracted_distance_kms'  => ['type' => 'REAL', 'null' => true],
            'asset_reference_raw'     => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'data_quality_score'      => ['type' => 'REAL', 'default' => 1.0],
            'ingestion_status'        => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'VALID'],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable('historical_feeder_interruptions', true);
    }

    protected function tearDown(): void
    {
        if (isset($this->statusFile) && is_file($this->statusFile)) {
            @unlink($this->statusFile);
        }
        parent::tearDown();
    }

    protected function buildSampleCsv(bool $appendExtra2026Row = false): string
    {
        $headers = [
            ' ', 'tanggal', 'tgl ke-', 'bln ke-', 'thn', 'GARDU INDUK', 'ULP', 'PMT-RECL/PMCB',
            'PENYULANG', 'Nama switching', 'RECLOSER', 'TGL PMT Trip', 'JAM PMT Lepas',
            'TGL PMT Masuk', 'JAM PMT Masuk', 'masuk total', 'LAMA PADAM', '( AMPER )', '( Kwh )',
            'KATEGORI', 'RELE KERJA', 'fasa', 'cuaca', 'KONDISI PADAM', 'KELOMPOK GGN',
            'JENIS GANGGUAN', 'KETERANGAN', 'TINDAK LANJUT', 'Penyebab', 'Nilai', 'PENYEBAB SESUAI KODE GANGGUAN',
        ];

        $rows = [
            // 2026 rows (6 rows: 4 Krian, 1 Sidoarjo Kota, 1 Porong; Permanen=2, Temporer=4)
            ['SDA', '2026-01-05', '5', '1', '2026', 'WARU', 'KRIAN', 'RECL-PMCB', 'WONOAYU', '', 'RECL PDAM KRIAN', '05-Jan-26', '06:10', '05-Jan-26', '06:22', '06:22', '00:12', '240,5', '412,30', 'Temporer', 'DGR', 'R-N', 'CERAH', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Kelelawar di tiang T21 Zona 1 Section 2', 'Reclose masuk', 'BINATANG', '1', 'BINATANG'],
            ['SDA', '2026-02-11', '11', '2', '2026', 'KRIAN', 'KRIAN', 'RECL-PMCB', 'SAMALEAK', '', 'PMCB BELAHANREJO', '11-Feb-26', '13:40', '11-Feb-26', '13:55', '13:55', '00:15', '310', '620,10', 'Permanen', 'OCR-INST', 'S-T', 'HUJAN', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Jumper FCO lepas jarak 4.2 km', 'Penggantian FCO', 'MATERIAL', '1', 'MATRIAL'],
            ['SDA', '2026-03-19', '19', '3', '2026', 'BUDURAN', 'SIDOARJO KOTA', 'PMT', 'HEAVEN LAND', '', 'PMT', '19-Mar-26', '19:15', '19-Mar-26', '19:20', '19:20', '00:05', '510', '1240,00', 'Temporer', 'OCR', 'R-S', 'CERAH', 'TOTAL', 'EKSTERNAL', 'GANGGUAN', 'Ranting bambu sentuh SUTM', 'Rabas pohon ROW', 'ROW', '1', 'ROW'],
            ['SDA', '2026-04-02', '2', '4', '2026', 'PORONG', 'PORONG', 'RECL-PMCB', 'JIKEN', '', 'GH SAFLOCK', '02-Apr-26', '21:00', '02-Apr-26', '22:10', '22:10', '01:10', '420', '890,50', 'Permanen', '', 'T-N', 'HUJAN', 'SEBAGIAN', 'INTERNAL', 'GANGGUAN', 'Tidak ditemukan bekas gangguan saat inspeksi', 'Normalisasi LBS', 'BELUM DIKETEMUKAN', '1', 'TIDAK DITEMUKAN'],
            ['SDA', '2026-04-15', '15', '4', '2026', 'WARU', 'KRIAN', 'PMT', 'WONOAYU', '', 'PMT', '15-Apr-26', '08:05', '15-Apr-26', '08:11', '08:11', '00:06', '480', '710,00', 'Temporer', 'DGR', 'R-N', 'CERAH', 'TOTAL', 'EKSTERNAL', 'GANGGUAN', 'Burung di isolator', 'Penormalan PMT', 'BINATANG', '1', 'BINATANG'],
            ['SDA', '2026-05-09', '9', '5', '2026', 'KRIAN', 'KRIAN', 'RECL-PMCB', 'WONOAYU', '', 'RECL PDAM KRIAN', '09-May-26', '17:20', '09-May-26', '17:28', '17:28', '00:08', '295', '350,00', 'Temporer', 'DGR', 'S-N', 'ANGIN', 'SEBAGIAN', 'ALAM', 'GANGGUAN', 'Angin kencang', 'Reclose normal', 'CUACA', '1', 'FORCE MAJURE'],

            // 2025 rows (4 rows: Jan, Mar, May, Dec — so comparable Jan-May 2025 = 3 rows vs 6 in 2026, full 2025 = 4)
            ['SDA', '2025-01-12', '12', '1', '2025', 'KRIAN', 'KRIAN', 'RECL-PMCB', 'SAMALEAK', '', 'RECL PDAM KRIAN', '12-Jan-25', '10:00', '12-Jan-25', '10:15', '10:15', '00:15', '200', '180,00', 'Temporer', 'DGR', 'R-N', 'CERAH', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Ular di tiang', 'Normalisasi', 'BINATANG', '1', 'BINATANG'],
            ['SDA', '2025-03-08', '8', '3', '2025', 'BUDURAN', 'SIDOARJO KOTA', 'RECL-PMCB', 'HEAVEN LAND', '', 'PMCB BUDURAN', '08-Mar-25', '14:00', '08-Mar-25', '14:30', '14:30', '00:30', '350', '510,00', 'Permanen', 'OCR-INST', 'R-S-T', 'HUJAN', 'SEBAGIAN', 'INTERNAL', 'GANGGUAN', 'Beban puncak trafo', 'Atur beban', 'OVERLOAD', '1', 'OVER LOAD'],
            ['SDA', '2025-05-20', '20', '5', '2025', 'PORONG', 'PORONG', 'PMT', 'JIKEN', '', 'PMT', '20-May-25', '16:10', '20-May-25', '16:18', '16:18', '00:08', '610', '940,00', 'Temporer', 'DGR', 'S-N', 'CERAH', 'TOTAL', 'EKSTERNAL', 'GANGGUAN', 'Layang-layang', 'Bersihkan benang', 'BINATANG', '1', 'BINATANG'],
            ['SDA', '2025-12-18', '18', '12', '2025', 'KRIAN', 'KRIAN', 'RECL-PMCB', 'WONOAYU', '', 'RECL PDAM KRIAN', '18-Dec-25', '20:00', '18-Dec-25', '20:45', '20:45', '00:45', '390', '680,00', 'Permanen', 'OCR', 'T-N', 'HUJAN', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Pohon sengon roboh', 'Potong pohon', 'ROW', '1', 'ROW'],
        ];

        if ($appendExtra2026Row) {
            $rows[] = ['SDA', '2026-06-01', '1', '6', '2026', 'KRIAN', 'KRIAN', 'RECL-PMCB', 'SAMALEAK', '', 'PMCB BELAHANREJO', '01-Jun-26', '09:10', '01-Jun-26', '09:20', '09:20', '00:10', '260', '210,00', 'Temporer', 'DGR', 'R-N', 'CERAH', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Tupai menyentuh jumper', 'Reclose masuk', 'BINATANG', '1', 'BINATANG'];
        }

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $headers);
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        rewind($fp);
        $csv = (string)stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }

    public function testSyncReplacesSpreadsheetRowsPreservesDirectEntriesAndPropagatesUpdates(): void
    {
        // Seed manual entry, custom XLSX, NULL source, legacy 2024 row, and legacy 2025 snapshot row:
        // NONE of these non-GOOGLE_SHEETS_SYNC rows may be deleted during sync.
        $this->conn->table('historical_feeder_interruptions')->insertBatch([
            [
                'batch_id'              => 0,
                'source_system'         => 'SIDAK_DIRECT_ENTRY',
                'source_record_hash'    => str_repeat('a', 64),
                'event_date'            => '2026-01-01',
                'ulp_name'              => 'KRIAN',
                'ulp_id'                => 1,
                'feeder_name'           => 'WONOAYU',
                'penyulang_id'          => 10,
                'switching_device_type' => 'PMT',
                'device_name'           => 'PMT',
                'relay_trip_type'       => 'DGR',
                'interruption_category' => 'TEMPORARY',
                'cause_raw'             => 'BINATANG',
                'cause_canonical_code'  => 'CAUSE_ANIMAL_CONTACT',
                'cause_category'        => 'ANIMAL_CONTACT',
                'cause_mapping_status'  => 'RESOLVED',
            ],
            [
                'batch_id'              => 91,
                'source_system'         => 'GOOGLE_SPREADSHEET_PLN_SDA',
                'source_record_hash'    => str_repeat('b', 64),
                'event_date'            => '2025-07-07',
                'ulp_name'              => 'PORONG',
                'ulp_id'                => 3,
                'feeder_name'           => 'LEGACY_2025_SNAPSHOT',
                'penyulang_id'          => null,
                'switching_device_type' => 'PMT',
                'device_name'           => 'PMT',
                'relay_trip_type'       => 'OCR',
                'interruption_category' => 'PERMANENT',
                'cause_raw'             => 'BINATANG',
                'cause_canonical_code'  => 'CAUSE_ANIMAL_CONTACT',
                'cause_category'        => 'ANIMAL_CONTACT',
                'cause_mapping_status'  => 'RESOLVED',
            ],
            [
                'batch_id'              => 92,
                'source_system'         => 'GOOGLE_SPREADSHEET_PLN_SDA',
                'source_record_hash'    => str_repeat('c', 64),
                'event_date'            => '2024-08-17',
                'ulp_name'              => 'KRIAN',
                'ulp_id'                => 1,
                'feeder_name'           => 'WONOAYU',
                'penyulang_id'          => 10,
                'switching_device_type' => 'PMT',
                'device_name'           => 'PMT',
                'relay_trip_type'       => 'DGR',
                'interruption_category' => 'TEMPORARY',
                'cause_raw'             => 'ROW',
                'cause_canonical_code'  => 'CAUSE_VEGETATION_ROW',
                'cause_category'        => 'VEGETATION_ROW',
                'cause_mapping_status'  => 'RESOLVED',
            ],
            [
                'batch_id'              => 93,
                'source_system'         => 'EXCEL_WORKBOOK_IMPORT',
                'source_record_hash'    => str_repeat('d', 64),
                'event_date'            => '2026-12-10',
                'ulp_name'              => 'SIDOARJO KOTA',
                'ulp_id'                => 2,
                'feeder_name'           => 'HEAVEN LAND',
                'penyulang_id'          => 12,
                'switching_device_type' => 'RECL-PMCB',
                'device_name'           => 'PMCB BUDURAN',
                'relay_trip_type'       => 'DGR',
                'interruption_category' => 'TEMPORARY',
                'cause_raw'             => 'BINATANG',
                'cause_canonical_code'  => 'CAUSE_ANIMAL_CONTACT',
                'cause_category'        => 'ANIMAL_CONTACT',
                'cause_mapping_status'  => 'RESOLVED',
            ],
            [
                'batch_id'              => 0,
                'source_system'         => null,
                'source_record_hash'    => str_repeat('e', 64),
                'event_date'            => '2026-12-11',
                'ulp_name'              => 'PORONG',
                'ulp_id'                => 3,
                'feeder_name'           => 'JIKEN',
                'penyulang_id'          => 13,
                'switching_device_type' => 'PMT',
                'device_name'           => 'PMT',
                'relay_trip_type'       => 'OCR',
                'interruption_category' => 'PERMANENT',
                'cause_raw'             => 'MATERIAL',
                'cause_canonical_code'  => 'CAUSE_EQUIPMENT_FAILURE',
                'cause_category'        => 'EQUIPMENT_FAILURE',
                'cause_mapping_status'  => 'RESOLVED',
            ],
        ]);

        $csvV1   = $this->buildSampleCsv(false);
        $csvV2   = $this->buildSampleCsv(true);
        $current = &$csvV1;

        $sync = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            static function () use (&$current): string {
                return $current;
            },
            $this->statusFile
        );

        $st1 = $sync->ensureSynced(false);
        $this->assertSame('SYNCED', $st1['status'], (string)($st1['last_error'] ?? ''));
        $this->assertTrue($st1['changed']);
        $this->assertSame(10, $st1['row_count']);

        // Every non-GOOGLE_SHEETS_SYNC row remains untouched in DB (5 pre-existing + 10 synced = 15 total)
        $this->assertSame(1, $this->conn->table('historical_feeder_interruptions')->where('feeder_name', 'LEGACY_2025_SNAPSHOT')->countAllResults());
        $this->assertSame(1, $this->conn->table('historical_feeder_interruptions')->where('source_system', 'SIDAK_DIRECT_ENTRY')->countAllResults());
        $this->assertSame(1, $this->conn->table('historical_feeder_interruptions')->where('source_system', 'EXCEL_WORKBOOK_IMPORT')->countAllResults());
        $this->assertSame(1, $this->conn->table('historical_feeder_interruptions')->where('source_system IS NULL', null, false)->countAllResults());
        $this->assertSame(1, $this->conn->table('historical_feeder_interruptions')->where('event_date', '2024-08-17')->countAllResults());
        $this->assertSame(15, $this->conn->table('historical_feeder_interruptions')->countAllResults());

        // Static aliases ('MATRIAL', 'OVER LOAD', 'TIDAK DITEMUKAN', 'FORCE MAJURE') must resolve cleanly
        $unmappedCount = $this->conn->table('historical_feeder_interruptions')
            ->where('cause_mapping_status', 'UNMAPPED')
            ->countAllResults();
        $this->assertSame(0, $unmappedCount);

        // Second sync with unchanged CSV short-circuits
        $st2 = $sync->syncFromGoogleSheets();
        $this->assertSame('SYNCED', $st2['status'], (string)($st2['last_error'] ?? ''));
        $this->assertFalse($st2['changed']);

        // Append new row in spreadsheet -> sync replaces ONLY GOOGLE_SHEETS_SYNC (10 -> 11) + keeps all 5 others (= 16 in DB)
        $current = $csvV2;
        $st3 = $sync->ensureSynced(true);
        $this->assertSame('SYNCED', $st3['status'], (string)($st3['last_error'] ?? ''));
        $this->assertTrue($st3['changed']);
        $this->assertSame(11, $st3['row_count']);
        $this->assertSame(16, $this->conn->table('historical_feeder_interruptions')->countAllResults());

        $analytics = new GangguanAnalyticsService($this->conn);
        $ctx = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'year_a'], 'administrator', null);
        // Legacy year 2024 stays discoverable
        $this->assertContains(2024, $ctx['available_years']);

        $g12 = $analytics->getGangguanAnalytics12Items($ctx);
        $dash = $analytics->getDashboardSyncPayload($ctx, [2026, 2025, 2024]);

        // 2026: 1 SIDAK_DIRECT_ENTRY + 1 custom EXCEL_WORKBOOK_IMPORT + 1 manual NULL + 7 synced = 10 trips
        // 2025: 4 synced rows (LEGACY_2025_SNAPSHOT excluded in analytics so it doesn't double-count, yet kept in DB)
        $this->assertSame(10, $g12['total_gangguan']['year_a_total']);
        $this->assertSame(4, $g12['total_gangguan']['year_b_total']);
        $this->assertSame($g12['total_gangguan']['year_a_total'], $dash['gangguanStats']['current_ytd']);
        $this->assertSame($g12['total_gangguan']['year_b_total'], $dash['gangguanStats']['last_ytd']);
        $this->assertSame($g12['total_gangguan']['yoy']['comparable_pct'], $dash['gangguanStats']['yoy_pct']);
        $this->assertSame($g12['top_10_penyulang']['rows'][0]['feeder_name'], $dash['topFeedersGangguan'][0]['feeder_name']);
    }

    public function testOverlappingLegacyExcelBatchIsPreservedInDbWithoutDoubleCounting(): void
    {
        // Simulate an existing pre-sync EXCEL_WORKBOOK_IMPORT batch (6 overlapping 2026 rows like GANGGUAN PENYULANG.xlsx)
        $legacyBatchRows = [
            ['2026-01-05', 'KRIAN', 1, 'WONOAYU', 10, 'PMT', 'PMT', 'DGR', 'TEMPORARY', 'BINATANG'],
            ['2026-02-11', 'KRIAN', 1, 'SAMALEAK', 11, 'RECL-PMCB', 'PMCB BELAHANREJO', 'OCR-INST', 'PERMANENT', 'MATERIAL'],
            ['2026-03-19', 'SIDOARJO KOTA', 2, 'HEAVEN LAND', 12, 'PMT', 'PMT', 'OCR', 'TEMPORARY', 'ROW'],
            ['2026-04-02', 'PORONG', 3, 'JIKEN', 13, 'RECL-PMCB', 'GH SAFLOCK', '', 'PERMANENT', 'BELUM DIKETEMUKAN'],
            ['2026-04-15', 'KRIAN', 1, 'WONOAYU', 10, 'PMT', 'PMT', 'DGR', 'TEMPORARY', 'BINATANG'],
            ['2026-05-09', 'KRIAN', 1, 'WONOAYU', 10, 'RECL-PMCB', 'RECL PDAM KRIAN', 'DGR', 'TEMPORARY', 'CUACA'],
        ];
        $seed = [];
        foreach ($legacyBatchRows as $idx => $lr) {
            $seed[] = [
                'batch_id'              => 55,
                'source_system'         => 'EXCEL_WORKBOOK_IMPORT',
                'source_record_hash'    => hash('sha256', 'legacy_xlsx_' . $idx),
                'event_date'            => $lr[0],
                'ulp_name'              => $lr[1],
                'ulp_id'                => $lr[2],
                'feeder_name'           => $lr[3],
                'penyulang_id'          => $lr[4],
                'switching_device_type' => $lr[5],
                'device_name'           => $lr[6],
                'relay_trip_type'       => $lr[7],
                'interruption_category' => $lr[8],
                'cause_raw'             => $lr[9],
                'cause_canonical_code'  => 'CAUSE_ANIMAL_CONTACT',
                'cause_category'        => 'ANIMAL_CONTACT',
                'cause_mapping_status'  => 'RESOLVED',
            ];
        }
        $this->conn->table('historical_feeder_interruptions')->insertBatch($seed);

        $sync = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            fn(): string => $this->buildSampleCsv(false),
            $this->statusFile
        );
        $st = $sync->syncFromGoogleSheets();
        $this->assertSame('SYNCED', $st['status'], (string)($st['last_error'] ?? ''));

        // Legacy EXCEL_WORKBOOK_IMPORT batch remains 100% intact in DB (6 legacy + 10 synced = 16)
        $this->assertSame(6, $this->conn->table('historical_feeder_interruptions')->where('source_system', 'EXCEL_WORKBOOK_IMPORT')->countAllResults());
        $this->assertSame(16, $this->conn->table('historical_feeder_interruptions')->countAllResults());

        // Yet analytics & dashboard count 6 in 2026 (NOT 6 + 6 = 12)
        $analytics = new GangguanAnalyticsService($this->conn);
        $ctx       = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'year_a'], 'administrator', null);
        $g12       = $analytics->getGangguanAnalytics12Items($ctx);
        $dash      = $analytics->getDashboardSyncPayload($ctx, [2026, 2025]);

        $this->assertSame(6, $g12['total_gangguan']['year_a_total']);
        $this->assertSame(6, $dash['gangguanStats']['current_ytd']);
    }

    public function testSyncFailureRetainsStoredDataAndRedactsSecrets(): void
    {
        $syncOk = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            fn(): string => $this->buildSampleCsv(false),
            $this->statusFile
        );
        $ok = $syncOk->syncFromGoogleSheets();
        $this->assertSame('SYNCED', $ok['status'], (string)($ok['last_error'] ?? ''));
        $this->assertSame(10, $this->conn->table('historical_feeder_interruptions')->countAllResults());

        $syncFail = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            static function (): string {
                throw new \RuntimeException('HTTP 403 failed on https://docs.google.com/spreadsheets/d/1bbY14--JLP4wnCh7NT7tLYK71ZkBGbZabQ3a7YaUEQg/export?token=SECRET_TOKEN_XYZ');
            },
            $this->statusFile
        );

        $fail = $syncFail->ensureSynced(true);
        $this->assertSame('ERROR', $fail['status']);
        $this->assertTrue($fail['using_cached_db']);
        // Stored rows preserved
        $this->assertSame(10, $this->conn->table('historical_feeder_interruptions')->countAllResults());
        // Spreadsheet URL & secret redacted
        $this->assertStringNotContainsString('docs.google.com', $fail['last_error']);
        $this->assertStringNotContainsString('1bbY14--', $fail['last_error']);
        $this->assertStringNotContainsString('SECRET_TOKEN_XYZ', $fail['last_error']);
    }

    public function testAnalytics12ItemsStructureAndDashboardConsistency(): void
    {
        $sync = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            fn(): string => $this->buildSampleCsv(false),
            $this->statusFile
        );
        $sync->syncFromGoogleSheets();

        $analytics = new GangguanAnalyticsService($this->conn);

        // Check Combined scope (2025 + 2026 = 10 records) matching Looker Studio full model
        $ctxComb = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'combined'], 'administrator', null);
        $g12     = $analytics->getGangguanAnalytics12Items($ctxComb);

        // 1. Permanen vs Temporer (4 Permanen, 6 Temporer = 10)
        $this->assertSame(4, $g12['permanen_vs_temporer']['focus']['PERMANENT']);
        $this->assertSame(6, $g12['permanen_vs_temporer']['focus']['TEMPORARY']);

        // 2 & 4. ULP breakdown sums to 10
        $this->assertSame(10, array_sum($g12['gangguan_per_ulp']['focus_counts']));
        $this->assertSame(
            $g12['permanen_vs_temporer']['focus']['PERMANENT'],
            array_sum($g12['permanen_vs_temporer_per_ulp']['permanen'])
        );

        // 5. Relai yang bekerja (includes empty -> 'TIDAK TERCATAT', total = 10)
        $this->assertSame(10, array_sum($g12['relai_bekerja']['focus_counts']));

        // 6. Rasio PMT vs REC/PMCB (PMT=3, RECL-PMCB=7)
        $this->assertSame(10, $g12['rasio_pmt_vs_recl_pmcb']['focus_total']);

        // 7. Pareto Penyebab Gangguan sorted DESC + monotonic cumulative % up to 100.0
        $pareto = $g12['pareto_penyebab']['items'];
        $this->assertNotEmpty($pareto);
        $prevCount = PHP_INT_MAX;
        $prevPct   = 0.0;
        foreach ($pareto as $pRow) {
            $this->assertLessThanOrEqual($prevCount, $pRow['count']);
            $this->assertGreaterThanOrEqual($prevPct, $pRow['cumulative_pct']);
            $prevCount = $pRow['count'];
            $prevPct   = $pRow['cumulative_pct'];
        }
        $this->assertEqualsWithDelta(100.0, end($pareto)['cumulative_pct'], 0.01);

        // 8. Top 10 penyulang
        $this->assertGreaterThanOrEqual(4, count($g12['top_10_penyulang']['rows']));

        // 9. Sebaran non-PMT vs all devices
        $nonPmtNames = array_column($g12['sebaran_pmt_recloser']['non_pmt_devices'], 'device_name');
        $allNames    = array_column($g12['sebaran_pmt_recloser']['all_devices'], 'device_name');
        $this->assertNotEmpty($nonPmtNames);
        $this->assertNotContains('PMT', $nonPmtNames);
        $this->assertContains('PMT', $allNames);

        // 10 & 11. Pivot matrices reconcile to total
        $this->assertSame(10, $g12['detail_penyulang_bulanan']['grand_total_combined']);
        $this->assertSame(10, $g12['penyulang_vs_penyebab']['grand_total']);

        // 12. Equipment families classify PMT, Recloser, PMCB, Key Point
        $famCounts = array_column($g12['detail_bulanan_peralatan']['family_summary'], 'total', 'family');
        $this->assertGreaterThan(0, $famCounts['PMT']);
        $this->assertGreaterThan(0, $famCounts['Recloser']);
        $this->assertGreaterThan(0, $famCounts['PMCB']);
        $this->assertGreaterThan(0, $famCounts['Key Point']);
        $this->assertSame(10, $g12['detail_bulanan_peralatan']['grand_total']);

        // Year A = 2026 parity between /gangguan and /dashboard
        $ctxYrA  = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'year_a'], 'administrator', null);
        $gYearA  = $analytics->getGangguanAnalytics12Items($ctxYrA);
        $dash    = $analytics->getDashboardSyncPayload($ctxYrA, [2026, 2025]);

        $this->assertSame($gYearA['total_gangguan']['year_a_total'], $dash['cumulativeGangguanYoY'][2026]['total']);
        $this->assertSame($gYearA['total_gangguan']['year_b_total'], $dash['cumulativeGangguanYoY'][2025]['total']);
        $this->assertSame($gYearA['total_gangguan']['monthly_a'], $dash['cumulativeGangguanYoY'][2026]['monthly']);
        $this->assertSame($gYearA['total_gangguan']['monthly_b'], $dash['cumulativeGangguanYoY'][2025]['monthly']);
        $this->assertSame($gYearA['total_gangguan']['yoy']['comparable_diff'], $dash['gangguanStats']['comparable_a'] - $dash['gangguanStats']['comparable_b']);
        $this->assertSame($gYearA['total_gangguan']['yoy']['comparable_pct'], $dash['gangguanStats']['yoy_pct']);

        // Switching type totals in Dashboard payload equal /gangguan Item 6
        $dashPmtTotal  = (int)array_sum(array_map('intval', $dash['interruptionMetricsPayload']['device'][2026]['PMT'] ?? []));
        $dashReclTotal = (int)array_sum(array_map('intval', $dash['interruptionMetricsPayload']['device'][2026]['RECL-PMCB'] ?? []));
        $this->assertSame($gYearA['total_gangguan']['year_a_total'], $dashPmtTotal + $dashReclTotal);
    }

    public function testEquipmentFamilyClassifier(): void
    {
        $this->assertSame('PMT', GangguanAnalyticsService::classifyEquipmentFamily('PMT', 'PMT'));
        $this->assertSame('Recloser', GangguanAnalyticsService::classifyEquipmentFamily('RECL PDAM KRIAN', 'RECL-PMCB'));
        $this->assertSame('PMCB', GangguanAnalyticsService::classifyEquipmentFamily('PMCB BELAHANREJO', 'RECL-PMCB'));
        $this->assertSame('Key Point', GangguanAnalyticsService::classifyEquipmentFamily('GH SAFLOCK', 'RECL-PMCB'));
    }

    public function testGangguanViewRendersAll12SectionsWithoutLeakingSecrets(): void
    {
        $sync = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            fn(): string => $this->buildSampleCsv(false),
            $this->statusFile
        );
        $syncStatus = $sync->syncFromGoogleSheets();
        $this->assertSame('SYNCED', $syncStatus['status'], (string)($syncStatus['last_error'] ?? ''));
        // Force an ERROR state banner check too (while preserving valid analytics)
        $syncStatus['status']     = 'ERROR';
        $syncStatus['last_error'] = 'Gangguan sync timeout';

        $analytics = new GangguanAnalyticsService($this->conn);
        $ctx       = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'year_a'], 'administrator', null);
        $g12       = $analytics->getGangguanAnalytics12Items($ctx);
        $yoyInfo   = $g12['total_gangguan']['yoy'];
        $rows      = $g12['filtered_rows'];

        // Minimal session role for view rendering
        session()->set(['user_id' => 1, 'user_role' => 'administrator', 'role' => 'administrator', 'nama' => 'Tester', 'username' => 'tester']);

        $html = view('gangguan/index', [
            'canManage'      => true,
            'yearA'          => 2026,
            'yearB'          => 2025,
            'scope'          => 'year_a',
            'ctx'            => $ctx,
            'ulpIdFilter'    => null,
            'penyulangId'    => null,
            'yearFilter'     => null,
            'categoryFilter' => null,
            'search'         => '',
            'availableYears' => [2026, 2025],
            'ulps'           => [['id' => 1, 'nama_ulp' => 'ULP Krian']],
            'penyulangs'     => [['id' => 10, 'nama_penyulang' => 'WONOAYU', 'ulp_id' => 1]],
            'syncStatus'     => $syncStatus,
            'analytics12'    => $g12,
            'totalA'         => $yoyInfo['comparable_total_a'],
            'totalB'         => $yoyInfo['comparable_total_b'],
            'selisih'        => $yoyInfo['comparable_diff'],
            'pctChange'      => $yoyInfo['comparable_pct'],
            'monthlyA'       => $g12['total_gangguan']['monthly_a'],
            'monthlyB'       => $g12['total_gangguan']['monthly_b'],
            'periodInfo'     => $yoyInfo,
            'top10Merged'    => $g12['top_10_penyulang']['rows'],
            'items'          => array_slice($rows, 0, 25),
            'totalCount'     => count($rows),
            'totalKwh'       => 100.5,
            'dgrCount'       => 5,
            'ocrCount'       => 3,
            'tempCount'      => 6,
            'permCount'      => 4,
            'page'           => 1,
            'perPage'        => 25,
            'totalPages'     => 1,
            'offset'         => 0,
        ]);

        foreach ([
            'chartPermTemp', 'chartUlp', 'chartPermTempUlp', 'chartRelai',
            'chartPmtRecl', 'chartParetoPenyebab', 'chartTop10Penyulang',
            'chartSebaranPeralatan', 'tableItem10', 'tableItem11', 'tableItem12',
            'tableParetoPenyebab', 'paretoThreshold80', 'pareto_cumulative', 'pareto_cause',
            'gangguanDrilldownModal', 'openGangguanDrilldown', 'gangguan/drilldown',
            'gangguan/sync-sheets', 'Gangguan sync timeout',
        ] as $expectedId) {
            $this->assertStringContainsString($expectedId, $html);
        }

        // Security invariant: raw Google Sheet ID / export URL never exposed in public HTML
        $this->assertStringNotContainsString('1bbY14--JLP4wnCh7NT7tLYK71ZkBGbZabQ3a7YaUEQg', $html);
        $this->assertStringNotContainsString('export?format=csv', $html);

        // Verify empty-data render condition cleanly displays empty alerts
        $this->conn->table('historical_feeder_interruptions')->truncate();
        $emptyAnalytics = new GangguanAnalyticsService($this->conn);
        $emptyCtx       = $emptyAnalytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'year_a'], 'administrator', null);
        $empty12        = $emptyAnalytics->getGangguanAnalytics12Items($emptyCtx);
        $emptyYoy       = $empty12['total_gangguan']['yoy'];
        $emptyHtml      = view('gangguan/index', [
            'canManage'      => true,
            'yearA'          => 2026,
            'yearB'          => 2025,
            'scope'          => 'year_a',
            'ctx'            => $emptyCtx,
            'ulpIdFilter'    => null,
            'penyulangId'    => null,
            'yearFilter'     => null,
            'categoryFilter' => null,
            'search'         => '',
            'availableYears' => [2026, 2025],
            'ulps'           => [['id' => 1, 'nama_ulp' => 'ULP Krian']],
            'penyulangs'     => [],
            'syncStatus'     => ['status' => 'EMPTY', 'last_error' => null, 'last_synced_at' => null, 'row_count' => 0, 'source_label' => 'Google Sheets'],
            'analytics12'    => $empty12,
            'totalA'         => $emptyYoy['comparable_total_a'],
            'totalB'         => $emptyYoy['comparable_total_b'],
            'selisih'        => $emptyYoy['comparable_diff'],
            'pctChange'      => $emptyYoy['comparable_pct'],
            'monthlyA'       => $empty12['total_gangguan']['monthly_a'],
            'monthlyB'       => $empty12['total_gangguan']['monthly_b'],
            'periodInfo'     => $emptyYoy,
            'top10Merged'    => [],
            'items'          => [],
            'totalCount'     => 0,
            'totalKwh'       => 0.0,
            'dgrCount'       => 0,
            'ocrCount'       => 0,
            'tempCount'      => 0,
            'permCount'      => 0,
            'page'           => 1,
            'perPage'        => 25,
            'totalPages'     => 1,
            'offset'         => 0,
        ]);
        $this->assertStringContainsString('Belum ada data', $emptyHtml);

        // Verify Dashboard Utama view source preserves shared Gangguan controls & interactivity without secrets
        $dashSource = (string)file_get_contents(APPPATH . 'Views/dashboard/index.php');
        foreach ([
            'Sinkronkan Sheets', 'yoyYearA', 'yoyYearB', 'topFeedersGangguanTableBody',
            'causeBreakdownTableBody', 'onDashboardGangguanYearPairChange',
            '_gangguan_drilldown_modal', 'openGangguanDrilldown',
            'dash-top-feeder-row', 'dash-cause-row', 'fault_current_avg',
        ] as $marker) {
            $this->assertStringContainsString($marker, $dashSource);
        }
        $this->assertStringNotContainsString('1bbY14--JLP4wnCh7NT7tLYK71ZkBGbZabQ3a7YaUEQg', $dashSource);
    }

    public function testParetoPrecisionEmptyHandlingExact80BoundaryAndDrilldowns(): void
    {
        // 5 events in 2026: A=4 (80.0% exact!), B=1 (20.0%) -> A is Vital <=80%, B starts AT 80.0% so NOT Vital!
        // Plus case/whitespace variants ('konduktor ', 'KONDUKTOR') and truly empty cause -> 'TIDAK TERCATAT'
        $headers = [
            ' ', 'tanggal', 'tgl ke-', 'bln ke-', 'thn', 'GARDU INDUK', 'ULP', 'PMT-RECL/PMCB',
            'PENYULANG', 'Nama switching', 'RECLOSER', 'TGL PMT Trip', 'JAM PMT Lepas',
            'TGL PMT Masuk', 'JAM PMT Masuk', 'masuk total', 'LAMA PADAM', '( AMPER )', '( Kwh )',
            'KATEGORI', 'RELE KERJA', 'fasa', 'cuaca', 'KONDISI PADAM', 'KELOMPOK GGN',
            'JENIS GANGGUAN', 'KETERANGAN', 'TINDAK LANJUT', 'Penyebab', 'Nilai', 'PENYEBAB SESUAI KODE GANGGUAN',
        ];
        $rowsV1 = [
            ['SDA', '2026-01-05', '5', '1', '2026', 'WARU', 'KRIAN', 'RECL-PMCB', 'WONOAYU', '', 'RECL PDAM KRIAN', '05-Jan-26', '06:10', '05-Jan-26', '06:22', '06:22', '00:12', '240', '100', 'Temporer', 'DGR', 'R-N', 'CERAH', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Desc 1', 'Act 1', 'KONDUKTOR', '1', 'konduktor '],
            ['SDA', '2026-01-06', '6', '1', '2026', 'WARU', 'KRIAN', 'RECL-PMCB', 'WONOAYU', '', 'RECL PDAM KRIAN', '06-Jan-26', '07:10', '06-Jan-26', '07:20', '07:20', '00:10', '260', '120', 'Temporer', 'DGR', 'R-N', 'CERAH', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Desc 2', 'Act 2', 'KONDUKTOR', '1', '  KONDUKTOR'],
            ['SDA', '2026-02-07', '7', '2', '2026', 'WARU', 'KRIAN', 'PMT', 'SAMALEAK', '', 'PMT', '07-Feb-26', '08:00', '07-Feb-26', '08:05', '08:05', '00:05', '400', '200', 'Permanen', 'OCR-INST', 'S-T', 'HUJAN', 'TOTAL', 'EKSTERNAL', 'GANGGUAN', 'Desc 3', 'Act 3', 'KONDUKTOR', '1', 'KONDUKTOR'],
            ['SDA', '2026-02-08', '8', '2', '2026', 'BUDURAN', 'SIDOARJO KOTA', 'RECL-PMCB', 'HEAVEN LAND', '', 'PMCB BUDURAN', '08-Feb-26', '09:00', '08-Feb-26', '09:10', '09:10', '00:10', '300', '150', 'Temporer', 'DGR', 'T-N', 'CERAH', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Desc 4', 'Act 4', 'KONDUKTOR', '1', 'KONDUKTOR'],
            ['SDA', '2026-03-09', '9', '3', '2026', 'BUDURAN', 'SIDOARJO KOTA', 'PMT', 'HEAVEN LAND', '', 'PMT', '09-Mar-26', '10:00', '09-Mar-26', '10:15', '10:15', '00:15', '500', '250', 'Permanen', 'OCR', 'R-S', 'HUJAN', 'TOTAL', 'INTERNAL', 'GANGGUAN', 'Desc 5', 'Act 5', '', '1', ''],
        ];
        // Rows V2 only change non-cause column KELOMPOK GGN on row 5 -> must still trigger sync update!
        $rowsV2 = $rowsV1;
        $rowsV2[4][24] = 'EKSTERNAL';

        $toCsv = static function (array $hdr, array $rows): string {
            $fp = fopen('php://temp', 'r+');
            fputcsv($fp, $hdr);
            foreach ($rows as $r) {
                fputcsv($fp, $r);
            }
            rewind($fp);
            $out = (string)stream_get_contents($fp);
            fclose($fp);
            return $out;
        };

        $csv1 = $toCsv($headers, $rowsV1);
        $csv2 = $toCsv($headers, $rowsV2);
        $cur  = &$csv1;

        $sync = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            static function () use (&$cur): string {
                return $cur;
            },
            $this->statusFile
        );

        $st1 = $sync->ensureSynced(false);
        $this->assertSame('SYNCED', $st1['status'], (string)($st1['last_error'] ?? ''));
        $this->assertTrue($st1['changed']);

        // Edit in non-cause column triggers changed = true
        $cur = $csv2;
        $st2 = $sync->ensureSynced(true);
        $this->assertSame('SYNCED', $st2['status'], (string)($st2['last_error'] ?? ''));
        $this->assertTrue($st2['changed']);

        $analytics = new GangguanAnalyticsService($this->conn);
        $ctxGlobal = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'year_a'], 'administrator', null);
        $g12       = $analytics->getGangguanAnalytics12Items($ctxGlobal);
        $pareto    = $g12['pareto_penyebab'];

        $this->assertSame(5, $pareto['total']);
        $this->assertSame(2, $pareto['category_count']);
        $this->assertSame(100.0, $pareto['sum_pct']);
        $this->assertSame(100.0, $pareto['final_cumulative_pct']);

        // Case/whitespace collapsed -> KONDUKTOR = 4 (80.0%, Vital), empty cause -> TIDAK TERCATAT = 1 (20.0%, NOT Vital)
        $this->assertSame('KONDUKTOR', $pareto['items'][0]['cause_raw']);
        $this->assertSame(4, $pareto['items'][0]['count']);
        $this->assertTrue($pareto['items'][0]['is_vital_80']);

        $this->assertSame('TIDAK TERCATAT', $pareto['items'][1]['cause_raw']);
        $this->assertSame(1, $pareto['items'][1]['count']);
        $this->assertFalse($pareto['items'][1]['is_vital_80']);
        $this->assertSame(1, $pareto['vital_80_count']);
        $this->assertSame(80.0, $pareto['vital_80_pct']);

        // Drill-down: pareto_cause vs pareto_cumulative rank #2
        $dCause = $analytics->getDrilldownDetails($ctxGlobal, 'pareto_cause', 'KONDUKTOR');
        $this->assertSame(4, $dCause['total_matching']);
        $this->assertCount(4, $dCause['events']);

        $dCum = $analytics->getDrilldownDetails($ctxGlobal, 'pareto_cumulative', 'TIDAK TERCATAT', ['rank' => 2]);
        $this->assertSame(5, $dCum['total_matching']);
        $this->assertCount(2, $dCum['pareto_sequence']);
        $this->assertSame(100.0, $dCum['pareto_point']['cumulative_pct']);

        // Drill-down: month + fault current min/avg/max
        $dMonth = $analytics->getDrilldownDetails($ctxGlobal, 'month', '2', ['target_year' => 2026, 'metric_focus' => 'current']);
        $this->assertSame(2, $dMonth['total_matching']);
        $this->assertSame(350.0, $dMonth['breakdowns']['fault_current']['avg']);
        $this->assertSame(300.0, $dMonth['breakdowns']['fault_current']['min']);
        $this->assertSame(400.0, $dMonth['breakdowns']['fault_current']['max']);

        // Role security check: ULP 2 user passing ?ulp_id=1 cannot escape ULP 2 scope
        $ctxUlp2 = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'ulp_id' => 1], 'admin_ulp', 2);
        $this->assertSame(2, $ctxUlp2['ulp_id']);
        $dUlp2 = $analytics->getDrilldownDetails($ctxUlp2, 'pareto_cause', 'KONDUKTOR');
        $this->assertSame(1, $dUlp2['total_matching']);
        $this->assertSame('SIDOARJO KOTA', $dUlp2['events'][0]['ulp_name']);

        // Event-weighted Ampere average check in Dashboard payload ((240+260+400+300+500)/5 = 340.0)
        $dash = $analytics->getDashboardSyncPayload($ctxGlobal, [2026, 2025]);
        $this->assertSame(340.0, $dash['interruptionMetricsPayload']['fault_current_avg'][2026]);
    }

    public function testEnsMultiDotParsingMonthlyAnnualReconciliationAndDrilldown(): void
    {
        $headers = [
            ' ', 'tanggal', 'tgl ke-', 'bln ke-', 'thn', 'GARDU INDUK', 'ULP', 'PMT-RECL/PMCB',
            'PENYULANG', 'Nama switching', 'RECLOSER', 'TGL PMT Trip', 'JAM PMT Lepas',
            'TGL PMT Masuk', 'JAM PMT Masuk', 'masuk total', 'LAMA PADAM', '( AMPER )', '( Kwh )',
            'KATEGORI', 'RELE KERJA', 'fasa', 'cuaca', 'KONDISI PADAM', 'KELOMPOK GGN',
            'JENIS GANGGUAN', 'KETERANGAN', 'TINDAK LANJUT', 'Penyebab', 'Nilai', 'PENYEBAB SESUAI KODE GANGGUAN',
        ];
        // Test multi-dot ("7.914.779" => 7914.779 kWh), single-dot ("198.262"), comma-decimal ("304,264")
        $rows = [
            ['SDA', '2025-09-27', '27', '9', '2025', 'KRIAN', 'KRIAN', 'PMT', 'WONOAYU', '', 'PMT', '27-Sep-25', '10:00', '27-Sep-25', '11:04', '11:04', '1:04:00', '252', '7.914.779', 'Permanen', 'OCR', 'R-S', 'CERAH', 'TOTAL', 'EKSTERNAL', 'GANGGUAN', 'Sep 25 big', 'Act', 'BINATANG', '1', 'BINATANG'],
            ['SDA', '2025-08-25', '25', '8', '2025', 'KRIAN', 'KRIAN', 'PMT', 'WONOAYU', '', 'PMT', '25-Aug-25', '08:00', '25-Aug-25', '08:02', '08:02', '0:02:00', '202', '198.262', 'Temporer', 'DGR', 'R-N', 'CERAH', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Aug 25 dot', 'Act', 'ROW', '1', 'ROW'],
            ['SDA', '2026-01-10', '10', '1', '2026', 'BUDURAN', 'SIDOARJO KOTA', 'RECL-PMCB', 'HEAVEN LAND', '', 'PMCB BUDURAN', '10-Jan-26', '07:00', '10-Jan-26', '07:05', '07:05', '0:05:00', '120', '304,264', 'Temporer', 'DGR', 'T-N', 'HUJAN', 'SEBAGIAN', 'EKSTERNAL', 'GANGGUAN', 'Jan 26 comma', 'Act', 'BINATANG', '1', 'BINATANG'],
            ['SDA', '2026-01-12', '12', '1', '2026', 'KRIAN', 'KRIAN', 'PMT', 'WONOAYU', '', 'PMT', '12-Jan-26', '09:00', '12-Jan-26', '09:15', '09:15', '0:15:00', '180', '1200,50', 'Permanen', 'OCR', 'R-S', 'CERAH', 'TOTAL', 'INTERNAL', 'GANGGUAN', 'Jan 26 two', 'Act', 'MATERIAL', '1', 'MATERIAL'],
        ];

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $headers);
        foreach ($rows as $r) {
            fputcsv($fp, $r);
        }
        rewind($fp);
        $csv = (string)stream_get_contents($fp);
        fclose($fp);

        $sync = new GoogleSheetsGangguanSyncService(
            $this->conn,
            new GangguanSync(),
            static fn(): string => $csv,
            $this->statusFile
        );
        $st = $sync->ensureSynced(true);
        $this->assertSame('SYNCED', $st['status'], (string)($st['last_error'] ?? ''));

        $analytics = new GangguanAnalyticsService($this->conn);
        $ctx       = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'scope' => 'year_a'], 'administrator', null);
        $g12       = $analytics->getGangguanAnalytics12Items($ctx);
        $dash      = $analytics->getDashboardSyncPayload($ctx, [2026, 2025]);
        $ens       = $g12['ens_analysis'];
        $dashImp   = $dash['interruptionMetricsPayload'];

        // Month 9 2025 must parse as ~7914.78 kWh (NOT 7,914,779.0!)
        $this->assertEqualsWithDelta(7914.78, (float)$ens['monthly_b'][8], 0.02);
        $this->assertEqualsWithDelta(198.26, (float)$ens['monthly_b'][7], 0.02);
        $this->assertEqualsWithDelta(1504.76, (float)$ens['monthly_a'][0], 0.02);

        // Monthly sums reconcile strictly to annual totals
        $sumMoA = array_sum(array_map(static fn($v): float => (float)($v ?? 0.0), $ens['monthly_a']));
        $sumMoB = array_sum(array_map(static fn($v): float => (float)($v ?? 0.0), $ens['monthly_b']));
        $this->assertEqualsWithDelta($ens['total_a'], $sumMoA, 0.01);
        $this->assertEqualsWithDelta($ens['total_b'], $sumMoB, 0.01);

        // Dashboard payload strictly matches /gangguan analytics (including monthly counts)
        $this->assertSame($ens['monthly_a'], $dashImp['ens_monthly'][2026]);
        $this->assertSame($ens['monthly_b'], $dashImp['ens_monthly'][2025]);
        $this->assertSame($ens['monthly_cnt_a'], $dashImp['ens_monthly_cnt'][2026]);
        $this->assertSame($ens['monthly_cnt_b'], $dashImp['ens_monthly_cnt'][2025]);
        $this->assertSame(2, (int)$ens['monthly_cnt_a'][0]);
        $this->assertEqualsWithDelta($ens['total_a'], $dashImp['ens_annual'][2026]['total_kwh'], 0.01);
        $this->assertEqualsWithDelta($ens['total_b'], $dashImp['ens_annual'][2025]['total_kwh'], 0.01);

        // Penyulang filter parity between /gangguan and getDashboardSyncPayload
        $ctxFeeder = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'penyulang_id' => 12, 'scope' => 'combined'], 'administrator', null);
        $g12Feeder = $analytics->getGangguanAnalytics12Items($ctxFeeder);
        $dashFeeder = $analytics->getDashboardSyncPayload($ctxFeeder, [2026, 2025]);
        $this->assertSame('combined', $dashFeeder['interruptionMetricsPayload']['ens_scope']);
        $this->assertSame(12, $dashFeeder['interruptionMetricsPayload']['ens_penyulang_id']);
        $this->assertSame($g12Feeder['ens_analysis']['monthly_a'], $dashFeeder['interruptionMetricsPayload']['ens_monthly'][2026]);
        $this->assertEqualsWithDelta(304.26, $dashFeeder['interruptionMetricsPayload']['ens_annual'][2026]['total_kwh'], 0.02);

        // ENS drilldowns return sorted top-kWh events first & respect ULP boundary
        $dEnsMo = $analytics->getDrilldownDetails($ctx, 'ens_month', '1', ['target_year' => 2026]);
        $this->assertSame(2, $dEnsMo['total_matching']);
        $this->assertEqualsWithDelta(1504.76, (float)$dEnsMo['breakdowns']['energy_kwh_total'], 0.02);
        $this->assertSame('WONOAYU', $dEnsMo['events'][0]['feeder_name']); // 1200.50 > 304.264

        $dEnsYr25 = $analytics->getDrilldownDetails($ctx, 'ens_year', '2025', ['target_year' => 2025]);
        $this->assertSame(2, $dEnsYr25['total_matching']);
        $this->assertEqualsWithDelta(8113.04, (float)$dEnsYr25['breakdowns']['energy_kwh_total'], 0.02);
        $this->assertEqualsWithDelta(7914.779, (float)$dEnsYr25['events'][0]['energy_not_supplied_kwh'], 0.01);

        $ctxUlp2 = $analytics->resolveContext(['year_a' => 2026, 'year_b' => 2025, 'ulp_id' => 1], 'admin_ulp', 2);
        $dUlp2Ens = $analytics->getDrilldownDetails($ctxUlp2, 'ens_year', '2026', ['target_year' => 2026]);
        $this->assertSame(1, $dUlp2Ens['total_matching']);
        $this->assertEqualsWithDelta(304.26, (float)$dUlp2Ens['breakdowns']['energy_kwh_total'], 0.02);
    }
}
