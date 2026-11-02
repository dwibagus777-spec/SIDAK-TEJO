<?php

namespace App\Controllers;

use App\Models\UidModel;
use App\Models\Up3Model;

class Up3Controller extends BaseController
{
    private UidModel $uidModel;
    private Up3Model $up3Model;

    public function __construct()
    {
        $this->uidModel = new UidModel();
        $this->up3Model = new Up3Model();
    }

    public function index()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $db = \Config\Database::connect();
        $up3s = $db->table('up3s')
            ->select('up3s.*, uids.nama_uid, uids.kode_uid,
                      COUNT(DISTINCT ulps.id) as total_ulp,
                      COUNT(DISTINCT gardu_induk.id) as total_gi,
                      COUNT(DISTINCT users.id) as total_users')
            ->join('uids', 'uids.id = up3s.uid_id', 'left')
            ->join('ulps', 'ulps.up3_id = up3s.id', 'left')
            ->join('gardu_induk', 'gardu_induk.up3_id = up3s.id', 'left')
            ->join('users', 'users.up3_id = up3s.id', 'left')
            ->groupBy('up3s.id')
            ->orderBy('up3s.nama_up3', 'ASC')
            ->get()
            ->getResultArray();

        return view('master_up3/index', [
            'title' => 'Master Data Unit Pelaksana (UP / UP3 / UP2D)',
            'up3s'  => $up3s
        ]);
    }

    public function create()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $uids = $this->uidModel->where('status', 'AKTIF')->orderBy('nama_uid', 'ASC')->findAll();

        return view('master_up3/form', [
            'title' => 'Tambah Unit Pelaksana Baru',
            'up3'   => null,
            'uids'  => $uids
        ]);
    }

    public function store()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $rules = [
            'uid_id'   => 'required|is_not_unique[uids.id]',
            'kode_up3' => 'required|max_length[50]|is_unique[up3s.kode_up3]',
            'nama_up3' => 'required|max_length[150]',
            'status'   => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'uid_id'   => (int)$this->request->getPost('uid_id'),
            'kode_up3' => strtoupper(trim((string)$this->request->getPost('kode_up3'))),
            'nama_up3' => trim((string)$this->request->getPost('nama_up3')),
            'status'   => $this->request->getPost('status') ?: 'AKTIF'
        ];

        $this->up3Model->insert($data);
        log_activity('CREATE_UP3', 'Menambahkan UP3 baru: ' . $data['nama_up3']);

        return redirect()->to(site_url('master-up3'))->with('success', 'Master Unit Pelaksana berhasil ditambahkan.');
    }

    public function edit(int $id)
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $up3 = $this->up3Model->find($id);
        if (!$up3) {
            return redirect()->to(site_url('master-up3'))->with('error', 'Data Unit Pelaksana tidak ditemukan.');
        }

        $uids = $this->uidModel->where('status', 'AKTIF')->orderBy('nama_uid', 'ASC')->findAll();

        return view('master_up3/form', [
            'title' => 'Edit Unit Pelaksana: ' . $up3['nama_up3'],
            'up3'   => $up3,
            'uids'  => $uids
        ]);
    }

    public function update(int $id)
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $up3 = $this->up3Model->find($id);
        if (!$up3) {
            return redirect()->to(site_url('master-up3'))->with('error', 'Data Unit Pelaksana tidak ditemukan.');
        }

        $rules = [
            'uid_id'   => 'required|is_not_unique[uids.id]',
            'kode_up3' => "required|max_length[50]|is_unique[up3s.kode_up3,id,{$id}]",
            'nama_up3' => 'required|max_length[150]',
            'status'   => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'uid_id'   => (int)$this->request->getPost('uid_id'),
            'kode_up3' => strtoupper(trim((string)$this->request->getPost('kode_up3'))),
            'nama_up3' => trim((string)$this->request->getPost('nama_up3')),
            'status'   => $this->request->getPost('status') ?: 'AKTIF'
        ];

        $this->up3Model->update($id, $data);
        log_activity('UPDATE_UP3', 'Memperbarui Unit Pelaksana: ' . $data['nama_up3']);

        return redirect()->to(site_url('master-up3'))->with('success', 'Master Unit Pelaksana berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $up3 = $this->up3Model->find($id);
        if (!$up3) {
            return redirect()->to(site_url('master-up3'))->with('error', 'Data Unit Pelaksana tidak ditemukan.');
        }

        $db = \Config\Database::connect();
        $attachedUlps = $db->table('ulps')->where('up3_id', $id)->countAllResults();
        $attachedGi = $db->table('gardu_induk')->where('up3_id', $id)->countAllResults();

        if ($attachedUlps > 0 || $attachedGi > 0) {
            return redirect()->to(site_url('master-up3'))->with('error', "Tidak dapat menghapus UP '{$up3['nama_up3']}' karena masih memiliki {$attachedUlps} ULP dan {$attachedGi} Gardu Induk terkait. Pindahkan relasi terlebih dahulu.");
        }

        $this->up3Model->delete($id);
        log_activity('DELETE_UP3', 'Menghapus UP3: ' . $up3['nama_up3']);

        return redirect()->to(site_url('master-up3'))->with('success', 'Master Unit Pelaksana berhasil dihapus.');
    }
}
