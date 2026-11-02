<?php

namespace App\Controllers;

use App\Models\UidModel;
use App\Models\Up3Model;

class UidController extends BaseController
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
        $uids = $db->table('uids')
            ->select('uids.*, COUNT(DISTINCT up3s.id) as total_up, COUNT(DISTINCT users.id) as total_users')
            ->join('up3s', 'up3s.uid_id = uids.id', 'left')
            ->join('users', 'users.uid_id = uids.id', 'left')
            ->groupBy('uids.id')
            ->orderBy('uids.nama_uid', 'ASC')
            ->get()
            ->getResultArray();

        return view('master_uid/index', [
            'title' => 'Master Data UID (Unit Induk Distribusi)',
            'uids'  => $uids
        ]);
    }

    public function create()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        return view('master_uid/form', [
            'title' => 'Tambah UID Baru',
            'uid'   => null
        ]);
    }

    public function store()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $rules = [
            'kode_uid' => 'required|max_length[50]|is_unique[uids.kode_uid]',
            'nama_uid' => 'required|max_length[150]',
            'status'   => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'kode_uid' => strtoupper(trim((string)$this->request->getPost('kode_uid'))),
            'nama_uid' => trim((string)$this->request->getPost('nama_uid')),
            'status'   => $this->request->getPost('status') ?: 'AKTIF'
        ];

        $this->uidModel->insert($data);
        log_activity('CREATE_UID', 'Menambahkan UID baru: ' . $data['nama_uid']);

        return redirect()->to(site_url('master-uid'))->with('success', 'Master UID berhasil ditambahkan.');
    }

    public function edit(int $id)
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $uid = $this->uidModel->find($id);
        if (!$uid) {
            return redirect()->to(site_url('master-uid'))->with('error', 'Data UID tidak ditemukan.');
        }

        return view('master_uid/form', [
            'title' => 'Edit UID: ' . $uid['nama_uid'],
            'uid'   => $uid
        ]);
    }

    public function update(int $id)
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $uid = $this->uidModel->find($id);
        if (!$uid) {
            return redirect()->to(site_url('master-uid'))->with('error', 'Data UID tidak ditemukan.');
        }

        $rules = [
            'kode_uid' => "required|max_length[50]|is_unique[uids.kode_uid,id,{$id}]",
            'nama_uid' => 'required|max_length[150]',
            'status'   => 'required|in_list[AKTIF,NONAKTIF]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $data = [
            'kode_uid' => strtoupper(trim((string)$this->request->getPost('kode_uid'))),
            'nama_uid' => trim((string)$this->request->getPost('nama_uid')),
            'status'   => $this->request->getPost('status') ?: 'AKTIF'
        ];

        $this->uidModel->update($id, $data);
        log_activity('UPDATE_UID', 'Memperbarui data UID: ' . $data['nama_uid']);

        return redirect()->to(site_url('master-uid'))->with('success', 'Master UID berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $uid = $this->uidModel->find($id);
        if (!$uid) {
            return redirect()->to(site_url('master-uid'))->with('error', 'Data UID tidak ditemukan.');
        }

        // Integrity check: cannot delete if UP3 is connected
        $attachedUp = $this->up3Model->where('uid_id', $id)->countAllResults();
        if ($attachedUp > 0) {
            return redirect()->to(site_url('master-uid'))->with('error', "Tidak dapat menghapus UID '{$uid['nama_uid']}' karena masih memiliki {$attachedUp} Unit Pelaksana (UP) terkait. Pindahkan atau hapus UP terlebih dahulu.");
        }

        $this->uidModel->delete($id);
        log_activity('DELETE_UID', 'Menghapus UID: ' . $uid['nama_uid']);

        return redirect()->to(site_url('master-uid'))->with('success', 'Master UID berhasil dihapus.');
    }
}
