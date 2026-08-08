<?php

namespace App\Controllers;

use App\Models\GarduIndukModel;

class GarduIndukController extends BaseController
{
    private GarduIndukModel $giModel;

    public function __construct()
    {
        $this->giModel = new GarduIndukModel();
    }

    public function index()
    {
        if (!check_role(['administrator', 'admin', 'admin_ulp'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $garduInduk = $this->giModel->orderBy('nama_gi', 'ASC')->findAll();

        return view('gardu_induk/index', [
            'garduInduk' => $garduInduk,
        ]);
    }

    public function store()
    {
        if (!check_role(['administrator', 'admin', 'admin_ulp'])) {
            return redirect()->to(site_url('master-gi'))->with('error', 'Akses ditolak.');
        }

        $namaGi = strtoupper(trim($this->request->getPost('nama_gi') ?: ''));
        if (strlen($namaGi) < 3) {
            return redirect()->to(site_url('master-gi'))->with('error', 'Nama Gardu Induk wajib diisi minimal 3 karakter.');
        }

        $kodeGi = $this->giModel->generateKodeGi($namaGi);

        $data = [
            'kode_gi'   => $kodeGi,
            'nama_gi'   => $namaGi,
            'lokasi'    => $this->request->getPost('lokasi'),
            'latitude'  => $this->request->getPost('latitude') ?: null,
            'longitude' => $this->request->getPost('longitude') ?: null,
            'status'    => $this->request->getPost('status') ?: 'ACTIVE',
        ];

        $this->giModel->insert($data);
        log_activity('CREATE_GI', "Membuat Gardu Induk Baru: {$data['nama_gi']} ({$data['kode_gi']})");

        return redirect()->to(site_url('master-gi'))->with('success', "Gardu Induk baru '{$data['nama_gi']}' ({$data['kode_gi']}) berhasil ditambahkan!");
    }

    public function update(int $id)
    {
        if (!check_role(['administrator', 'admin', 'admin_ulp'])) {
            return redirect()->to(site_url('master-gi'))->with('error', 'Akses ditolak.');
        }

        $gi = $this->giModel->find($id);
        if (!$gi) {
            return redirect()->to(site_url('master-gi'))->with('error', 'Data Gardu Induk tidak ditemukan.');
        }

        $data = [
            'nama_gi'   => strtoupper(trim($this->request->getPost('nama_gi'))),
            'lokasi'    => $this->request->getPost('lokasi'),
            'latitude'  => $this->request->getPost('latitude') ?: null,
            'longitude' => $this->request->getPost('longitude') ?: null,
            'status'    => $this->request->getPost('status') ?: 'ACTIVE',
        ];

        $this->giModel->update($id, $data);
        log_activity('UPDATE_GI', "Memperbarui Gardu Induk ID {$id}: {$data['nama_gi']}");

        return redirect()->to(site_url('master-gi'))->with('success', 'Data Gardu Induk berhasil diperbarui!');
    }

    public function delete(int $id)
    {
        if (!check_role(['administrator', 'admin'])) {
            return redirect()->to(site_url('master-gi'))->with('error', 'Akses ditolak.');
        }

        $gi = $this->giModel->find($id);
        if (!$gi) {
            return redirect()->to(site_url('master-gi'))->with('error', 'Data Gardu Induk tidak ditemukan.');
        }

        $this->giModel->delete($id);
        log_activity('DELETE_GI', "Menghapus Gardu Induk ID {$id}");

        return redirect()->to(site_url('master-gi'))->with('success', 'Gardu Induk berhasil dihapus.');
    }
}
