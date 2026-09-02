<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Forensic Audit Tool for Temuan Record & Date Provenance
 *
 * Command: php spark audit:temuan [nomor_temuan]
 * Example: php spark audit:temuan STJ-2026-000175
 */
class AuditTemuanCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:temuan';
    protected $description = 'Forensic audit of temuan record date fields, creator, SLA, and audit logs.';
    protected $usage       = 'audit:temuan [nomor_temuan]';
    protected $arguments   = [
        'nomor_temuan' => 'The finding code (e.g. STJ-2026-000175) or ID',
    ];

    public function run(array $params)
    {
        $target = $params[0] ?? CLI::prompt('Masukkan Nomor Temuan atau ID', 'STJ-2026-000175');

        CLI::newLine();
        CLI::write("==================================================================", "yellow");
        CLI::write("   SIDAK TEJO FORENSIC AUDIT — TEMUAN DATE & RECORD PROVENANCE   ", "yellow");
        CLI::write("==================================================================", "yellow");
        CLI::write("Target: " . $target, "white");
        CLI::newLine();

        $db = Database::connect();

        $builder = $db->table('temuan t')
            ->select('t.*, ulps.nama_ulp, penyulang.nama_penyulang, sections.nama_section')
            ->join('ulps', 'ulps.id = t.ulp_id', 'left')
            ->join('penyulang', 'penyulang.id = t.penyulang_id', 'left')
            ->join('sections', 'sections.id = t.section_id', 'left');

        if (is_numeric($target)) {
            $builder->where('t.id', (int)$target);
        } else {
            $builder->where('t.nomor_temuan', trim($target));
        }

        $row = $builder->get()->getRowArray();

        if (!$row) {
            CLI::error("❌ Record '$target' TIDAK DITEMUKAN di database!");
            CLI::newLine();
            return;
        }

        CLI::write("1. FIELD INSPECTION (EXACT DATABASE VALUES):", "cyan");
        CLI::write(str_repeat("-", 66));

        $fieldsToInspect = [
            'id'               => 'ID Record',
            'nomor_temuan'     => 'Nomor Temuan',
            'tanggal_temuan'   => 'Tanggal Temuan (DATE)',
            'created_at'       => 'Created At (DATETIME)',
            'updated_at'       => 'Updated At (DATETIME)',
            'tanggal_selesai'  => 'Tanggal Selesai',
            'status'           => 'Status Pekerjaan',
            'prioritas'        => 'Prioritas SLA',
            'pelaksana'        => 'Pelaksana',
            'nama_ulp'         => 'ULP',
            'nama_penyulang'   => 'Penyulang',
            'nama_section'     => 'Section / Ruas',
            'created_by'       => 'Created By (User ID)',
            'created_by_name'  => 'Created By (Name)',
            'created_by_nip'   => 'Created By (NIP)',
        ];

        foreach ($fieldsToInspect as $k => $label) {
            $val = $row[$k] ?? null;
            $valStr = is_null($val) ? 'NULL' : (string)$val;
            
            // Highlight date differences
            $color = 'white';
            if ($k === 'tanggal_temuan') $color = 'light_red';
            if ($k === 'created_at')     $color = 'light_green';
            if ($k === 'status')         $color = 'light_yellow';

            CLI::write(sprintf("  %-26s : ", $label . " [$k]"), "white", null);
            CLI::write($valStr, $color);
        }

        CLI::newLine();
        CLI::write("2. SLA & DATE ANATOMY FORENSIC:", "cyan");
        CLI::write(str_repeat("-", 66));

        $tglTemuan = $row['tanggal_temuan'] ?? null;
        $createdAt  = $row['created_at'] ?? null;

        if ($tglTemuan && $createdAt) {
            $tsTemuan  = strtotime($tglTemuan);
            $tsCreated = strtotime($createdAt);
            $dayDiff   = round(($tsCreated - $tsTemuan) / 86400);

            CLI::write("  Tanggal Kejadian Fisik   : " . date('d F Y', $tsTemuan), "white");
            CLI::write("  Waktu Input ke Sistem    : " . date('d F Y H:i:s', $tsCreated) . " WIB", "white");
            CLI::write("  Selisih Waktu Input      : " . $dayDiff . " hari setelah tanggal temuan", ($dayDiff > 0 ? "light_red" : "light_green"));

            if (function_exists('get_sla_status')) {
                $sla = get_sla_status($row['prioritas'], $row['tanggal_temuan'], $row['status'], $row['tanggal_selesai']);
                CLI::write("  Deadline SLA             : " . ($sla['deadline'] ?? '-'), "yellow");
                CLI::write("  Status SLA Dihitung      : " . ($sla['is_overdue'] ? 'OVERDUE (TERLAMBAT)' : 'ON SCHEDULE'), ($sla['is_overdue'] ? 'light_red' : 'light_green'));
            }
        }

        // 3. Check Audit Logs
        CLI::newLine();
        CLI::write("3. AUDIT LOGS TRAIL FOR THIS RECORD:", "cyan");
        CLI::write(str_repeat("-", 66));

        if ($db->tableExists('audit_logs')) {
            $logs = $db->table('audit_logs')
                ->where('action', 'CREATE_TEMUAN')
                ->like('description', $row['nomor_temuan'])
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray();

            if (!empty($logs)) {
                foreach ($logs as $l) {
                    CLI::write("  [LOG #{$l['id']}] {$l['created_at']} | User: {$l['username']} | IP: {$l['ip_address']}");
                    CLI::write("    Desc: {$l['description']}");
                }
            } else {
                CLI::write("  (Tidak ditemukan catatan CREATE_TEMUAN di audit_logs)");
            }
        }

        // 4. Surrounding Records (Context)
        CLI::newLine();
        CLI::write("4. NEIGHBORING RECORDS (SURROUNDING TEMUAN):", "cyan");
        CLI::write(str_repeat("-", 66));

        $id = (int)$row['id'];
        $neighbors = $db->table('temuan')
            ->select('id, nomor_temuan, tanggal_temuan, created_at, status, prioritas')
            ->where('id >=', max(1, $id - 2))
            ->where('id <=', $id + 2)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($neighbors as $n) {
            $isTarget = ($n['id'] == $id);
            $prefix = $isTarget ? "👉 [TARGET] " : "   ";
            $color  = $isTarget ? "light_cyan" : "white";
            CLI::write(
                sprintf("%sID: #%-4d | %-16s | Tgl Temuan: %-10s | Input: %-19s | %s",
                    $prefix,
                    $n['id'],
                    $n['nomor_temuan'],
                    $n['tanggal_temuan'] ?? '-',
                    $n['created_at'] ?? '-',
                    $n['status']
                ),
                $color
            );
        }

        CLI::write(str_repeat("=", 66), "yellow");
        CLI::newLine();
    }
}
