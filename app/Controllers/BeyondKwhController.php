<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\InspeksiKubikelRekomendasiModel;
use App\Models\InspeksiKubikelTemuanModel;
use App\Models\BeyondKwhProbingModel;
use App\Models\BeyondKwhPenawaranModel;
use App\Models\BeyondKwhSpkPoModel;
use App\Models\BeyondKwhPenagihanModel;
use App\Models\BeyondKwhPembayaranModel;

class BeyondKwhController extends BaseController
{
    protected $rekomendasiModel;
    protected $temuanModel;
    protected $probingModel;
    protected $penawaranModel;
    protected $spkPoModel;
    protected $penagihanModel;
    protected $pembayaranModel;

    public function __construct()
    {
        $this->rekomendasiModel = new InspeksiKubikelRekomendasiModel();
        $this->temuanModel      = new InspeksiKubikelTemuanModel();
        $this->probingModel     = new BeyondKwhProbingModel();
        $this->penawaranModel   = new BeyondKwhPenawaranModel();
        $this->spkPoModel       = new BeyondKwhSpkPoModel();
        $this->penagihanModel   = new BeyondKwhPenagihanModel();
        $this->pembayaranModel  = new BeyondKwhPembayaranModel();
    }

    /**
     * Verifikasi hak akses pengguna untuk rekomendasi yang dipilih
     *
     * @return array|null [rekomendasi, temuan, inspeksi] atau null jika ditolak/tidak ditemukan
     */
    protected function verifyAccess(int $rekomendasiId): ?array
    {
        $session = session();
        $role    = strtolower((string) $session->get('user_role'));
        $userId  = (int) $session->get('user_id');
        $isAdmin = in_array($role, ['administrator', 'admin', 'admin_pusat'], true);

        if ($role !== 'beyond_kwh' && ! $isAdmin) {
            return null;
        }

        $rekomendasi = $this->rekomendasiModel->find($rekomendasiId);
        if (! $rekomendasi) {
            return null;
        }

        // Jika bukan admin, pastikan user adalah penerima yang ditugaskan
        if (! $isAdmin && (int) ($rekomendasi['penerima_user_id'] ?? 0) !== $userId) {
            return null;
        }

        $temuan = $this->temuanModel->find($rekomendasi['temuan_id'] ?? 0);
        if (! $temuan) {
            $temuan = ['id' => $rekomendasi['temuan_id'] ?? 0, 'inspeksi_kubikel_id' => 0];
        }

        $db = \Config\Database::connect();
        $inspeksi = null;
        if (! empty($temuan['inspeksi_kubikel_id']) && $db->tableExists('inspeksi_kubikel')) {
            $inspeksi = $db->table('inspeksi_kubikel')->where('id', $temuan['inspeksi_kubikel_id'])->get()->getRowArray();
        }

        return [
            'rekomendasi' => $rekomendasi,
            'temuan'      => $temuan,
            'inspeksi'    => $inspeksi ?? [],
        ];
    }

    // ==========================================
    // 1. TAHAP PROBING
    // ==========================================

    public function probing(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $history = [];
        if ($this->probingModel->db->tableExists('beyond_kwh_probing')) {
            $history = $this->probingModel
                ->where('rekomendasi_id', $id)
                ->orWhere('temuan_id', $ctx['temuan']['id'])
                ->orderBy('tanggal_probing', 'DESC')
                ->findAll();
        }

        return view('kubikel_inspeksi/beyond_kwh/probing/create', [
            'rekomendasi' => $ctx['rekomendasi'],
            'temuan'      => $ctx['temuan'],
            'history'     => $history,
        ]);
    }

