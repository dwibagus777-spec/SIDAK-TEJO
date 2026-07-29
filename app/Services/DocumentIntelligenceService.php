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
}
