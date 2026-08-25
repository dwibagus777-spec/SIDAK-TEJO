<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Feeder Identity Resolution Service (CR-01 Phase 2)
 *
 * Responsibilities:
 * - Deterministic resolution of spreadsheet feeder strings against master `penyulang`.
 * - Tiered matching: Exact -> Normalized -> Similarity Candidates -> Unresolved.
 * - Zero mutation on master penyulang table (strictly read-only matching).
 */
class FeederIdentityResolutionService
{
    protected BaseConnection $db;
    protected ?array $masterFeeders = null;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Resolve a source feeder string to master penyulang.
     *
     * @param string|null $sourceFeederName Raw string from spreadsheet
     * @return array Resolution result payload
     */
    public function resolveFeeder(?string $sourceFeederName): array
    {
        $raw = trim((string)$sourceFeederName);
        if ($raw === '' || strtoupper($raw) === 'UNKNOWN_FEEDER') {
            return [
                'source_feeder_name'     => $sourceFeederName,
                'status'                 => 'UNRESOLVED',
                'resolved_penyulang_id'  => null,
                'resolved_penyulang_name'=> null,
                'confidence'             => 0.0,
                'candidates'             => [],
                'requires_manual_review' => true,
            ];
        }

        $feeders = $this->getMasterFeeders();
        $cleanUpper = strtoupper($raw);

        // 1. Exact Match
        foreach ($feeders as $f) {
            if (strtoupper($f['nama_penyulang']) === $cleanUpper || (!empty($f['kode_penyulang']) && strtoupper($f['kode_penyulang']) === $cleanUpper)) {
                return [
                    'source_feeder_name'     => $sourceFeederName,
                    'status'                 => 'EXACT_MATCH',
                    'resolved_penyulang_id'  => (int)$f['id'],
                    'resolved_penyulang_name'=> $f['nama_penyulang'],
                    'ulp_id'                 => (int)$f['ulp_id'],
                    'confidence'             => 1.0,
                    'candidates'             => [],
                    'requires_manual_review' => false,
                ];
            }
        }

        // 2. Normalized Match (strip common noise: (FDR), FDR, PENYULANG, special chars)
        $normalizedInput = $this->normalizeFeederString($cleanUpper);
        foreach ($feeders as $f) {
            $normMaster = $this->normalizeFeederString(strtoupper($f['nama_penyulang']));
            if ($normMaster === $normalizedInput && !empty($normalizedInput)) {
                return [
                    'source_feeder_name'     => $sourceFeederName,
                    'status'                 => 'NORMALIZED_MATCH',
                    'resolved_penyulang_id'  => (int)$f['id'],
                    'resolved_penyulang_name'=> $f['nama_penyulang'],
                    'ulp_id'                 => (int)$f['ulp_id'],
                    'confidence'             => 0.95,
                    'candidates'             => [],
                    'requires_manual_review' => false,
                ];
            }
        }

        // 3. Similarity Candidates (Levenshtein & Similar Text)
        $candidates = [];
        foreach ($feeders as $f) {
            $normMaster = $this->normalizeFeederString(strtoupper($f['nama_penyulang']));
            similar_text($normalizedInput, $normMaster, $percent);
            $sim = $percent / 100.0;

            // Substring bonus
            if (str_contains($normalizedInput, $normMaster) || str_contains($normMaster, $normalizedInput)) {
                $sim = max($sim, 0.85);
            }

            if ($sim >= 0.65) {
                $candidates[] = [
                    'penyulang_id'   => (int)$f['id'],
                    'nama_penyulang' => $f['nama_penyulang'],
                    'ulp_id'         => (int)$f['ulp_id'],
                    'confidence'     => round($sim, 2)
                ];
            }
        }

        // Sort candidates descending by confidence
        usort($candidates, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        if (!empty($candidates)) {
            $topCandidate = $candidates[0];

            // If top candidate is very high and significantly better than second
            $secondConf = $candidates[1]['confidence'] ?? 0.0;
            if ($topCandidate['confidence'] >= 0.85 && ($topCandidate['confidence'] - $secondConf) >= 0.15) {
                return [
                    'source_feeder_name'     => $sourceFeederName,
                    'status'                 => 'HIGH_CONFIDENCE_CANDIDATE',
                    'resolved_penyulang_id'  => $topCandidate['penyulang_id'],
                    'resolved_penyulang_name'=> $topCandidate['nama_penyulang'],
                    'ulp_id'                 => $topCandidate['ulp_id'],
                    'confidence'             => $topCandidate['confidence'],
                    'candidates'             => $candidates,
                    'requires_manual_review' => true, // Requires explicit acceptance
                ];
            }

            // Ambiguous multiple close candidates
            return [
                'source_feeder_name'     => $sourceFeederName,
                'status'                 => 'AMBIGUOUS_CANDIDATE',
                'resolved_penyulang_id'  => null,
                'resolved_penyulang_name'=> null,
                'confidence'             => $topCandidate['confidence'],
                'candidates'             => $candidates,
                'requires_manual_review' => true,
            ];
        }

        // 4. Unresolved
        return [
            'source_feeder_name'     => $sourceFeederName,
            'status'                 => 'UNRESOLVED',
            'resolved_penyulang_id'  => null,
            'resolved_penyulang_name'=> null,
            'confidence'             => 0.0,
            'candidates'             => [],
            'requires_manual_review' => true,
        ];
    }

    /**
     * Clean feeder string from noise words and formatting.
     */
    protected function normalizeFeederString(string $name): string
    {
        $clean = strtoupper($name);
        $clean = preg_replace('/\(FDR\)|\(FEEDER\)|FDR|FEEDER|PENYULANG|PENY\./i', '', $clean);
        $clean = preg_replace('/[^A-Z0-9]/', '', $clean);
        return trim((string)$clean);
    }

    /**
     * Lazy-load master feeders from DB in a single pass.
     */
    protected function getMasterFeeders(): array
    {
        if ($this->masterFeeders === null) {
            $this->masterFeeders = $this->db->table('penyulang')
                ->select('id, nama_penyulang, kode_penyulang, ulp_id')
                ->get()
                ->getResultArray();
        }
        return $this->masterFeeders;
    }
}
