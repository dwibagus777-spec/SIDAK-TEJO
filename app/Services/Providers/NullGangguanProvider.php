<?php

namespace App\Services\Providers;

use App\Contracts\GangguanDataProviderInterface;

/**
 * Null Gangguan Provider
 *
 * Safe fallback provider when no active disturbance database or external spreadsheet
 * is attached. Returns empty datasets, zero statistics, and safe metadata.
 */
class NullGangguanProvider implements GangguanDataProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceIdentifier(): string
    {
        return 'NULL_GANGGUAN_PROVIDER';
    }

    /**
     * {@inheritdoc}
     */
    public function getInterruptions(array $filters = []): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getFeederInterruptions(int|string $penyulangIdentifier, array $filters = []): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInterruptionStats(array $filters = []): array
    {
        return [
            'total_events'        => 0,
            'permanent_count'     => 0,
            'temporary_count'     => 0,
            'median_duration_min' => 0.0,
            'total_ens_kwh'       => 0.0,
            'dominant_causes'     => [],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return [
            'provider_class'      => self::class,
            'source_system'       => 'NONE_FALLBACK',
            'is_available'        => false,
            'record_count'        => 0,
            'last_synced_at'      => null,
            'status_description'  => 'No external disturbance data source connected (Safe Null Fallback).',
        ];
    }
}
