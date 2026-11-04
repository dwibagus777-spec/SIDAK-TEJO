<?php

namespace App\Controllers;

use App\Models\UidModel;
use App\Models\Up3Model;

class MasterHierarchyController extends BaseController
{
    private function safeGetRows($builder): array
    {
        try {
            $res = $builder->get();
            return ($res !== false && is_object($res)) ? $res->getResultArray() : [];
        } catch (\Throwable $e) {
            log_message('error', '[MasterHierarchyController] Query error: ' . $e->getMessage());
            return [];
        }
    }

    public function index()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Akses ditolak.');
        }

        $db = \Config\Database::connect();
        $feederTable = $db->tableExists('penyulang') ? 'penyulang' : ($db->tableExists('penyulangs') ? 'penyulangs' : null);

        // 1. Audit Unconnected Data
        $unlinkedAssets = [];
        $totalUnlinkedAssets = 0;
        if ($db->tableExists('assets')) {
            $assetBuilder = $db->table('assets')
                ->select('id, kode_asset AS asset_id, kode_asset, nama_asset, jenis_asset, latitude, longitude, created_at')
                ->groupStart()
                    ->where('penyulang_id IS NULL')
                    ->orWhere('penyulang_id', 0)
                ->groupEnd()
                ->where('status !=', 'DELETED');
            if ($db->fieldExists('deleted_at', 'assets')) {
                $assetBuilder->where('deleted_at IS NULL');
            }
            $unlinkedAssets = $this->safeGetRows($assetBuilder->limit(100));

            $countBuilder = $db->table('assets')
                ->groupStart()
                    ->where('penyulang_id IS NULL')
                    ->orWhere('penyulang_id', 0)
                ->groupEnd()
                ->where('status !=', 'DELETED');
            if ($db->fieldExists('deleted_at', 'assets')) {
                $countBuilder->where('deleted_at IS NULL');
            }
            try {
                $totalUnlinkedAssets = (int)$countBuilder->countAllResults();
            } catch (\Throwable $e) {
                $totalUnlinkedAssets = count($unlinkedAssets);
            }
        }

        $unlinkedFeedersGi = [];
        $unlinkedFeedersUlp = [];
        $penyulangs = [];
        if ($feederTable) {
            $unlinkedFeedersGi = $this->safeGetRows(
                $db->table($feederTable)
                    ->select('id, kode_penyulang, nama_penyulang, ulp_id')
                    ->groupStart()
                        ->where('gi_id IS NULL')
                        ->orWhere('gi_id', 0)
                    ->groupEnd()
            );

            $unlinkedFeedersUlp = $this->safeGetRows(
                $db->table($feederTable)
                    ->select('id, kode_penyulang, nama_penyulang, gi_id')
                    ->groupStart()
                        ->where('ulp_id IS NULL')
                        ->orWhere('ulp_id', 0)
                    ->groupEnd()
            );

            $penyulangs = $this->safeGetRows(
                $db->table($feederTable)->orderBy('nama_penyulang')
            );
        }

        $unlinkedUlps = $db->tableExists('ulps') ? $this->safeGetRows(
            $db->table('ulps')
                ->select('id, kode_ulp, nama_ulp')
                ->groupStart()
                    ->where('up3_id IS NULL')
                    ->orWhere('up3_id', 0)
                ->groupEnd()
        ) : [];

        $unlinkedGi = $db->tableExists('gardu_induk') ? $this->safeGetRows(
            $db->table('gardu_induk')
                ->select('id, kode_gi, nama_gi')
                ->groupStart()
                    ->where('up3_id IS NULL')
                    ->orWhere('up3_id', 0)
                ->groupEnd()
        ) : [];

        $unlinkedUp3 = $db->tableExists('up3s') ? $this->safeGetRows(
            $db->table('up3s')
                ->select('id, kode_up3, nama_up3')
                ->groupStart()
                    ->where('uid_id IS NULL')
                    ->orWhere('uid_id', 0)
                ->groupEnd()
        ) : [];

        // Master lists for quick assignment
        $uids = $db->tableExists('uids') ? $this->safeGetRows($db->table('uids')->where('status', 'AKTIF')->orderBy('nama_uid')) : [];
        $up3s = $db->tableExists('up3s') ? $this->safeGetRows($db->table('up3s')->where('status', 'AKTIF')->orderBy('nama_up3')) : [];
        $garduInduk = $db->tableExists('gardu_induk') ? $this->safeGetRows($db->table('gardu_induk')->orderBy('nama_gi')) : [];
        $ulps = $db->tableExists('ulps') ? $this->safeGetRows($db->table('ulps')->where('status', 'AKTIF')->orderBy('nama_ulp')) : [];

        return view('master_hierarchy/unconnected', [
            'title'               => 'Audit & Perapian Relasi Hierarki Jaringan',
            'unlinkedAssets'      => $unlinkedAssets,
            'totalUnlinkedAssets' => $totalUnlinkedAssets,
            'unlinkedFeedersGi'   => $unlinkedFeedersGi,
            'unlinkedFeedersUlp'  => $unlinkedFeedersUlp,
            'unlinkedUlps'        => $unlinkedUlps,
            'unlinkedGi'          => $unlinkedGi,
            'unlinkedUp3'         => $unlinkedUp3,
            'uids'                => $uids,
            'up3s'                => $up3s,
            'garduInduk'          => $garduInduk,
            'ulps'                => $ulps,
            'penyulangs'          => $penyulangs
        ]);
    }

    public function assignAssetFeeder()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Akses ditolak.'])->setStatusCode(403);
        }

        $assetId = (int)$this->request->getPost('asset_id');
        $penyulangId = (int)$this->request->getPost('penyulang_id');

        if ($assetId <= 0 || $penyulangId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Parameter tidak valid.'])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        $feederTable = $db->tableExists('penyulang') ? 'penyulang' : 'penyulangs';
        $q = $db->table($feederTable)->where('id', $penyulangId)->get();
        $penyulang = ($q !== false && is_object($q)) ? $q->getRowArray() : null;
        if (!$penyulang) {
            return $this->response->setJSON(['success' => false, 'message' => 'Penyulang tidak ditemukan.'])->setStatusCode(404);
        }

        $db->table('assets')->where('id', $assetId)->update([
            'penyulang_id' => $penyulangId,
            'ulp_id'       => $penyulang['ulp_id'] ?: 1,
            'updated_at'   => date('Y-m-d H:i:s')
        ]);

        log_activity('RECONCILE_ASSET', "Menautkan Aset ID {$assetId} ke Penyulang {$penyulang['nama_penyulang']}");

        return $this->response->setJSON([
            'success' => true,
            'message' => "Aset berhasil ditautkan ke penyulang {$penyulang['nama_penyulang']}."
        ]);
    }

    public function assignFeederGi()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Akses ditolak.'])->setStatusCode(403);
        }

        $penyulangId = (int)$this->request->getPost('penyulang_id');
        $giId = (int)$this->request->getPost('gi_id');

        if ($penyulangId <= 0 || $giId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Parameter tidak valid.'])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        $feederTable = $db->tableExists('penyulang') ? 'penyulang' : 'penyulangs';
        $db->table($feederTable)->where('id', $penyulangId)->update(['gi_id' => $giId]);
        log_activity('RECONCILE_FEEDER', "Menautkan Penyulang ID {$penyulangId} ke GI ID {$giId}");

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Penyulang berhasil ditautkan ke Gardu Induk.'
        ]);
    }

    public function assignUlpUp3()
    {
        if (!check_role(['administrator', 'admin', 'admin_pusat'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Akses ditolak.'])->setStatusCode(403);
        }

        $ulpId = (int)$this->request->getPost('ulp_id');
        $up3Id = (int)$this->request->getPost('up3_id');

        if ($ulpId <= 0 || $up3Id <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Parameter tidak valid.'])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        $db->table('ulps')->where('id', $ulpId)->update(['up3_id' => $up3Id]);
        log_activity('RECONCILE_ULP', "Menautkan ULP ID {$ulpId} ke UP ID {$up3Id}");

        return $this->response->setJSON([
            'success' => true,
            'message' => 'ULP berhasil ditautkan ke UP.'
        ]);
    }
}