    public function storeProbing(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $tanggal = $this->request->getPost('tanggal_probing') ?: date('Y-m-d');
        $uraian  = (string) $this->request->getPost('uraian');

        $data = [
            'rekomendasi_id'      => $id,
            'temuan_id'           => (int) ($ctx['temuan']['id'] ?? 0),
            'inspeksi_kubikel_id' => (int) ($ctx['temuan']['inspeksi_kubikel_id'] ?? 0),
            'asset_id'            => ! empty($ctx['inspeksi']['asset_id']) ? (int) $ctx['inspeksi']['asset_id'] : null,
            'user_id'             => (int) session()->get('user_id'),
            'tanggal_probing'     => $tanggal,
            'uraian'              => $uraian,
        ];

        try {
            $this->probingModel->insert($data);

            // Update status rekomendasi jika masih TERKIRIM atau DIBACA
            if (in_array($ctx['rekomendasi']['status'] ?? '', ['TERKIRIM', 'DIBACA'], true)) {
                $this->rekomendasiModel->update($id, [
                    'status' => 'DIPROSES',
                    'catatan_penerima' => 'Probing dimulai pada ' . date('d/m/Y') . ($uraian ? ': ' . mb_substr($uraian, 0, 100) : ''),
                ]);
            }

            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh/detail/' . $id))->with('success', 'Data Probing berhasil disimpan.');
        } catch (\Throwable $e) {
            log_message('error', '[BeyondKwhController::storeProbing] ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan data probing: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 2. TAHAP PENAWARAN
    // ==========================================

    public function penawaran(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $history = [];
        if ($this->penawaranModel->db->tableExists('beyond_kwh_penawaran')) {
            $history = $this->penawaranModel
                ->where('rekomendasi_id', $id)
                ->orWhere('temuan_id', $ctx['temuan']['id'])
                ->orderBy('tanggal_penawaran', 'DESC')
                ->findAll();
        }

        return view('kubikel_inspeksi/beyond_kwh/penawaran/create', [
            'rekomendasi' => $ctx['rekomendasi'],
            'temuan'      => $ctx['temuan'],
            'history'     => $history,
        ]);
    }

    public function storePenawaran(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $tanggal = $this->request->getPost('tanggal_penawaran') ?: date('Y-m-d');
        $nomor   = (string) $this->request->getPost('nomor_penawaran');
        $nominal = (float) str_replace(',', '', (string) $this->request->getPost('nominal'));
        $uraian  = (string) $this->request->getPost('uraian');

        $data = [
            'rekomendasi_id'   => $id,
            'temuan_id'        => (int) ($ctx['temuan']['id'] ?? 0),
            'asset_id'         => ! empty($ctx['inspeksi']['asset_id']) ? (int) $ctx['inspeksi']['asset_id'] : null,
            'user_id'          => (int) session()->get('user_id'),
            'tanggal_penawaran'=> $tanggal,
            'nomor_penawaran'  => $nomor,
            'uraian'           => $uraian,
            'nominal'          => $nominal,
        ];

        try {
            $this->penawaranModel->insert($data);
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh/detail/' . $id))->with('success', 'Data Penawaran berhasil disimpan.');
        } catch (\Throwable $e) {
            log_message('error', '[BeyondKwhController::storePenawaran] ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan data penawaran: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 3. TAHAP SPK / PO
    // ==========================================

    public function spkpo(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $history = [];
        if ($this->spkPoModel->db->tableExists('beyond_kwh_spkpo')) {
            $history = $this->spkPoModel
                ->where('rekomendasi_id', $id)
                ->orWhere('temuan_id', $ctx['temuan']['id'])
                ->orderBy('tanggal_spkpo', 'DESC')
                ->findAll();
        }

        return view('kubikel_inspeksi/beyond_kwh/spkpo/create', [
            'rekomendasi' => $ctx['rekomendasi'],
            'temuan'      => $ctx['temuan'],
            'history'     => $history,
        ]);
    }

    public function storeSpkPo(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $tanggalSpk     = $this->request->getPost('tanggal_spkpo') ?: date('Y-m-d');
        $tanggalBerlaku = $this->request->getPost('tanggal_berlaku') ?: null;
        $tanggalSelesai = $this->request->getPost('tanggal_selesai_pekerjaan') ?: null;
        $nomorSpk       = (string) $this->request->getPost('nomor_spkpo');
        $nominal        = (float) str_replace(',', '', (string) $this->request->getPost('nominal'));
        $uraian         = (string) $this->request->getPost('uraian_jenis_perbaikan');

        $data = [
            'rekomendasi_id'            => $id,
            'temuan_id'                 => (int) ($ctx['temuan']['id'] ?? 0),
            'asset_id'                  => ! empty($ctx['inspeksi']['asset_id']) ? (int) $ctx['inspeksi']['asset_id'] : null,
            'user_id'                   => (int) session()->get('user_id'),
            'penawaran_id'              => null,
            'tanggal_spkpo'             => $tanggalSpk,
            'tanggal_berlaku'           => $tanggalBerlaku,
            'tanggal_selesai_pekerjaan' => $tanggalSelesai,
            'nomor_spkpo'               => $nomorSpk,
            'uraian_jenis_perbaikan'    => $uraian,
            'nominal'                   => $nominal,
        ];

        try {
            $this->spkPoModel->insert($data);
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh/detail/' . $id))->with('success', 'Data SPK/PO berhasil disimpan.');
        } catch (\Throwable $e) {
            log_message('error', '[BeyondKwhController::storeSpkPo] ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan SPK/PO: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 4. TAHAP PENAGIHAN
    // ==========================================

    public function penagihan(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $spkpoList = [];
        if ($this->spkPoModel->db->tableExists('beyond_kwh_spkpo')) {
            $spkpoList = $this->spkPoModel
                ->where('rekomendasi_id', $id)
                ->orWhere('temuan_id', $ctx['temuan']['id'])
                ->findAll();

            if (empty($spkpoList)) {
                $spkpoList = $this->spkPoModel->orderBy('id', 'DESC')->findAll(20);
            }
        }

        return view('kubikel_inspeksi/beyond_kwh/penagihan/create', [
            'rekomendasi' => $ctx['rekomendasi'],
            'temuan'      => $ctx['temuan'],
            'spkpoList'   => $spkpoList,
        ]);
    }

    public function storePenagihan(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $tanggal    = $this->request->getPost('tanggal_penagihan') ?: date('Y-m-d');
        $spkpoId    = (int) $this->request->getPost('spkpo_id');
        $nomorBapp  = (string) $this->request->getPost('nomor_bapp');
        $nomorBast  = (string) $this->request->getPost('nomor_bast');
        $nominal    = (float) str_replace(',', '', (string) $this->request->getPost('nominal'));
        $keterangan = (string) $this->request->getPost('keterangan');

        // Cari nomor SPK/PO dari record terkait
        $nomorSpk = '';
        if ($spkpoId > 0 && $this->spkPoModel->db->tableExists('beyond_kwh_spkpo')) {
            $spk = $this->spkPoModel->find($spkpoId);
            $nomorSpk = $spk['nomor_spkpo'] ?? '';
        }

        $data = [
            'rekomendasi_id'    => $id,
            'temuan_id'         => (int) ($ctx['temuan']['id'] ?? 0),
            'spkpo_id'          => $spkpoId > 0 ? $spkpoId : null,
            'user_id'           => (int) session()->get('user_id'),
            'tanggal_penagihan' => $tanggal,
            'nomor_spkpo'       => $nomorSpk,
            'nomor_bapp'        => $nomorBapp,
            'nomor_bast'        => $nomorBast,
            'nominal'           => $nominal,
            'keterangan'        => $keterangan,
        ];

        try {
            $this->penagihanModel->insert($data);
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh/detail/' . $id))->with('success', 'Data Penagihan berhasil disimpan.');
        } catch (\Throwable $e) {
            log_message('error', '[BeyondKwhController::storePenagihan] ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan penagihan: ' . $e->getMessage());
        }
    }

    // ==========================================
    // 5. TAHAP PEMBAYARAN
    // ==========================================

    public function pembayaran(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $spkpoList = [];
        if ($this->spkPoModel->db->tableExists('beyond_kwh_spkpo')) {
            $spkpoList = $this->spkPoModel
                ->where('rekomendasi_id', $id)
                ->orWhere('temuan_id', $ctx['temuan']['id'])
                ->findAll();

            if (empty($spkpoList)) {
                $spkpoList = $this->spkPoModel->orderBy('id', 'DESC')->findAll(20);
            }
        }

        $penagihanList = [];
        if ($this->penagihanModel->db->tableExists('beyond_kwh_penagihan')) {
            $penagihanList = $this->penagihanModel
                ->where('rekomendasi_id', $id)
                ->orWhere('temuan_id', $ctx['temuan']['id'])
                ->findAll();

            if (empty($penagihanList)) {
                $penagihanList = $this->penagihanModel->orderBy('id', 'DESC')->findAll(20);
            }
        }

        return view('kubikel_inspeksi/beyond_kwh/pembayaran/create', [
            'rekomendasi'   => $ctx['rekomendasi'],
            'temuan'        => $ctx['temuan'],
            'spkpoList'     => $spkpoList,
            'penagihanList' => $penagihanList,
        ]);
    }

    public function storePembayaran(int $id)
    {
        $ctx = $this->verifyAccess($id);
        if (! $ctx) {
            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh'))->with('error', 'Akses ditolak atau data tidak ditemukan.');
        }

        $tanggal      = $this->request->getPost('tanggal_pembayaran') ?: date('Y-m-d');
        $spkpoId      = (int) $this->request->getPost('spkpo_id');
        $penagihanId  = (int) $this->request->getPost('penagihan_id');
        $nomorSpk     = (string) $this->request->getPost('nomor_spkpo');
        $nomorInvoice = (string) $this->request->getPost('nomor_invoice');
        $nomorBapp    = (string) $this->request->getPost('nomor_bapp');
        $nomorBast    = (string) $this->request->getPost('nomor_bast');
        $nominal      = (float) str_replace(',', '', (string) $this->request->getPost('nominal'));
        $keterangan   = (string) $this->request->getPost('keterangan');

        if (empty($nomorSpk) && $spkpoId > 0 && $this->spkPoModel->db->tableExists('beyond_kwh_spkpo')) {
            $spk = $this->spkPoModel->find($spkpoId);
            $nomorSpk = $spk['nomor_spkpo'] ?? '';
        }

        $data = [
            'rekomendasi_id'     => $id,
            'temuan_id'          => (int) ($ctx['temuan']['id'] ?? 0),
            'spkpo_id'           => $spkpoId > 0 ? $spkpoId : null,
            'penagihan_id'       => $penagihanId > 0 ? $penagihanId : null,
            'user_id'            => (int) session()->get('user_id'),
            'tanggal_pembayaran' => $tanggal,
            'nomor_spkpo'        => $nomorSpk,
            'nomor_invoice'      => $nomorInvoice,
            'nomor_bapp'         => $nomorBapp,
            'nomor_bast'         => $nomorBast,
            'nominal'            => $nominal,
            'keterangan'         => $keterangan,
        ];

        try {
            $this->pembayaranModel->insert($data);

            // Update status rekomendasi menjadi SELESAI jika pembayaran masuk
            $this->rekomendasiModel->update($id, [
                'status'        => 'SELESAI',
                'waktu_selesai' => date('Y-m-d H:i:s'),
                'catatan_penerima' => 'Pembayaran selesai (' . ($nomorInvoice ? 'Inv: ' . $nomorInvoice . ', ' : '') . 'Nominal: Rp ' . number_format($nominal, 0, ',', '.') . ')',
            ]);

            return redirect()->to(site_url('kubikel-inspeksi/beyond-kwh/detail/' . $id))->with('success', 'Data Pembayaran berhasil disimpan dan status rekomendasi diselesaikan.');
        } catch (\Throwable $e) {
            log_message('error', '[BeyondKwhController::storePembayaran] ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan pembayaran: ' . $e->getMessage());
        }
    }
}
