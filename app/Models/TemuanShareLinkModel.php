<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for temuan_share_links
 *
 * CR-HOTFIX-03: Secure Share Links for Network Asset Findings
 */
class TemuanShareLinkModel extends Model
{
    protected $table            = 'temuan_share_links';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'temuan_id',
        'token_hash',
        'created_by',
        'created_at',
        'expires_at',
        'revoked_at',
        'is_active',
    ];

    protected $useTimestamps = false; // We manage created_at explicitly

    /**
     * Find a valid, non-expired, non-revoked share link by token hash.
     * Guaranteed ZERO database writes (Read-Only).
     */
    public function findValidByHash(string $tokenHash): ?array
    {
        $now = date('Y-m-d H:i:s');
        return $this->where('token_hash', $tokenHash)
            ->where('is_active', 1)
            ->where('revoked_at IS NULL')
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >', $now)
            ->groupEnd()
            ->first();
    }

    /**
     * Find latest active share link for a temuan ID.
     */
    public function findActiveByTemuanId(int $temuanId): ?array
    {
        $now = date('Y-m-d H:i:s');
        return $this->where('temuan_id', $temuanId)
            ->where('is_active', 1)
            ->where('revoked_at IS NULL')
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >', $now)
            ->groupEnd()
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Revoke all active share links for a temuan ID.
     */
    public function revokeByTemuanId(int $temuanId): bool
    {
        return $this->where('temuan_id', $temuanId)
            ->where('is_active', 1)
            ->set([
                'is_active'  => 0,
                'revoked_at' => date('Y-m-d H:i:s'),
            ])
            ->update();
    }
}
