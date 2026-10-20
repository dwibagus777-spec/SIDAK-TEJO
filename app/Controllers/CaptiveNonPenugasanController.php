<?php

namespace App\Controllers;

use App\Services\CaptiveNonPenugasanService;
use App\Services\GisTranslineService;
use Config\Database;
use Throwable;

class CaptiveNonPenugasanController extends BaseController
{
    public function index()
    {
        $session = session();
        $db = Database::connect();
        $userUlpId = (int)($session->get('user_ulp_id') ?? $session->get('ulp_id') ?? 0) ?: null;

        $ulps = [];
        if ($db->tableExists('ulps')) {
            $ulps = $db->table('ulps')->select('id,nama_ulp')->orderBy('nama_ulp')->get()->getResultArray();
            if ($userUlpId) $ulps = array_values(array_filter($ulps, static fn($u) => (int)$u['id'] === $userUlpId));
        }

        $feeders = [];
        if ($db->tableExists('penyulang')) {
            $feedersQuery = $db->table('penyulang')->select('id,ulp_id,nama_penyulang')->orderBy('nama_penyulang');
            if ($userUlpId) $feedersQuery->where('ulp_id', $userUlpId);
            $feeders = $feedersQuery->get()->getResultArray();
        }

        $sections = [];
        if ($db->tableExists('sections')) {
            $sections = $db->table('sections')->select('id,penyulang_id,nama_section')->orderBy('nama_section')->get()->getResultArray();
        }

        $service = new CaptiveNonPenugasanService($db);
        $service->ensureTableExists();
        $items = $service->list($userUlpId);

        $translineId = (int)($this->request->getGet('transline_id') ?? 0);
        $selectedLine = null;
        if ($translineId > 0 && $db->tableExists('gis_translines')) {
            $line = $db->table('gis_translines t')->select('t.id,t.penyulang_id,p.ulp_id')->join('penyulang p','p.id=t.penyulang_id','left')->where('t.id',$translineId)->where('t.is_active',1)->where('t.deleted_at IS NULL')->get()->getRowArray();
            if ($line && (!$userUlpId || (int)$line['ulp_id'] === $userUlpId)) {
                $result = (new GisTranslineService($db))->getAuthoritativeTranslines(['penyulang_id' => (int)$line['penyulang_id']], $userUlpId);
                foreach (($result['data'] ?? []) as $candidate) {
                    if ((int)$candidate['id'] === $translineId) { $candidate['ulp_id'] = (int)$line['ulp_id']; $selectedLine = $candidate; break; }
                }
            }
        }
        return view('captive_non_penugasan/index', [
            'items' => $items, 'ulps' => $ulps, 'selectedUlpId' => $userUlpId ?: (int)($this->request->getGet('ulp_id') ?? 0),
            'translineId' => $translineId, 'selectedLine' => $selectedLine, 'feeders' => $feeders, 'sections' => $sections,
            'conductors' => CaptiveNonPenugasanService::CONDUCTORS, 'sizes' => CaptiveNonPenugasanService::SIZES,
        ]);
    }

    public function store()
    {
        $session = session();
        try {
            $id = (new CaptiveNonPenugasanService())->create(
                $this->request->getPost(),
                (int)$session->get('user_id'),
                (string)($session->get('user_name') ?? 'Petugas'),
                (int)($session->get('user_ulp_id') ?? $session->get('ulp_id') ?? 0) ?: null
            );
            return redirect()->to(site_url('captive-non-penugasan'))->with('success', 'Catatan Captive tersimpan: #' . $id);
        } catch (Throwable $e) {
            log_message('error', 'Captive create failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function complete(int $id)
    {
        $session = session();
        try {
            (new CaptiveNonPenugasanService())->complete($id, (int)$session->get('user_id'), (int)($session->get('user_ulp_id') ?? $session->get('ulp_id') ?? 0) ?: null);
            return redirect()->to(site_url('captive-non-penugasan'))->with('success', 'Pekerjaan ditandai selesai; transline terkait telah diperbarui bila ini catatan coating.');
        } catch (Throwable $e) {
            log_message('error', 'Captive completion failed: {message}', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
