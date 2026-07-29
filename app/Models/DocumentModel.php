<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentModel extends Model
{
    protected $table            = 'documents';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $allowedFields    = ['nomor_dokumen', 'jenis_dokumen', 'judul_dokumen', 'content_html', 'pdf_path', 'checksum', 'status', 'created_by', 'created_at', 'updated_at', 'deleted_at'];
    protected $useTimestamps    = true;

    public function __construct()
    {
        parent::__construct();
        $this->ensureTablesExist();
    }

    private function ensureTablesExist(): void
    {
        try {
            $db = \Config\Database::connect();
            $forge = \Config\Database::forge();

            // 1. Table `documents`
            if (!$db->tableExists('documents')) {
                $forge->addField([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'nomor_dokumen' => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
                    'jenis_dokumen' => ['type' => 'VARCHAR', 'constraint' => 100], // Berita Acara, Laporan Inspeksi, Work Order, Surat Tugas, etc.
                    'judul_dokumen' => ['type' => 'VARCHAR', 'constraint' => 255],
                    'content_html'  => ['type' => 'TEXT', 'null' => true],
                    'pdf_path'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                    'checksum'      => ['type' => 'VARCHAR', 'constraint' => 100], // SHA256 Hash
                    'status'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'DRAFT'], // DRAFT, REVIEW, APPROVED, REJECTED, ARCHIVED
                    'created_by'    => ['type' => 'VARCHAR', 'constraint' => 100],
                    'created_at'    => ['type' => 'DATETIME', 'null' => true],
                    'updated_at'    => ['type' => 'DATETIME', 'null' => true],
                    'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('documents', true);
            }

            // 2. Table `document_signatures`
            if (!$db->tableExists('document_signatures')) {
                $forge->addField([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'document_id' => ['type' => 'INT', 'constraint' => 11],
                    'user_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'signer_name' => ['type' => 'VARCHAR', 'constraint' => 100],
                    'signer_role' => ['type' => 'VARCHAR', 'constraint' => 100], // Petugas, Supervisor, Manager, Direktur
                    'signature_canvas_data' => ['type' => 'TEXT', 'null' => true],
                    'signature_date' => ['type' => 'DATETIME', 'null' => true],
                    'notes'       => ['type' => 'TEXT', 'null' => true],
                    'created_at'  => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('document_signatures', true);
            }

            // 3. Table `document_revisions`
            if (!$db->tableExists('document_revisions')) {
                $forge->addField([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                    'document_id'     => ['type' => 'INT', 'constraint' => 11],
                    'revision_number' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
                    'revised_by'      => ['type' => 'VARCHAR', 'constraint' => 100],
                    'revision_notes'  => ['type' => 'TEXT', 'null' => true],
                    'created_at'      => ['type' => 'DATETIME', 'null' => true],
                ]);
                $forge->addKey('id', true);
                $forge->createTable('document_revisions', true);
            }

        } catch (\Throwable $e) {
            log_message('error', '[DocumentModel] Gagal membuat tabel: ' . $e->getMessage());
        }
    }
}
