<?php

namespace App\Services;

use App\Models\DocumentModel;
use App\Repositories\DocumentRepository;

class DocumentIntelligenceService
{
    private DocumentModel $model;
    private DocumentRepository $repository;

    public function __construct()
    {
        $this->model      = new DocumentModel();
        $this->repository = new DocumentRepository();
    }

    /**
     * Generate automatic unique document number (BA-YYYY-XXXXXX, WO-YYYY-XXXXXX, LP-YYYY-XXXXXX)
     */
    public function generateNomorDokumen(string $jenisDokumen): string
    {
        $prefix = match(strtoupper($jenisDokumen)) {
            'BERITA ACARA'      => 'BA',
            'WORK ORDER'        => 'WO',
            'SURAT TUGAS'       => 'ST',
            'LAPORAN BULANAN'   => 'LPB',
            'LAPORAN MINGGUAN'  => 'LPM',
            default             => 'LP'
        };

        $year = date('Y');
        $db = \Config\Database::connect();
        $count = $db->table('documents')->where('YEAR(created_at)', $year)->countAllResults() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $count);
    }

    /**
     * Create new document with auto SHA256 checksum & QR Code verification URL
     */
    public function createDocument(array $data): int
    {
        $nomor = $this->generateNomorDokumen($data['jenis_dokumen']);
        $contentHtml = $data['content_html'] ?? '<p>Isi Dokumen Official SIDAK TEJO.</p>';

        // Calculate SHA256 Checksum
        $checksum = hash('sha256', $nomor . $contentHtml . microtime());

        $docData = [
            'nomor_dokumen' => $nomor,
            'jenis_dokumen' => $data['jenis_dokumen'],
            'judul_dokumen' => $data['judul_dokumen'],
            'content_html'  => $contentHtml,
            'checksum'      => $checksum,
            'status'        => strtoupper($data['status'] ?? 'DRAFT'),
            'created_by'    => $data['created_by'] ?? 'System',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        return $this->model->insert($docData);
    }

    /**
     * Approve Document and Add Digital Signature Canvas
     */
    public function approveDocument(int $docId, array $signerData): bool
    {
        $this->repository->addSignature(array_merge($signerData, [
            'document_id' => $docId,
        ]));

        // Check if status should update to APPROVED
        $signatures = $this->repository->findDocumentDetail($docId)['signatures'] ?? [];
        if (count($signatures) >= 1) {
            $this->model->update($docId, [
                'status'     => 'APPROVED',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    /**
     * Auto-generate operational report content from live database findings
     */
    public function generateOperationalReportContent(array $params): array
    {
        $periode   = strtoupper(trim($params['periode'] ?? 'BULANAN'));
        $pekerjaan = trim($params['pekerjaan'] ?? 'SEMUA');
        $tahun     = (int)($params['tahun'] ?? date('Y'));
        $bulan     = (int)($params['bulan'] ?? date('n'));
        $semester  = (int)($params['semester'] ?? (date('n') <= 6 ? 1 : 2));
        $tanggal   = trim($params['tanggal'] ?? date('Y-m-d'));
        $ulpId     = !empty($params['ulp_id']) ? (int)$params['ulp_id'] : null;
        $penyulangId = !empty($params['penyulang_id']) ? (int)$params['penyulang_id'] : null;

        // Calculate Date Range
        if ($periode === 'HARIAN') {
            $startDate = $tanggal;
            $endDate   = $tanggal;
            $periodeLabel = 'Harian (' . date('d F Y', strtotime($tanggal)) . ')';
        } elseif ($periode === 'SEMESTER') {
            if ($semester === 1) {
                $startDate = "{$tahun}-01-01";
                $endDate   = "{$tahun}-06-30";
                $periodeLabel = "Semester 1 (Januari - Juni {$tahun})";
            } else {
                $startDate = "{$tahun}-07-01";
                $endDate   = "{$tahun}-12-31";
                $periodeLabel = "Semester 2 (Juli - Desember {$tahun})";
            }
        } else {
            // Default BULANAN
            $startDate = sprintf('%04d-%02d-01', $tahun, $bulan);
            $endDate   = date('Y-m-t', strtotime($startDate));
            $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            $periodeLabel = 'Bulan ' . ($monthNames[$bulan] ?? $bulan) . ' ' . $tahun;
        }

        $db = \Config\Database::connect();
        $builder = $db->table('temuan t');
        $builder->select('t.id, t.nomor_temuan, t.tanggal_temuan, t.jenis_temuan, t.detail_temuan, t.pelaksana, t.status, t.prioritas, t.noga, u.nama_ulp, p.nama_penyulang, s.nama_section');
        $builder->join('ulps u', 't.ulp_id = u.id', 'left');
        $builder->join('penyulang p', 't.penyulang_id = p.id', 'left');
        $builder->join('sections s', 't.section_id = s.id', 'left');
        $builder->where('t.deleted_at IS NULL');
        $builder->where('t.tanggal_temuan >=', $startDate);
        $builder->where('t.tanggal_temuan <=', $endDate);

        if ($pekerjaan !== '' && $pekerjaan !== 'SEMUA') {
            $builder->where('t.pelaksana', $pekerjaan);
        }
        if ($ulpId) {
            $builder->where('t.ulp_id', $ulpId);
        }
        if ($penyulangId) {
            $builder->where('t.penyulang_id', $penyulangId);
        }

        $builder->orderBy('t.tanggal_temuan', 'ASC');
        $items = $builder->get()->getResultArray();

        // Statistics
        $totalTemuan = count($items);
        $statusCounts = ['SELESAI' => 0, 'PROSES' => 0, 'OPEN' => 0];
        $priorityCounts = ['EMERGENCY' => 0, 'HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        $pelaksanaBreakdown = [];

        foreach ($items as $it) {
            $st = strtoupper($it['status'] ?? 'OPEN');
            if (str_contains($st, 'SELESAI')) {
                $statusCounts['SELESAI']++;
            } elseif (str_contains($st, 'PROSES')) {
                $statusCounts['PROSES']++;
            } else {
                $statusCounts['OPEN']++;
            }

            $pr = strtoupper($it['prioritas'] ?? 'LOW');
            if (isset($priorityCounts[$pr])) {
                $priorityCounts[$pr]++;
            }

            $pel = $it['pelaksana'] ?: 'Lainnya';
            $pelaksanaBreakdown[$pel] = ($pelaksanaBreakdown[$pel] ?? 0) + 1;
        }

        $workLabel = ($pekerjaan === 'SEMUA' || $pekerjaan === '') ? 'Seluruh Tim Pelaksana' : $pekerjaan;
        $suggestedTitle = "Laporan Pekerjaan {$workLabel} - {$periodeLabel}";

        // Build HTML Report Content
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
            <div style="border-bottom: 2px solid #0056b3; padding-bottom: 12px; margin-bottom: 20px;">
                <h2 style="color: #0056b3; margin: 0 0 6px 0; font-size: 20px;">PT PLN (PERSERO) UP3 SIDOARJO</h2>
                <h3 style="margin: 0 0 6px 0; font-size: 16px;"><?= htmlspecialchars($suggestedTitle) ?></h3>
                <table style="font-size: 12px; color: #555;">
                    <tr><td style="width: 120px;"><strong>Periode</strong></td><td>: <?= htmlspecialchars($periodeLabel) ?> (<?= htmlspecialchars($startDate) ?> s/d <?= htmlspecialchars($endDate) ?>)</td></tr>
                    <tr><td><strong>Bidang Pekerjaan</strong></td><td>: <?= htmlspecialchars($workLabel) ?></td></tr>
                    <tr><td><strong>Waktu Generate</strong></td><td>: <?= date('d F Y H:i:s') ?> WIB (Live Operational Database)</td></tr>
                </table>
            </div>

            <h4 style="font-size: 14px; margin-bottom: 8px; color: #111;">I. RINGKASAN EKSEKUTIF</h4>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px;">
                <thead>
                    <tr style="background-color: #f1f5f9; border: 1px solid #cbd5e1;">
                        <th style="padding: 8px; border: 1px solid #cbd5e1; text-align: left;">Indikator</th>
                        <th style="padding: 8px; border: 1px solid #cbd5e1; text-align: center; width: 120px;">Jumlah</th>
                        <th style="padding: 8px; border: 1px solid #cbd5e1; text-align: left;">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Total Temuan Terdeteksi</td><td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center; font-weight: bold;"><?= $totalTemuan ?></td><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Seluruh item pekerjaan jaringan</td></tr>
                    <tr><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Pekerjaan Selesai (Completed)</td><td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center; color: #16a34a; font-weight: bold;"><?= $statusCounts['SELESAI'] ?></td><td style="padding: 6px 8px; border: 1px solid #cbd5e1;"><?= $totalTemuan > 0 ? round(($statusCounts['SELESAI']/$totalTemuan)*100, 1) : 0 ?>% dari total volume</td></tr>
                    <tr><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Pekerjaan Dalam Proses (Progress)</td><td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center; color: #2563eb; font-weight: bold;"><?= $statusCounts['PROSES'] ?></td><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Sedang dieksekusi di lapangan</td></tr>
                    <tr><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Belum Tertangani (Open)</td><td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center; color: #d97706; font-weight: bold;"><?= $statusCounts['OPEN'] ?></td><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Menunggu alokasi material / regu</td></tr>
                    <tr><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Prioritas Emergency</td><td style="padding: 6px 8px; border: 1px solid #cbd5e1; text-align: center; color: #dc2626; font-weight: bold;"><?= $priorityCounts['EMERGENCY'] ?></td><td style="padding: 6px 8px; border: 1px solid #cbd5e1;">Temuan kritis berpotensi padam</td></tr>
                </tbody>
            </table>

            <h4 style="font-size: 14px; margin-bottom: 8px; color: #111;">II. DETAIL DAFTAR PEKERJAAN & TEMUAN LAPANGAN</h4>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 25px;">
                <thead>
                    <tr style="background-color: #0056b3; color: #ffffff;">
                        <th style="padding: 6px; border: 1px solid #004085; text-align: center; width: 35px;">No</th>
                        <th style="padding: 6px; border: 1px solid #004085; text-align: left; width: 85px;">Tanggal</th>
                        <th style="padding: 6px; border: 1px solid #004085; text-align: left; width: 100px;">Penyulang / Gardu</th>
                        <th style="padding: 6px; border: 1px solid #004085; text-align: left;">Detail Temuan & Pekerjaan</th>
                        <th style="padding: 6px; border: 1px solid #004085; text-align: left; width: 110px;">Pelaksana</th>
                        <th style="padding: 6px; border: 1px solid #004085; text-align: center; width: 75px;">Prioritas</th>
                        <th style="padding: 6px; border: 1px solid #004085; text-align: center; width: 80px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="7" style="padding: 12px; text-align: center; border: 1px solid #cbd5e1; color: #64748b;">Tidak ada temuan pekerjaan pada rentang periode dan filter yang dipilih.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($items, 0, 150) as $idx => $it): ?>
                            <tr style="background-color: <?= $idx % 2 === 0 ? '#ffffff' : '#f8fafc' ?>;">
                                <td style="padding: 5px; border: 1px solid #cbd5e1; text-align: center;"><?= $idx + 1 ?></td>
                                <td style="padding: 5px; border: 1px solid #cbd5e1;"><?= date('d/m/Y', strtotime($it['tanggal_temuan'])) ?></td>
                                <td style="padding: 5px; border: 1px solid #cbd5e1;">
                                    <strong><?= htmlspecialchars($it['nama_penyulang'] ?: '-') ?></strong>
                                    <?php if (!empty($it['noga'])): ?>
                                        <br><small style="color: #64748b;"><?= htmlspecialchars($it['noga']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 5px; border: 1px solid #cbd5e1;"><?= htmlspecialchars($it['detail_temuan'] ?: ($it['jenis_temuan'] ?: '-')) ?></td>
                                <td style="padding: 5px; border: 1px solid #cbd5e1;"><?= htmlspecialchars($it['pelaksana'] ?: '-') ?></td>
                                <td style="padding: 5px; border: 1px solid #cbd5e1; text-align: center;">
                                    <span style="font-weight: bold; color: <?= $it['prioritas'] === 'EMERGENCY' ? '#dc2626' : ($it['prioritas'] === 'HIGH' ? '#d97706' : '#2563eb') ?>;">
                                        <?= htmlspecialchars($it['prioritas'] ?: 'LOW') ?>
                                    </span>
                                </td>
                                <td style="padding: 5px; border: 1px solid #cbd5e1; text-align: center;">
                                    <span style="font-weight: bold; color: <?= str_contains(strtoupper($it['status'] ?? ''), 'SELESAI') ? '#16a34a' : '#ea580c' ?>;">
                                        <?= htmlspecialchars($it['status'] ?: 'OPEN') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($items) > 150): ?>
                            <tr>
                                <td colspan="7" style="padding: 8px; text-align: center; border: 1px solid #cbd5e1; color: #475569; font-style: italic;">
                                    Menampilkan 150 dari total <?= count($items) ?> temuan. Unduh laporan lengkap untuk melihat seluruh data.
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top: 30px; display: flex; justify-content: space-between; font-size: 12px;">
                <div style="text-align: center; width: 200px;">
                    Mengetahui,<br><strong>Manager Bagian Jaringan</strong><br><br><br><br>
                    (.......................................................)
                </div>
                <div style="text-align: center; width: 200px;">
                    Sidoarjo, <?= date('d F Y') ?><br><strong>Supervisor Pemeliharaan</strong><br><br><br><br>
                    (.......................................................)
                </div>
            </div>
        </div>
        <?php
        $htmlContent = ob_get_clean();

        return [
            'success'       => true,
            'judul_dokumen' => $suggestedTitle,
            'content_html'  => $htmlContent,
            'total_items'   => $totalTemuan,
            'periode_label' => $periodeLabel,
        ];
    }
}
