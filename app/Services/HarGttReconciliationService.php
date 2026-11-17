<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Safely inspects and reconciles legacy 'HAR GARDU' / 'HAR_GARDU' pelaksana
 * labels to canonical 'HAR GTT' with full pre-change record snapshots and audit logging.
 */
class HarGttReconciliationService
{
    protected BaseConnection $db;
    protected string $auditDir;

    public function __construct(?BaseConnection $db = null, ?string $auditDir = null)
    {
        $this->db       = $db ?? \Config\Database::connect();
        $this->auditDir = $auditDir
            ?? (defined('WRITEPATH') ? rtrim(WRITEPATH, "\\/") . DIRECTORY_SEPARATOR . 'audits' : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sidak_audits');
    }

    /**
     * Inspect legacy 'HAR GARDU' / 'HAR_GARDU' records without mutating any data.
     */
    public function inspect(): array
    {
        $temuanRows = [];
        $workOrderRows = [];

        if ($this->db->tableExists('temuan')) {
            $cols = array_flip($this->db->getFieldNames('temuan'));
            $select = ['id', 'nomor_temuan', 'pelaksana', 'status'];
            foreach (['ulp_id', 'penyulang_id', 'jenis_temuan', 'prioritas', 'updated_at', 'deleted_at'] as $optCol) {
                if (isset($cols[$optCol])) {
                    $select[] = $optCol;
                }
            }
            $temuanRows = $this->db->table('temuan')
                ->select(implode(', ', $select))
                ->where("REPLACE(REPLACE(UPPER(TRIM(COALESCE(pelaksana, ''))), '_', ' '), '-', ' ') =", 'HAR GARDU')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray() ?: [];
        }

        if ($this->db->tableExists('work_orders')) {
            $woCols = array_flip($this->db->getFieldNames('work_orders'));
            if (isset($woCols['pelaksana'])) {
                $selectWo = ['id', 'pelaksana'];
                foreach (['wo_number', 'temuan_id', 'status', 'updated_at'] as $optCol) {
                    if (isset($woCols[$optCol])) {
                        $selectWo[] = $optCol;
                    }
                }
                $workOrderRows = $this->db->table('work_orders')
                    ->select(implode(', ', $selectWo))
                    ->where("REPLACE(REPLACE(UPPER(TRIM(COALESCE(pelaksana, ''))), '_', ' '), '-', ' ') =", 'HAR GARDU')
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getResultArray() ?: [];
            }
        }

        return [
            'temuan_rows'        => $temuanRows,
            'work_order_rows'    => $workOrderRows,
            'temuan_count'       => count($temuanRows),
            'work_orders_count'  => count($workOrderRows),
            'total_target_count' => count($temuanRows) + count($workOrderRows),
        ];
    }

    /**
     * Reconcile only exact legacy 'HAR GARDU' records to 'HAR GTT' inside a DB transaction
     * and persist an immutable JSON evidence/rollback manifest before returning.
     */
    public function reconcile(bool $execute = false, string $actor = 'SYSTEM'): array
    {
        $snapshot = $this->inspect();
        if (!$execute) {
            return [
                'executed'            => false,
                'changed'             => false,
                'temuan_updated'      => 0,
                'work_orders_updated' => 0,
                'temuan_target'       => $snapshot['temuan_count'],
                'work_orders_target'  => $snapshot['work_orders_count'],
                'affected_ids'        => array_map(static fn(array $r): int => (int)$r['id'], $snapshot['temuan_rows']),
                'audit_file'          => null,
            ];
        }

        if ($snapshot['total_target_count'] === 0) {
            return [
                'executed'            => true,
                'changed'             => false,
                'temuan_updated'      => 0,
                'work_orders_updated' => 0,
                'temuan_target'       => 0,
                'work_orders_target'  => 0,
                'affected_ids'        => [],
                'audit_file'          => null,
            ];
        }

        $now        = date('Y-m-d H:i:s');
        $temuanIds  = array_values(array_filter(array_map(static fn(array $r): int => (int)$r['id'], $snapshot['temuan_rows'])));
        $woIds      = array_values(array_filter(array_map(static fn(array $r): int => (int)$r['id'], $snapshot['work_order_rows'])));
        $temuanUpd  = 0;
        $woUpd      = 0;

        $this->db->transBegin();
        try {
            if (!empty($temuanIds)) {
                $temuanCols = array_flip($this->db->getFieldNames('temuan'));
                $payload    = ['pelaksana' => 'HAR GTT'];
                if (isset($temuanCols['updated_at'])) {
                    $payload['updated_at'] = $now;
                }
                foreach (array_chunk($temuanIds, 200) as $idChunk) {
                    $this->db->table('temuan')->whereIn('id', $idChunk)->update($payload);
                    $temuanUpd += max(0, (int)$this->db->affectedRows());
                }
            }

            if (!empty($woIds)) {
                $woCols  = array_flip($this->db->getFieldNames('work_orders'));
                $woPay   = ['pelaksana' => 'HAR GTT'];
                if (isset($woCols['updated_at'])) {
                    $woPay['updated_at'] = $now;
                }
                foreach (array_chunk($woIds, 200) as $idChunk) {
                    $this->db->table('work_orders')->whereIn('id', $idChunk)->update($woPay);
                    $woUpd += max(0, (int)$this->db->affectedRows());
                }
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Transaksi rekonsiliasi HAR GTT gagal.');
            }

            $auditFile = $this->writeAuditManifest($snapshot, $temuanUpd, $woUpd, $actor, $now);
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $sampleNomor = array_slice(array_map(static fn(array $r): string => (string)($r['nomor_temuan'] ?? ('#' . $r['id'])), $snapshot['temuan_rows']), 0, 10);
        $summaryMsg  = sprintf(
            'Rekonsiliasi pelaksana HAR GARDU -> HAR GTT oleh %s: %d temuan (%s%s), %d work_orders. Bukti: %s',
            $actor,
            $temuanUpd,
            implode(', ', $sampleNomor),
            count($snapshot['temuan_rows']) > 10 ? ', ...' : '',
            $woUpd,
            $auditFile ? basename($auditFile) : '-'
        );

        log_message('info', '[HarGttReconciliationService] ' . $summaryMsg);
        if (function_exists('log_activity') && $this->db->tableExists('activity_logs')) {
            try {
                log_activity('RECONCILE_HAR_GTT', $summaryMsg);
            } catch (\Throwable $eLog) {
                // Keep reconciliation atomic and resilient in CLI/migration contexts without session.
            }
        }

        return [
            'executed'            => true,
            'changed'             => ($temuanUpd + $woUpd) > 0,
            'temuan_updated'      => $temuanUpd,
            'work_orders_updated' => $woUpd,
            'temuan_target'       => $snapshot['temuan_count'],
            'work_orders_target'  => $snapshot['work_orders_count'],
            'affected_ids'        => $temuanIds,
            'sample_nomor'        => $sampleNomor,
            'audit_file'          => $auditFile,
        ];
    }

    protected function writeAuditManifest(array $snapshot, int $temuanUpd, int $woUpd, string $actor, string $timestamp): ?string
    {
        if (!is_dir($this->auditDir)) {
            @mkdir($this->auditDir, 0755, true);
        }
        if (!is_dir($this->auditDir)) {
            return null;
        }

        $filename = 'har_gtt_reconciliation_' . date('Ymd_His') . '_' . substr(md5($timestamp . $actor . $temuanUpd), 0, 6) . '.json';
        $path     = rtrim($this->auditDir, "\\/") . DIRECTORY_SEPARATOR . $filename;
        $manifest = [
            'action'              => 'RECONCILE_HAR_GARDU_TO_HAR_GTT',
            'actor'               => $actor,
            'executed_at'         => $timestamp,
            'temuan_updated'      => $temuanUpd,
            'work_orders_updated' => $woUpd,
            'before_temuan_rows'  => $snapshot['temuan_rows'],
            'before_work_orders'  => $snapshot['work_order_rows'],
        ];

        $written = @file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $written !== false ? $path : null;
    }
}
