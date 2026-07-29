<?php

namespace App\Repositories;

use App\Models\DocumentModel;
use CodeIgniter\Database\BaseResult;

class DocumentRepository
{
    private DocumentModel $model;

    public function __construct()
    {
        $this->model = new DocumentModel();
    }

    public function getFilteredDocuments(array $filters = [], int $limit = 50): array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('documents d')->where('d.deleted_at IS NULL');

            if (!empty($filters['jenis_dokumen'])) {
                $builder->where('d.jenis_dokumen', $filters['jenis_dokumen']);
            }
            if (!empty($filters['status'])) {
                $builder->where('d.status', $filters['status']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $builder->groupStart()
                    ->like('d.nomor_dokumen', $s)
                    ->orLike('d.judul_dokumen', $s)
                    ->orLike('d.created_by', $s)
                ->groupEnd();
            }

            $builder->orderBy('d.id', 'DESC');

            $query = $builder->get($limit);
            if ($query === false || !($query instanceof BaseResult)) {
                $error = $db->error();
                log_message('error', '[DocumentRepository::getFilteredDocuments] Query gagal | Code: ' . ($error['code'] ?? 'N/A') . ' | Message: ' . ($error['message'] ?? 'Unknown'));
                return [];
            }

            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[DocumentRepository::getFilteredDocuments] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function findDocumentDetail(int $id): ?array
    {
        try {
            $doc = $this->model->find($id);
            if (!$doc) return null;

            $db = \Config\Database::connect();

            $qSig = $db->table('document_signatures')->where('document_id', $id)->orderBy('id', 'ASC')->get();
            $doc['signatures'] = ($qSig && ($qSig instanceof BaseResult)) ? $qSig->getResultArray() : [];

            $qRev = $db->table('document_revisions')->where('document_id', $id)->orderBy('id', 'DESC')->get();
            $doc['revisions'] = ($qRev && ($qRev instanceof BaseResult)) ? $qRev->getResultArray() : [];

            return $doc;
        } catch (\Throwable $e) {
            log_message('error', '[DocumentRepository::findDocumentDetail] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function findByChecksum(string $checksum): ?array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('documents')->where('checksum', $checksum)->get();
            if ($query === false || !($query instanceof BaseResult)) {
                log_message('error', '[DocumentRepository::findByChecksum] Query gagal | ' . json_encode($db->error()));
                return null;
            }
            $doc = $query->getRowArray();
            if (!$doc) return null;

            $qSig = $db->table('document_signatures')->where('document_id', $doc['id'])->orderBy('id', 'ASC')->get();
            $doc['signatures'] = ($qSig && ($qSig instanceof BaseResult)) ? $qSig->getResultArray() : [];

            return $doc;
        } catch (\Throwable $e) {
            log_message('error', '[DocumentRepository::findByChecksum] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function addSignature(array $data): bool
    {
        $db = \Config\Database::connect();
        return $db->table('document_signatures')->insert(array_merge($data, [
            'signature_date' => date('Y-m-d H:i:s'),
            'created_at'     => date('Y-m-d H:i:s'),
        ]));
    }
}
