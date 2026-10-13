<?php

namespace App\Services;

final class NetworkAccessScope
{
    private const CROSS_ULP_ROLES = ['administrator', 'pdkb', 'har_crane'];

    private string $role;
    private ?int $ulpId;

    public function __construct(string $role, ?int $ulpId)
    {
        $this->role = strtolower(trim($role));
        $this->ulpId = $ulpId !== null && $ulpId > 0 ? $ulpId : null;
    }

    public function isCrossUlp(): bool
    {
        return in_array($this->role, self::CROSS_ULP_ROLES, true);
    }

    public function getUlpId(): ?int
    {
        return $this->ulpId;
    }

    public function allowsUlp(?int $ulpId): bool
    {
        if ($this->isCrossUlp()) {
            return true;
        }

        return $this->ulpId !== null && $ulpId !== null && $this->ulpId === $ulpId;
    }
}
