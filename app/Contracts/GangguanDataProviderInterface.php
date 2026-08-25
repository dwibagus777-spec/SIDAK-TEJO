<?php

namespace App\Contracts;

/**
 * Gangguan Data Provider Interface
 *
 * Boundary contract for supplying feeder interruption and disturbance event records
 * to SIDAK TEJO Command Center and Preventive Risk Radar without coupling directly
 * to specific storage engines or file formats.
 */
interface GangguanDataProviderInterface
{
    /**
     * Check if the data source is reachable and contains usable interruption data.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get unique source identifier name.
     *
     * @return string
     */
    public function getSourceIdentifier(): string;

    /**
     * Retrieve all interruption records matching optional filter criteria.
     *
     * @param array $filters [ 'feeder_name' => '', 'category' => '', 'limit' => 100, 'date_from' => '', 'date_to' => '' ]
     * @return array Array of canonical interruption records
     */
    public function getInterruptions(array $filters = []): array;

    /**
     * Retrieve interruption records specific to a given feeder (by name or ID).
     *
     * @param int|string $penyulangIdentifier Feeder ID (int) or Feeder Name (string)
     * @param array $filters Additional filters
     * @return array Array of canonical interruption records for this feeder
     */
    public function getFeederInterruptions(int|string $penyulangIdentifier, array $filters = []): array;

    /**
     * Get aggregate statistics for interruptions (e.g. total events, total ENS kWh, median duration).
     *
     * @param array $filters
     * @return array [ 'total_events' => 0, 'permanent_count' => 0, 'temporary_count' => 0, 'median_duration_min' => 0.0, 'total_ens_kwh' => 0.0 ]
     */
    public function getInterruptionStats(array $filters = []): array;

    /**
     * Retrieve metadata regarding the provider source and freshness.
     *
     * @return array [ 'provider_class' => '', 'source_system' => '', 'record_count' => 0, 'last_synced_at' => null ]
     */
    public function getMetadata(): array;
}
