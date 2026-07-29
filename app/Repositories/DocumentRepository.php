<?php

namespace App\Repositories;

use App\Models\DocumentModel;

class DocumentRepository
{
    private DocumentModel $model;

    public function __construct()
    {
        $this->model = new DocumentModel();
    }

    public function getFilteredDocuments(array $filters = [], int $limit = 50): array
    {
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
        return $builder->get($limit)->getResultArray();
    }

    public function findDocumentDetail(int $id): ?array
    {
        $doc = $this->model->find($id);
        if (!$doc) return null;

        $db = \Config\Database::connect();
        $doc['signatures'] = $db->table('document_signatures')->where('document_id', $id)->orderBy('id', 'ASC')->get()->getResultArray();
        $doc['revisions']  = $db->table('document_revisions')->where('document_id', $id)->orderBy('id', 'DESC')->get()->getResultArray();

        return $doc;
    }

    public function findByChecksum(string $checksum): ?array
    {
        $db = \Config\Database::connect();
        $doc = $db->table('documents')->where('checksum', $checksum)->get()->getRowArray();
        if (!$doc) return null;

        $doc['signatures'] = $db->table('document_signatures')->where('document_id', $doc['id'])->orderBy('id', 'ASC')->get()->getResultArray();
        return $doc;
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
