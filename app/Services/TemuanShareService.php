<?php

namespace App\Services;

use App\Models\TemuanShareLinkModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Service for Temuan Sharing & Network Asset Context
 *
 * CR-HOTFIX-03:
 * - Secure opaque token generation (random_bytes(32) -> sha256 hash).
 * - Public read-only resolution with guaranteed ZERO database mutation (Delta DB = 0).
 * - Comprehensive Network Asset Context resolution.
 */
class TemuanShareService
{
    protected BaseConnection $db;
    protected TemuanShareLinkModel $shareLinkModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->shareLinkModel = new TemuanShareLinkModel();
    }

    /**
     * Generate a new secure public share link for a finding.
     */
    public function generateShareLink(int $temuanId, ?int $userId = null, int $durationDays = 30): array
    {
        if ($temuanId <= 0) {
            return [
                'status'  => 'ERROR',
                'message' => 'ID Temuan tidak valid.',
            ];
        }

        // Verify temuan exists
        $temuanExists = $this->db->table('temuan')->where('id', $temuanId)->countAllResults() > 0;
        if (!$temuanExists) {
            return [
                'status'  => 'NOT_FOUND',
                'message' => 'Data temuan tidak ditemukan.',
            ];
        }

        // Check if there is an existing active valid link
        $existing = $this->shareLinkModel->findActiveByTemuanId($temuanId);
        // Note: Because token_hash is one-way, we create a fresh link or rotate when explicitly requested.
        $rawToken = bin2hex(random_bytes(32)); // 64 hex chars
        $tokenHash = hash('sha256', $rawToken);
        $createdAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($durationDays * 86400));

        $inserted = $this->shareLinkModel->insert([
            'temuan_id'  => $temuanId,
            'token_hash' => $tokenHash,
            'created_by' => $userId,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'is_active'  => 1,
        ]);

        if (!$inserted) {
            return [
                'status'  => 'ERROR',
                'message' => 'Gagal membuat tautan berbagi.',
            ];
        }

        $shareUrl = site_url("temuan/share/{$rawToken}");

        return [
            'status'     => 'SUCCESS',
            'message'    => 'Tautan berbagi berhasil dibuat.',
            'share_url'  => $shareUrl,
            'token'      => $rawToken,
            'raw_token'  => $rawToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Resolve a raw share token to its database record.
     * Guaranteed ZERO database writes (Read-Only).
     */
    public function resolveShareToken(string $rawToken): ?array
    {
        $rawToken = trim($rawToken);
        if (strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        return $this->shareLinkModel->findValidByHash($tokenHash);
    }

    /**
     * Revoke active share links for a temuan ID.
     */
    public function revokeLink(int $temuanId): bool
    {
        return $this->shareLinkModel->revokeByTemuanId($temuanId);
    }

    /**
     * Fetch complete finding detail with Network Asset Context for public sharing.
     * Guaranteed ZERO database writes (Read-Only).
     */
    public function getShareFindingDetail(int $temuanId): ?array
    {
        if ($temuanId <= 0) {
            return null;
        }

        // 1. Fetch temuan with hierarchy
        $temuan = $this->db->table('temuan t')
            ->select('t.*, u.nama_ulp, p.nama_penyulang, s.nama_section')
            ->join('ulps u', 'u.id = t.ulp_id', 'left')
            ->join('penyulang p', 'p.id = t.penyulang_id', 'left')
            ->join('sections s', 's.id = t.section_id', 'left')
            ->where('t.id', $temuanId)
            ->get()
            ->getRowArray();

        if (!$temuan) {
            return null;
        }

        // 2. Fetch authoritative asset if linked
        $linkedAsset = null;
        if (!empty($temuan['asset_id']) && $this->db->tableExists('assets')) {
            $linkedAsset = $this->db->table('assets a')
                ->select('a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.latitude, a.longitude, a.construction_type_id, ct.construction_code, ct.construction_name, u.nama_ulp, p.nama_penyulang, s.nama_section')
                ->join('construction_types ct', 'ct.id = a.construction_type_id', 'left')
                ->join('ulps u', 'u.id = a.ulp_id', 'left')
                ->join('penyulang p', 'p.id = a.penyulang_id', 'left')
                ->join('sections s', 's.id = a.section_id', 'left')
                ->where('a.id', (int)$temuan['asset_id'])
                ->get()
                ->getRowArray();
        }

        // 3. Fetch structured materials (MR-01)
        $materials = [];
        if ($this->db->tableExists('temuan_materials')) {
            $materials = $this->db->table('temuan_materials tm')
                ->select('tm.id, tm.material_name, tm.quantity, tm.unit, tm.material_category, tm.is_special_device, ct.construction_code, ct.construction_name')
                ->join('construction_types ct', 'tm.construction_type_id = ct.id', 'left')
                ->where('tm.temuan_id', $temuanId)
                ->orderBy('tm.id', 'ASC')
                ->get()
                ->getResultArray() ?: [];
        }

        // 4. Fetch accessories (CR-HOTFIX-02)
        $accessories = [];
        if ($this->db->tableExists('temuan_accessories')) {
            $accService = new \App\Services\JtmAccessoryService($this->db);
            $accessories = $accService->getAccessoriesForTemuan($temuanId);
        }

        return [
            'temuan'      => $temuan,
            'asset'       => $linkedAsset,
            'materials'   => $materials,
            'accessories' => $accessories,
        ];
    }
}
