<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Database\ConnectionInterface;

/**
 * Smart Search — Multi-table LIKE-based full text search for SIDAK TEJO
 * Supports: Temuan, Work Order, Penyulang, User
 */
class SmartSearch extends BaseController
{
    protected ConnectionInterface $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    // -------------------------------------------------------------------------
    // GET /smart-search  — full search results page
    // -------------------------------------------------------------------------
    public function index(): string
    {
        $q = trim($this->request->getGet('q') ?? '');

        $data = [
            'q'        => $q,
            'results'  => [],
            'total'    => 0,
            'searched' => false,
        ];

        if (strlen($q) >= 2) {
            $data['results']  = $this->runSearch($q);
            $data['total']    = array_sum(array_map('count', $data['results']));
            $data['searched'] = true;
        }

        return view('smart_search/index', $data);
    }

    // -------------------------------------------------------------------------
    // GET /smart-search/api?q=keyword  — AJAX JSON autocomplete / quick-search
    // -------------------------------------------------------------------------
    public function api(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->response->setHeader('Content-Type', 'application/json');

        $q = trim($this->request->getGet('q') ?? '');
        if (strlen($q) < 2) {
            return $this->response->setJSON(['results' => [], 'total' => 0]);
        }

        $results = $this->runSearch($q, 5);
        $flat = [];
        foreach ($results as $type => $items) {
            foreach ($items as $item) {
                $item['_type'] = $type;
                $flat[] = $item;
            }
        }

        return $this->response->setJSON([
            'results' => $flat,
            'total'   => count($flat),
            'q'       => $q,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal: run multi-table search
    // -------------------------------------------------------------------------
    private function runSearch(string $q, int $limit = 10): array
    {
        $like = '%' . $q . '%';
        $results = [];

        // --- Temuan ---------------------------------------------------------
        $temuan = $this->db->query(
            "SELECT id, kode_temuan AS kode, detail_temuan AS detail,
                    jenis_temuan, prioritas, status,
                    penyulang, section, tanggal_temuan AS tgl
             FROM temuan
             WHERE (detail_temuan LIKE ? OR kode_temuan LIKE ? OR jenis_temuan LIKE ? OR penyulang LIKE ?)
               AND deleted_at IS NULL
             ORDER BY created_at DESC
             LIMIT ?",
            [$like, $like, $like, $like, $limit]
        )->getResultArray();

        foreach ($temuan as &$t) {
            $t['_label'] = $t['kode'] . ' — ' . mb_substr($t['detail'], 0, 60);
            $t['_url']   = site_url('temuan/detail/' . $t['id']);
            $t['_icon']  = 'fa-triangle-exclamation';
            $t['_color'] = $this->priorityColor($t['prioritas'] ?? '');
        }
        $results['Temuan'] = $temuan;

        // --- Work Order -----------------------------------------------------
        $wo = [];
        if ($this->db->tableExists('work_orders')) {
            $woQuery = "SELECT wo.id, wo.nomor_wo, wo.judul_wo AS judul,
                               wo.status, wo.prioritas, wo.created_at
                        FROM work_orders wo
                        WHERE (wo.nomor_wo LIKE ? OR wo.judul_wo LIKE ?)
                          AND wo.deleted_at IS NULL
                        ORDER BY wo.created_at DESC
                        LIMIT ?";
            $wo = $this->db->query($woQuery, [$like, $like, $limit])->getResultArray();

            foreach ($wo as &$w) {
                $w['_label'] = $w['nomor_wo'] . ' — ' . mb_substr($w['judul'] ?? '', 0, 60);
                $w['_url']   = site_url('work-orders/detail/' . $w['id']);
                $w['_icon']  = 'fa-clipboard-list';
                $w['_color'] = 'text-primary';
            }
        }
        $results['Work Order'] = $wo;

        // --- Penyulang / Section -------------------------------------------
        if (strlen($q) >= 3) {
            $penyulang = $this->db->query(
                "SELECT DISTINCT p.nama_penyulang AS penyulang, s.nama_section AS section,
                       COUNT(t.id) AS jumlah_temuan
                 FROM temuan t
                 JOIN penyulang p ON p.id = t.penyulang_id
                 JOIN sections s ON s.id = t.section_id
                 WHERE (p.nama_penyulang LIKE ? OR s.nama_section LIKE ?)
                   AND t.deleted_at IS NULL
                 GROUP BY p.nama_penyulang, s.nama_section
                 LIMIT ?",
                [$like, $like, $limit]
            )->getResultArray();

            foreach ($penyulang as &$p) {
                $p['_label'] = 'Penyulang ' . $p['penyulang'] . ($p['section'] ? ' / ' . $p['section'] : '');
                $p['_url']   = site_url('temuan?penyulang=' . urlencode($p['penyulang']));
                $p['_icon']  = 'fa-bolt';
                $p['_color'] = 'text-warning';
            }
            $results['Penyulang'] = $penyulang;
        }

        return $results;
    }

    private function priorityColor(string $priority): string
    {
        return match (strtoupper($priority)) {
            'EMERGENCY' => 'text-danger',
            'HIGH'      => 'text-warning',
            'MEDIUM'    => 'text-primary',
            default     => 'text-success',
        };
    }
}
