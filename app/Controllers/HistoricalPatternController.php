<?php

namespace App\Controllers;

use App\Services\HistoricalPatternIntelligenceService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Historical Pattern Controller (CR-03 Phase 2)
 *
 * Responsibilities:
 * - Render Pattern Intelligence Dashboard UI.
 * - Serve read-only JSON API for pattern intelligence summary.
 * - Serve read-only JSON API for feeder-specific pattern intelligence.
 * - Enforce zero database write boundary.
 */
class HistoricalPatternController extends BaseController
{
    protected HistoricalPatternIntelligenceService $patternService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->patternService = new HistoricalPatternIntelligenceService();
    }

    /**
     * Render the Pattern Intelligence Dashboard UI.
     */
    public function index()
    {
        $summary = $this->patternService->getSummaryIntelligence();
        $feeders = model('PenyulangModel')
            ->select('id, nama_penyulang, ulp_id')
            ->orderBy('nama_penyulang', 'ASC')
            ->findAll();

        return view('pattern_intelligence/index', [
            'title'   => 'Historical Pattern Intelligence & Recurrence Analytics | SIDAK TEJO',
            'summary' => $summary,
            'feeders' => $feeders,
        ]);
    }

    /**
     * API Endpoint: Summary Pattern Intelligence.
     * GET /api/pattern-intelligence/summary
     */
    public function apiSummary(): ResponseInterface
    {
        $summary = $this->patternService->getSummaryIntelligence();
        return $this->response->setJSON($summary);
    }

    /**
     * API Endpoint: Feeder Specific Pattern Intelligence.
     * GET /api/pattern-intelligence/feeder/{id}
     */
    public function apiFeeder(int $id): ResponseInterface
    {
        $data = $this->patternService->getFeederPatternIntelligence($id);
        if (!$data['success']) {
            return $this->response->setStatusCode(404)->setJSON($data);
        }
        return $this->response->setJSON($data);
    }
}
