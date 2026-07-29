<?php

namespace App\Controllers;

use App\Repositories\DocumentRepository;
use App\Services\DocumentIntelligenceService;

class DocumentCenter extends BaseController
{
    private DocumentRepository $repository;
    private DocumentIntelligenceService $service;

    public function __construct()
    {
        $this->repository = new DocumentRepository();
        $this->service    = new DocumentIntelligenceService();
    }

    public function index()
    {
        $filters = [
            'jenis_dokumen' => $this->request->getGet('jenis_dokumen'),
            'status'        => $this->request->getGet('status'),
            'search'        => $this->request->getGet('search'),
        ];

        $documents = $this->repository->getFilteredDocuments($filters, 50);

        return view('documents/index', [
            'documents' => $documents,
            'filters'   => $filters,
            'userRole'  => session()->get('user_role'),
        ]);
    }

    public function detail(int $id)
    {
        $doc = $this->repository->findDocumentDetail($id);
        if (!$doc) {
            return redirect()->to(site_url('documents'))->with('error', 'Dokumen tidak ditemukan.');
        }

        return view('documents/detail', [
            'doc'      => $doc,
            'userRole' => session()->get('user_role'),
        ]);
    }

    public function create()
    {
        return view('documents/form', [
            'doc' => null,
        ]);
    }

    public function store()
    {
        $data = [
            'jenis_dokumen' => $this->request->getPost('jenis_dokumen'),
            'judul_dokumen' => $this->request->getPost('judul_dokumen'),
            'content_html'  => $this->request->getPost('content_html'),
            'status'        => $this->request->getPost('status') ?: 'DRAFT',
            'created_by'    => session()->get('user_name') ?: 'System User',
        ];

        $docId = $this->service->createDocument($data);
        log_activity('CREATE_DOCUMENT', 'Menerbitkan Dokumen Resmi Baru: ' . $data['judul_dokumen']);

        return redirect()->to(site_url('documents/detail/' . $docId))->with('success', 'Dokumen resmi berhasil diterbitkan!');
    }

    public function approve(int $id)
    {
        $session = session();
        $signerData = [
            'user_id'               => $session->get('user_id'),
            'signer_name'           => $session->get('user_name') ?: 'Official Signer',
            'signer_role'           => $session->get('user_role') ?: 'Supervisor',
            'signature_canvas_data' => $this->request->getPost('signature_canvas_data'),
            'notes'                 => $this->request->getPost('notes'),
        ];

        $this->service->approveDocument($id, $signerData);
        log_activity('APPROVE_DOCUMENT', 'Menandatangani Dokumen ID #' . $id);

        return redirect()->to(site_url('documents/detail/' . $id))->with('success', 'Dokumen berhasil ditandatangani secara digital!');
    }

    /**
     * Public QR Code Verification Page (No Auth Filter Required)
     */
    public function verify(string $checksum)
    {
        $doc = $this->repository->findByChecksum($checksum);

        return view('documents/verify', [
            'doc'      => $doc,
            'checksum' => $checksum,
        ]);
    }
}
