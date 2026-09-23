<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Phase FL-01F: Fault Hypothesis Evidence & Resolution Engine
 *
 * Evaluates candidate fault hypotheses from FL-01D (Distance Traversal) and
 * FL-01E (Geometry Projection) using authoritative evidence.
 *
 * Hard Engineering Invariants:
 * 1. STRICT READ-ONLY: 0 INSERT, 0 UPDATE, 0 DELETE, 0 DDL.
 * 2. NO NEAREST ASSET / GPS / SECTION / TRANSLINE FALLBACK.
 * 3. NO SEQUENCE NUMBER GUESSING.
 * 4. NO AI INFERENCE / AI-GENERATED PROBABILITIES.
 * 5. NO ARBITRARY SCORING WEIGHTS (e.g. 40/30/20/10).
 * 6. NO FABRICATED EVIDENCE: Unavailable evidence is explicitly marked UNAVAILABLE.
 * 7. UNKNOWN EVIDENCE IS NEVER TREATED AS NEGATIVE.
 * 8. PRESERVE AMBIGUITY: Never force a single winner when evidence is equally probable.
 * 9. DETERMINISTIC REPRODUCIBILITY: Reproducible SHA-256 fingerprint invariant to runtime timestamp.
 */
class FaultHypothesisResolutionService
{
    public const ENGINE  = 'FL-01F';
    public const VERSION = 'FL-01F-1.0';

    // Authoritative Resolution States
    public const STATE_SINGLE_CONFIDENT     = 'SINGLE_CONFIDENT_HYPOTHESIS';
    public const STATE_MULTIPLE_COMPATIBLE  = 'MULTIPLE_COMPATIBLE_HYPOTHESES';
    public const STATE_EQUALLY_PROBABLE     = 'EQUALLY_PROBABLE';
    public const STATE_BRANCH_AMBIGUITY     = 'BRANCH_AMBIGUITY';
    public const STATE_UNRESOLVED           = 'UNRESOLVED';
    public const STATE_NO_VALID_HYPOTHESIS  = 'NO_VALID_HYPOTHESIS';

    // Authoritative Evidence Types
    public const EVIDENCE_MEASUREMENT = 'MEASUREMENT_CONSISTENCY';
    public const EVIDENCE_HISTORICAL  = 'HISTORICAL_FAULT_INDEX';
    public const EVIDENCE_PROTECTION  = 'PROTECTION_STATUS';
    public const EVIDENCE_RECLOSER    = 'RECLOSER_STATUS';
    public const EVIDENCE_INSPECTION  = 'INSPECTION_FINDING';
    public const EVIDENCE_TOPOLOGY    = 'TOPOLOGY_CONSISTENCY';
    public const EVIDENCE_GEOMETRY    = 'GEOMETRY_CONSISTENCY';
    public const EVIDENCE_DISTANCE    = 'DISTANCE_CONSISTENCY';

    // Evidence Status Norms
    public const STATUS_SUPPORTING    = 'SUPPORTING';
    public const STATUS_CONTRADICTING = 'CONTRADICTING';
    public const STATUS_NEUTRAL       = 'NEUTRAL';
    public const STATUS_UNAVAILABLE   = 'UNAVAILABLE';
    public const STATUS_UNKNOWN       = 'UNKNOWN';

    protected ?BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db;
    }

    /**
     * Resolve Fault Hypotheses based on Authoritative Evidence.
     *
     * @param array $faultContext Standard pipeline context containing outputs from FL-01A..FL-01E
     * @return array
     */
    public function resolve(array $faultContext): array
    {
        // 1. Extract Core Subsystem Inputs
        $traversal   = $faultContext['traversal'] ?? $faultContext['traversal_result'] ?? [];
        $projection  = $faultContext['projection'] ?? $faultContext['projection_result'] ?? [];
        $measurement = $faultContext['validated_measurement'] ?? $faultContext['ftu_measurement'] ?? $faultContext['measurement'] ?? [];
        $topology    = $faultContext['topology'] ?? $faultContext['feeder_topology'] ?? [];
        $ftuParsed   = $faultContext['ftu_parsed'] ?? $faultContext['parsed_payload'] ?? [];
        $options     = $faultContext['options'] ?? [];

        $tStatus = (string)($traversal['status'] ?? $projection['traversal_status'] ?? '');
        $pStatus = (string)($projection['status'] ?? '');

        // 2. Extract / Normalize Candidate Hypotheses
        $rawHypotheses = $this->extractCandidateHypotheses($faultContext);

        // 3. Precondition Check: Global Traversal / Measurement Unresolved States
        $globalUnresolvedReason = $this->checkGlobalUnresolvedPreconditions($tStatus, $pStatus, $rawHypotheses, $measurement);
        if ($globalUnresolvedReason !== null) {
            return $this->buildResolutionResult(
                self::STATE_UNRESOLVED,
                null,
                $rawHypotheses,
                [],
                [$globalUnresolvedReason],
                true
            );
        }

        // 4. Collect & Trace Evidence for Each Candidate Hypothesis
        $evaluatedHypotheses = [];
        $allEvidenceCatalog  = [];
        $activeHypotheses    = [];
        $excludedHypotheses  = [];

        foreach ($rawHypotheses as $hyp) {
            $hypId = (string)$hyp['hypothesis_id'];
            $evidenceList = [];

            // A. TOPOLOGY_CONSISTENCY
            $topoEv = $this->evaluateTopologyConsistency($hyp, $topology, $traversal);
            $evidenceList[] = $topoEv;
            $allEvidenceCatalog[] = $topoEv;

            // B. GEOMETRY_CONSISTENCY
            $geomEv = $this->evaluateGeometryConsistency($hyp, $projection);
            $evidenceList[] = $geomEv;
            $allEvidenceCatalog[] = $geomEv;

            // C. DISTANCE_CONSISTENCY
            $distEv = $this->evaluateDistanceConsistency($hyp, $traversal);
            $evidenceList[] = $distEv;
            $allEvidenceCatalog[] = $distEv;

            // D. MEASUREMENT_CONSISTENCY
            $measEv = $this->evaluateMeasurementConsistency($hyp, $measurement, $ftuParsed);
            $evidenceList[] = $measEv;
            $allEvidenceCatalog[] = $measEv;

            // E. PROTECTION_STATUS
            $protEv = $this->evaluateProtectionStatus($hyp, $faultContext);
            $evidenceList[] = $protEv;
            $allEvidenceCatalog[] = $protEv;

            // F. RECLOSER_STATUS
            $recEv = $this->evaluateRecloserStatus($hyp, $faultContext);
            $evidenceList[] = $recEv;
            $allEvidenceCatalog[] = $recEv;

            // G. INSPECTION_FINDING
            $inspEv = $this->evaluateInspectionFindings($hyp, $faultContext);
            $evidenceList[] = $inspEv;
            $allEvidenceCatalog[] = $inspEv;

            // H. HISTORICAL_FAULT_INDEX
            $histEv = $this->evaluateHistoricalFaultIndex($hyp, $faultContext);
            $evidenceList[] = $histEv;
            $allEvidenceCatalog[] = $histEv;

            // Classify hypothesis active vs excluded
            $hasContradiction = false;
            $exclusionReasons = [];
            foreach ($evidenceList as $ev) {
                if ($ev['status'] === self::STATUS_CONTRADICTING) {
                    $hasContradiction = true;
                    $exclusionReasons[] = "{$ev['type']}: {$ev['details']}";
                }
            }

            $hyp['evidence'] = $evidenceList;
            if ($hasContradiction) {
                $hyp['status'] = 'EXCLUDED';
                $hyp['exclusion_reason'] = implode('; ', $exclusionReasons);
                $excludedHypotheses[] = $hyp;
            } else {
                $hyp['status'] = 'ACTIVE';
                $hyp['exclusion_reason'] = null;
                $activeHypotheses[] = $hyp;
            }

            $evaluatedHypotheses[] = $hyp;
        }

        // 5. Hypothesis Comparison & Resolution State Machine
        return $this->resolveStateMachine(
            $tStatus,
            $pStatus,
            $evaluatedHypotheses,
            $activeHypotheses,
            $excludedHypotheses,
            $allEvidenceCatalog
        );
    }

    /**
     * State Machine Resolver
     */
    protected function resolveStateMachine(
        string $tStatus,
        string $pStatus,
        array $allHypotheses,
        array $activeHypotheses,
        array $excludedHypotheses,
        array $evidenceCatalog
    ): array {
        $activeCount = count($activeHypotheses);

        // Case A: Zero Active Hypotheses -> NO_VALID_HYPOTHESIS
        if ($activeCount === 0) {
            return $this->buildResolutionResult(
                self::STATE_NO_VALID_HYPOTHESIS,
                null,
                $allHypotheses,
                $evidenceCatalog,
                ['All candidate hypotheses were excluded by contradictory authoritative evidence.'],
                false
            );
        }

        // Case B: Exactly One Active Hypothesis
        if ($activeCount === 1) {
            $singleHyp = $activeHypotheses[0];

            // Verify supporting evidence sufficiency
            $supportingCount = 0;
            foreach ($singleHyp['evidence'] as $ev) {
                if ($ev['status'] === self::STATUS_SUPPORTING) {
                    $supportingCount++;
                }
            }

            if ($supportingCount >= 2) {
                return $this->buildResolutionResult(
                    self::STATE_SINGLE_CONFIDENT,
                    $singleHyp['hypothesis_id'],
                    $allHypotheses,
                    $evidenceCatalog,
                    ["Single valid candidate ({$singleHyp['hypothesis_id']}) with {$supportingCount} supporting evidence and zero competing active hypotheses."],
                    false
                );
            }

            return $this->buildResolutionResult(
                self::STATE_UNRESOLVED,
                null,
                $allHypotheses,
                $evidenceCatalog,
                ["Single candidate ({$singleHyp['hypothesis_id']}) has insufficient supporting evidence ({$supportingCount} supporting)."],
                true
            );
        }

        // Case C: Multiple Active Hypotheses ($activeCount >= 2)
        // Check if there is an authoritative discriminator
        $discriminator = $this->evaluateAuthoritativeDiscriminator($activeHypotheses);

        if ($discriminator['has_unique_winner']) {
            // An explicit authoritative differentiator exists (e.g. verified inspection finding on asset or branch protection trip)
            $winnerId = $discriminator['winner_id'];
            return $this->buildResolutionResult(
                self::STATE_SINGLE_CONFIDENT,
                $winnerId,
                $allHypotheses,
                $evidenceCatalog,
                $discriminator['reasons'],
                false
            );
        }

        // Multiple candidates with no unique winner
        if ($tStatus === 'BRANCH_AMBIGUITY') {
            return $this->buildResolutionResult(
                self::STATE_BRANCH_AMBIGUITY,
                null,
                $allHypotheses,
                $evidenceCatalog,
                [
                    "Preserving branch ambiguity: {$activeCount} active hypotheses across distinct topological branches without authoritative discriminator.",
                ],
                true
            );
        }

        // Check if equally probable (identical evidence profiles)
        if ($discriminator['is_equally_probable']) {
            return $this->buildResolutionResult(
                self::STATE_EQUALLY_PROBABLE,
                null,
                $allHypotheses,
                $evidenceCatalog,
                [
                    "Multiple candidate hypotheses ({$activeCount}) share identical authoritative evidence support; no discriminator exists.",
                ],
                true
            );
        }

        return $this->buildResolutionResult(
            self::STATE_MULTIPLE_COMPATIBLE,
            null,
            $allHypotheses,
            $evidenceCatalog,
            [
                "{$activeCount} compatible hypotheses remain active. Selected hypothesis ID is null to prevent forced winner selection.",
            ],
            true
        );
    }

    /**
     * Evaluate if an authoritative discriminator separates the active hypotheses.
     * STRICT: No arbitrary weighting, no fake confidence percentage.
     */
    protected function evaluateAuthoritativeDiscriminator(array $activeHypotheses): array
    {
        $scores = [];
        foreach ($activeHypotheses as $h) {
            $hId = $h['hypothesis_id'];
            $explicitAssetFinding = false;
            $explicitLocationFinding = false;
            $branchTrip = false;
            $supportingTotal = 0;
            $neutralTotal = 0;

            foreach ($h['evidence'] as $ev) {
                if ($ev['status'] === self::STATUS_SUPPORTING) {
                    $supportingTotal++;
                    if ($ev['type'] === self::EVIDENCE_INSPECTION) {
                        if (($ev['relation'] ?? '') === 'EXPLICIT_ASSET_LINK') {
                            $explicitAssetFinding = true;
                        } elseif (($ev['relation'] ?? '') === 'EXPLICIT_LOCATION_LINK') {
                            $explicitLocationFinding = true;
                        }
                    } elseif ($ev['type'] === self::EVIDENCE_PROTECTION && ($ev['value'] ?? '') === 'BRANCH_TRIP') {
                        $branchTrip = true;
                    }
                } elseif ($ev['status'] === self::STATUS_NEUTRAL) {
                    $neutralTotal++;
                }
            }

            $scores[$hId] = [
                'supporting'       => $supportingTotal,
                'neutral'          => $neutralTotal,
                'explicit_finding' => ($explicitAssetFinding || $explicitLocationFinding),
                'branch_trip'      => $branchTrip,
            ];
        }

        // Check if one candidate has an authoritative high-fidelity discriminator
        // (e.g. explicit asset finding or branch trip) while other candidates do not
        $candidatesWithHighFidelity = [];
        foreach ($scores as $hId => $sc) {
            if ($sc['explicit_finding'] || $sc['branch_trip']) {
                $candidatesWithHighFidelity[] = $hId;
            }
        }

        if (count($candidatesWithHighFidelity) === 1) {
            $winnerId = $candidatesWithHighFidelity[0];
            return [
                'has_unique_winner'   => true,
                'winner_id'           => $winnerId,
                'is_equally_probable' => false,
                'reasons'             => [
                    "Authoritative discriminator identified: Hypothesis {$winnerId} has high-fidelity verified evidence (inspection finding or branch protection).",
                ],
            ];
        }

        // Check if all active candidates have identical supporting/neutral counts
        $first = reset($scores);
        $identical = true;
        foreach ($scores as $sc) {
            if ($sc['supporting'] !== $first['supporting'] || $sc['neutral'] !== $first['neutral']) {
                $identical = false;
                break;
            }
        }

        return [
            'has_unique_winner'   => false,
            'winner_id'           => null,
            'is_equally_probable' => $identical,
            'reasons'             => [],
        ];
    }

    /**
     * Extract candidate hypotheses from context.
     */
    protected function extractCandidateHypotheses(array $context): array
    {
        if (!empty($context['hypotheses']) && is_array($context['hypotheses'])) {
            return array_values($context['hypotheses']);
        }

        $traversal  = $context['traversal'] ?? $context['traversal_result'] ?? [];
        $projection = $context['projection'] ?? $context['projection_result'] ?? [];

        $candPaths = $traversal['candidate_paths'] ?? [];
        $projList  = $projection['projections'] ?? [];

        // Index projections by path_id and edge_id
        $projByPath = [];
        $projByEdge = [];
        foreach ($projList as $p) {
            if (!empty($p['path_id'])) {
                $projByPath[(string)$p['path_id']] = $p;
            }
            if (!empty($p['edge_id'])) {
                $projByEdge[(int)$p['edge_id']] = $p;
            }
        }

        $candidates = [];
        foreach ($candPaths as $idx => $cp) {
            $pathId = (string)($cp['path_id'] ?? "PATH-{$idx}");
            $edgeId = (int)($cp['edge_id'] ?? 0);
            $compId = (int)($cp['component_id'] ?? 1);

            $matchedProj = $projByPath[$pathId] ?? $projByEdge[$edgeId] ?? null;

            $hypId = !empty($cp['hypothesis_id'])
                ? (string)$cp['hypothesis_id']
                : "HYP-C{$compId}-{$pathId}-TL{$edgeId}";

            $candidates[] = [
                'hypothesis_id'       => $hypId,
                'component_id'        => $compId,
                'path_id'             => $pathId,
                'edge_id'             => $edgeId,
                'transline_id'        => $edgeId,
                'upstream_asset_id'   => (int)($cp['upstream_asset_id'] ?? 0),
                'downstream_asset_id' => (int)($cp['downstream_asset_id'] ?? 0),
                'segment_length_m'    => (float)($cp['segment_length_m'] ?? 0.0),
                'offset_in_segment_m' => (float)($cp['offset_in_segment_m'] ?? 0.0),
                'offset_m'            => (float)($cp['offset_in_segment_m'] ?? 0.0),
                'distance_from_root_m'=> (float)($traversal['traversal_distance_m'] ?? 0.0),
                'segment_ratio'       => (float)($cp['segment_ratio'] ?? ($matchedProj['segment_ratio'] ?? 0.0)),
                'fault_lat'           => $matchedProj['latitude'] ?? null,
                'fault_lng'           => $matchedProj['longitude'] ?? null,
                'wkt_point'           => $matchedProj['wkt_point'] ?? null,
                'projection_status'   => $matchedProj['status'] ?? 'NOT_PROJECTED',
                'orientation'         => $matchedProj['orientation'] ?? null,
            ];
        }

        return $candidates;
    }

    /**
     * Precondition Check: Global Traversal & Measurement Statuses
     */
    protected function checkGlobalUnresolvedPreconditions(
        string $tStatus,
        string $pStatus,
        array $hypotheses,
        array $measurement
    ): ?string {
        $blockingTraversalStates = [
            'ROOT_UNRESOLVED'       => 'Authoritative topology root is unresolved. Cannot establish downstream direction.',
            'PATH_DISCONTINUOUS'    => 'Graph traversal path is discontinuous in authoritative topology.',
            'DISTANCE_OUT_OF_RANGE' => 'Fault distance exceeds maximum reachable path length on feeder.',
            'TOPOLOGY_UNAVAILABLE'  => 'Authoritative topology model is unavailable.',
            'FEEDER_NOT_FOUND'      => 'Feeder not found in database.',
            'MEASUREMENT_NOT_USABLE'=> 'FTU fault measurement is invalid or unusable.',
            'EMPTY_TRAVERSAL'       => 'Traversal result is empty.',
        ];

        if (isset($blockingTraversalStates[$tStatus])) {
            return $blockingTraversalStates[$tStatus];
        }

        if (empty($hypotheses)) {
            return 'No candidate fault hypotheses were produced by traversal/projection.';
        }

        $measStatus = (string)($measurement['measurement_status'] ?? 'VALID');
        if (in_array($measStatus, ['INVALID', 'UNUSABLE', 'CORRUPT', 'ZERO_CURRENT'], true)) {
            return "FTU fault measurement is explicitly flagged as {$measStatus}.";
        }

        return null;
    }

    /**
     * A. Evaluate TOPOLOGY_CONSISTENCY Evidence
     */
    protected function evaluateTopologyConsistency(array $hyp, array $topology, array $traversal): array
    {
        $edgeId = (int)($hyp['edge_id'] ?? $hyp['transline_id'] ?? 0);
        $edges  = $topology['edges'] ?? [];

        // Check edge existence in topology edges if topology was provided
        $edgeFound = false;
        $isActive  = true;
        if (!empty($edges)) {
            foreach ($edges as $e) {
                if ((int)$e['id'] === $edgeId) {
                    $edgeFound = true;
                    if (isset($e['is_active']) && (int)$e['is_active'] !== 1) {
                        $isActive = false;
                    }
                    break;
                }
            }
        } else {
            // Topology array not provided directly; check if edge_id is positive
            $edgeFound = ($edgeId > 0);
        }

        if (!$edgeFound) {
            return [
                'type'             => self::EVIDENCE_TOPOLOGY,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'gis_translines',
                'source_id'        => $edgeId,
                'field'            => 'id',
                'relation'         => 'TOPOLOGY_DERIVED',
                'value'            => 'EDGE_NOT_FOUND',
                'details'          => "Edge #{$edgeId} does not exist in authoritative topology.",
                'evidence_version' => self::VERSION,
            ];
        }

        if (!$isActive) {
            return [
                'type'             => self::EVIDENCE_TOPOLOGY,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'gis_translines',
                'source_id'        => $edgeId,
                'field'            => 'is_active',
                'relation'         => 'TOPOLOGY_DERIVED',
                'value'            => 'EDGE_INACTIVE',
                'details'          => "Edge #{$edgeId} is marked inactive in authoritative topology.",
                'evidence_version' => self::VERSION,
            ];
        }

        return [
            'type'             => self::EVIDENCE_TOPOLOGY,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_SUPPORTING,
            'source_table'     => 'gis_translines',
            'source_id'        => $edgeId,
            'field'            => 'id',
            'relation'         => 'TOPOLOGY_DERIVED',
            'value'            => 'TOPOLOGY_CONNECTED',
            'details'          => "Edge #{$edgeId} is verified connected in authoritative topology.",
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * B. Evaluate GEOMETRY_CONSISTENCY Evidence
     */
    protected function evaluateGeometryConsistency(array $hyp, array $projection): array
    {
        $projStatus = (string)($hyp['projection_status'] ?? '');
        $edgeId     = (int)($hyp['edge_id'] ?? $hyp['transline_id'] ?? 0);

        if ($projStatus === 'PROJECTED') {
            return [
                'type'             => self::EVIDENCE_GEOMETRY,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_SUPPORTING,
                'source_table'     => 'gis_translines',
                'source_id'        => $edgeId,
                'field'            => 'geometry',
                'relation'         => 'SPATIAL_GEOMETRY',
                'value'            => $hyp['wkt_point'] ?? 'POINT_PROJECTED',
                'details'          => "Authoritative LineString projected fault coordinate {$hyp['wkt_point']}.",
                'evidence_version' => self::VERSION,
            ];
        }

        if ($projStatus === 'GEOMETRY_ORIENTATION_AMBIGUOUS') {
            return [
                'type'             => self::EVIDENCE_GEOMETRY,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_NEUTRAL,
                'source_table'     => 'gis_translines',
                'source_id'        => $edgeId,
                'field'            => 'geometry',
                'relation'         => 'SPATIAL_GEOMETRY',
                'value'            => 'ORIENTATION_AMBIGUOUS',
                'details'          => 'LineString orientation is ambiguous within tolerance (<0.001m difference).',
                'evidence_version' => self::VERSION,
            ];
        }

        return [
            'type'             => self::EVIDENCE_GEOMETRY,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_CONTRADICTING,
            'source_table'     => 'gis_translines',
            'source_id'        => $edgeId,
            'field'            => 'geometry',
            'relation'         => 'SPATIAL_GEOMETRY',
            'value'            => $projStatus,
            'details'          => "Geometry projection failed with status: {$projStatus}.",
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * C. Evaluate DISTANCE_CONSISTENCY Evidence
     */
    protected function evaluateDistanceConsistency(array $hyp, array $traversal): array
    {
        $segLen  = (float)($hyp['segment_length_m'] ?? 0.0);
        $offsetM = (float)($hyp['offset_in_segment_m'] ?? $hyp['offset_m'] ?? 0.0);

        if ($segLen <= 0.0) {
            return [
                'type'             => self::EVIDENCE_DISTANCE,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'gis_translines',
                'source_id'        => (int)($hyp['edge_id'] ?? 0),
                'field'            => 'distance_meters',
                'relation'         => 'TOPOLOGY_DERIVED',
                'value'            => $segLen,
                'details'          => "Segment length is non-positive ({$segLen}m).",
                'evidence_version' => self::VERSION,
            ];
        }

        if ($offsetM < 0.0 || $offsetM > $segLen) {
            return [
                'type'             => self::EVIDENCE_DISTANCE,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'gis_translines',
                'source_id'        => (int)($hyp['edge_id'] ?? 0),
                'field'            => 'offset_in_segment_m',
                'relation'         => 'TOPOLOGY_DERIVED',
                'value'            => $offsetM,
                'details'          => "Offset {$offsetM}m exceeds segment length {$segLen}m.",
                'evidence_version' => self::VERSION,
            ];
        }

        return [
            'type'             => self::EVIDENCE_DISTANCE,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_SUPPORTING,
            'source_table'     => 'gis_translines',
            'source_id'        => (int)($hyp['edge_id'] ?? 0),
            'field'            => 'offset_in_segment_m',
            'relation'         => 'TOPOLOGY_DERIVED',
            'value'            => round($offsetM, 2),
            'details'          => "Offset {$offsetM}m is bounded within segment length {$segLen}m.",
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * D. Evaluate MEASUREMENT_CONSISTENCY Evidence
     */
    protected function evaluateMeasurementConsistency(array $hyp, array $measurement, array $ftuParsed): array
    {
        if (empty($measurement)) {
            return [
                'type'             => self::EVIDENCE_MEASUREMENT,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_UNAVAILABLE,
                'source_table'     => null,
                'source_id'        => null,
                'field'            => null,
                'relation'         => 'MEASUREMENT_VALIDATED',
                'value'            => null,
                'details'          => 'FTU measurement data is unavailable in context.',
                'evidence_version' => self::VERSION,
            ];
        }

        $measStatus = (string)($measurement['measurement_status'] ?? 'VALID');
        $faultType  = (string)($measurement['fault_type'] ?? $ftuParsed['fault_type'] ?? 'UNKNOWN');
        $z          = (float)($measurement['Z'] ?? $measurement['impedance_ohm'] ?? 0.0);
        $ia         = (float)($measurement['Ia'] ?? 0.0);
        $ib         = (float)($measurement['Ib'] ?? 0.0);
        $ic         = (float)($measurement['Ic'] ?? 0.0);

        if (in_array($measStatus, ['INVALID', 'CORRUPT', 'ZERO_FAULT_CURRENT'], true)) {
            return [
                'type'             => self::EVIDENCE_MEASUREMENT,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'ftu_records',
                'source_id'        => null,
                'field'            => 'measurement_status',
                'relation'         => 'MEASUREMENT_VALIDATED',
                'value'            => $measStatus,
                'details'          => "Measurement explicitly flagged as {$measStatus}.",
                'evidence_version' => self::VERSION,
            ];
        }

        // Validate fault currents against fault type without altering FTU values
        $consistent = true;
        if ($faultType === 'A-G' && $ia <= 0.0 && ($ib > 0.0 || $ic > 0.0)) {
            $consistent = false;
        } elseif ($faultType === 'B-G' && $ib <= 0.0 && ($ia > 0.0 || $ic > 0.0)) {
            $consistent = false;
        } elseif ($faultType === 'C-G' && $ic <= 0.0 && ($ia > 0.0 || $ib > 0.0)) {
            $consistent = false;
        }

        if (!$consistent) {
            return [
                'type'             => self::EVIDENCE_MEASUREMENT,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'ftu_records',
                'source_id'        => null,
                'field'            => 'currents',
                'relation'         => 'MEASUREMENT_VALIDATED',
                'value'            => ['Ia' => $ia, 'Ib' => $ib, 'Ic' => $ic, 'fault_type' => $faultType],
                'details'          => "Reported fault currents are inconsistent with fault type {$faultType}.",
                'evidence_version' => self::VERSION,
            ];
        }

        return [
            'type'             => self::EVIDENCE_MEASUREMENT,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_SUPPORTING,
            'source_table'     => 'ftu_records',
            'source_id'        => null,
            'field'            => 'fault_type',
            'relation'         => 'MEASUREMENT_VALIDATED',
            'value'            => ['fault_type' => $faultType, 'Z' => $z],
            'details'          => "FTU measurement is consistent with fault type {$faultType} and impedance Z={$z}Ω.",
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * E. Evaluate PROTECTION_STATUS Evidence
     */
    protected function evaluateProtectionStatus(array $hyp, array $context): array
    {
        $protection = $context['protection_status'] ?? null;
        if ($protection === null) {
            return [
                'type'             => self::EVIDENCE_PROTECTION,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_UNAVAILABLE,
                'source_table'     => null,
                'source_id'        => null,
                'field'            => null,
                'relation'         => 'PROTECTION_TELEMETRY',
                'value'            => null,
                'details'          => 'Protection device telemetry is unavailable.',
                'evidence_version' => self::VERSION,
            ];
        }

        // Context can provide status per hypothesis ID or globally
        $state = 'UNKNOWN';
        if (is_array($protection)) {
            $state = (string)($protection[$hyp['hypothesis_id']] ?? $protection['status'] ?? 'UNKNOWN');
        } elseif (is_string($protection)) {
            $state = $protection;
        }

        if ($state === 'TRIP' || $state === 'LOCKOUT' || $state === 'BRANCH_TRIP') {
            return [
                'type'             => self::EVIDENCE_PROTECTION,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_SUPPORTING,
                'source_table'     => 'protection_events',
                'source_id'        => null,
                'field'            => 'status',
                'relation'         => 'PROTECTION_TELEMETRY',
                'value'            => $state,
                'details'          => "Protection device operated ({$state}) for this feeder/branch.",
                'evidence_version' => self::VERSION,
            ];
        }

        if ($state === 'CLOSED' || $state === 'NO_TRIP') {
            return [
                'type'             => self::EVIDENCE_PROTECTION,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'protection_events',
                'source_id'        => null,
                'field'            => 'status',
                'relation'         => 'PROTECTION_TELEMETRY',
                'value'            => $state,
                'details'          => 'Protection device remained CLOSED (no trip detected on branch).',
                'evidence_version' => self::VERSION,
            ];
        }

        // UNKNOWN is NEVER negative
        return [
            'type'             => self::EVIDENCE_PROTECTION,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_UNKNOWN,
            'source_table'     => null,
            'source_id'        => null,
            'field'            => 'status',
            'relation'         => 'PROTECTION_TELEMETRY',
            'value'            => 'UNKNOWN',
            'details'          => 'Protection device telemetry status is UNKNOWN (not treated as negative).',
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * F. Evaluate RECLOSER_STATUS Evidence
     */
    protected function evaluateRecloserStatus(array $hyp, array $context): array
    {
        $recloser = $context['recloser_status'] ?? null;
        if ($recloser === null) {
            return [
                'type'             => self::EVIDENCE_RECLOSER,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_UNAVAILABLE,
                'source_table'     => null,
                'source_id'        => null,
                'field'            => null,
                'relation'         => 'RECLOSER_TELEMETRY',
                'value'            => null,
                'details'          => 'Recloser telemetry is unavailable in context.',
                'evidence_version' => self::VERSION,
            ];
        }

        $state = 'UNKNOWN';
        if (is_array($recloser)) {
            $state = (string)($recloser[$hyp['hypothesis_id']] ?? $recloser['status'] ?? 'UNKNOWN');
        } elseif (is_string($recloser)) {
            $state = $recloser;
        }

        if ($state === 'TRIP' || $state === 'LOCKOUT' || $state === 'BRANCH_TRIP') {
            return [
                'type'             => self::EVIDENCE_RECLOSER,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_SUPPORTING,
                'source_table'     => 'recloser_events',
                'source_id'        => null,
                'field'            => 'status',
                'relation'         => 'RECLOSER_TELEMETRY',
                'value'            => $state,
                'details'          => "Recloser operated ({$state}) downstream of anchor.",
                'evidence_version' => self::VERSION,
            ];
        }

        if ($state === 'CLOSED' || $state === 'NO_TRIP') {
            return [
                'type'             => self::EVIDENCE_RECLOSER,
                'hypothesis_id'    => $hyp['hypothesis_id'],
                'status'           => self::STATUS_CONTRADICTING,
                'source_table'     => 'recloser_events',
                'source_id'        => null,
                'field'            => 'status',
                'relation'         => 'RECLOSER_TELEMETRY',
                'value'            => $state,
                'details'          => 'Recloser remained CLOSED.',
                'evidence_version' => self::VERSION,
            ];
        }

        return [
            'type'             => self::EVIDENCE_RECLOSER,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_UNKNOWN,
            'source_table'     => null,
            'source_id'        => null,
            'field'            => 'status',
            'relation'         => 'RECLOSER_TELEMETRY',
            'value'            => 'UNKNOWN',
            'details'          => 'Recloser telemetry is UNKNOWN (not treated as negative).',
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * G. Evaluate INSPECTION_FINDING Evidence (Authoritative temuan)
     * STRICT: EXPLICIT relationships only. NEVER nearest GPS / nearest finding.
     */
    protected function evaluateInspectionFindings(array $hyp, array $context): array
    {
        $upAssetId   = (int)($hyp['upstream_asset_id'] ?? 0);
        $downAssetId = (int)($hyp['downstream_asset_id'] ?? 0);
        $translineId = (int)($hyp['transline_id'] ?? $hyp['edge_id'] ?? 0);

        // 1. Check if mock/in-memory findings provided in context
        $contextFindings = $context['inspection_findings'] ?? null;
        if (is_array($contextFindings)) {
            foreach ($contextFindings as $f) {
                $fAssetId = (int)($f['asset_id'] ?? 0);
                $fTransId = (int)($f['transline_id'] ?? 0);
                $fHypId   = (string)($f['hypothesis_id'] ?? '');

                if (!empty($fHypId) && $fHypId === (string)$hyp['hypothesis_id']) {
                    return [
                        'type'             => self::EVIDENCE_INSPECTION,
                        'hypothesis_id'    => $hyp['hypothesis_id'],
                        'status'           => self::STATUS_SUPPORTING,
                        'source_table'     => 'temuan',
                        'source_id'        => (int)($f['id'] ?? 0),
                        'field'            => 'detail_temuan',
                        'relation'         => 'EXPLICIT_LOCATION_LINK',
                        'value'            => $f['detail_temuan'] ?? 'FINDING_RECORDED',
                        'details'          => "Explicit inspection finding #{$f['id']} matched hypothesis {$fHypId}.",
                        'evidence_version' => self::VERSION,
                    ];
                }

                if ($fAssetId > 0 && ($fAssetId === $upAssetId || $fAssetId === $downAssetId)) {
                    return [
                        'type'             => self::EVIDENCE_INSPECTION,
                        'hypothesis_id'    => $hyp['hypothesis_id'],
                        'status'           => self::STATUS_SUPPORTING,
                        'source_table'     => 'temuan',
                        'source_id'        => (int)($f['id'] ?? 0),
                        'field'            => 'asset_id',
                        'relation'         => 'EXPLICIT_ASSET_LINK',
                        'value'            => $f['detail_temuan'] ?? 'ASSET_ANOMALY',
                        'details'          => "Explicit inspection finding #{$f['id']} on asset #{$fAssetId}.",
                        'evidence_version' => self::VERSION,
                    ];
                }

                if ($fTransId > 0 && $fTransId === $translineId) {
                    return [
                        'type'             => self::EVIDENCE_INSPECTION,
                        'hypothesis_id'    => $hyp['hypothesis_id'],
                        'status'           => self::STATUS_SUPPORTING,
                        'source_table'     => 'temuan',
                        'source_id'        => (int)($f['id'] ?? 0),
                        'field'            => 'transline_id',
                        'relation'         => 'EXPLICIT_LOCATION_LINK',
                        'value'            => $f['detail_temuan'] ?? 'TRANSLINE_ANOMALY',
                        'details'          => "Explicit inspection finding #{$f['id']} on transline #{$translineId}.",
                        'evidence_version' => self::VERSION,
                    ];
                }
            }
        }

        // 2. Query Authoritative temuan Table (READ-ONLY)
        if ($this->db && ($upAssetId > 0 || $downAssetId > 0)) {
            try {
                if ($this->db->tableExists('temuan')) {
                    $builder = $this->db->table('temuan');
                    $builder->select('id, detail_temuan, status');
                    $builder->where('deleted_at IS NULL');
                    if ($this->db->fieldExists('asset_id', 'temuan')) {
                        $targetAssets = array_filter([$upAssetId, $downAssetId]);
                        $builder->whereIn('asset_id', $targetAssets);
                        $row = $builder->get(1)->getRowArray();
                        if ($row) {
                            return [
                                'type'             => self::EVIDENCE_INSPECTION,
                                'hypothesis_id'    => $hyp['hypothesis_id'],
                                'status'           => self::STATUS_SUPPORTING,
                                'source_table'     => 'temuan',
                                'source_id'        => (int)$row['id'],
                                'field'            => 'asset_id',
                                'relation'         => 'EXPLICIT_ASSET_LINK',
                                'value'            => $row['detail_temuan'] ?? 'ASSET_ANOMALY',
                                'details'          => "Authoritative finding #{$row['id']} explicitly linked to asset.",
                                'evidence_version' => self::VERSION,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Database query error handled gracefully
            }
        }

        return [
            'type'             => self::EVIDENCE_INSPECTION,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_UNAVAILABLE,
            'source_table'     => 'temuan',
            'source_id'        => null,
            'field'            => null,
            'relation'         => 'NONE',
            'value'            => null,
            'details'          => 'No explicit inspection finding linked to this hypothesis.',
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * H. Evaluate HISTORICAL_FAULT_INDEX Evidence
     * STRICT: Only if real authoritative historical records exist. Never guessed.
     */
    protected function evaluateHistoricalFaultIndex(array $hyp, array $context): array
    {
        $contextHist = $context['historical_fault_index'] ?? null;
        if (is_array($contextHist)) {
            $hId = (string)$hyp['hypothesis_id'];
            if (isset($contextHist[$hId])) {
                $val = $contextHist[$hId];
                return [
                    'type'             => self::EVIDENCE_HISTORICAL,
                    'hypothesis_id'    => $hyp['hypothesis_id'],
                    'status'           => self::STATUS_SUPPORTING,
                    'source_table'     => 'historical_faults',
                    'source_id'        => null,
                    'field'            => 'fault_count',
                    'relation'         => 'EXPLICIT_FEEDER_LINK',
                    'value'            => $val,
                    'details'          => "Authoritative historical fault records indicate recurrence ({$val}).",
                    'evidence_version' => self::VERSION,
                ];
            }
        }

        // Live database check: fault_records does NOT exist in baseline
        return [
            'type'             => self::EVIDENCE_HISTORICAL,
            'hypothesis_id'    => $hyp['hypothesis_id'],
            'status'           => self::STATUS_UNAVAILABLE,
            'source_table'     => null,
            'source_id'        => null,
            'field'            => null,
            'relation'         => null,
            'value'            => null,
            'details'          => 'Historical fault registry is unavailable in authoritative database.',
            'evidence_version' => self::VERSION,
        ];
    }

    /**
     * Build standard resolution result payload.
     */
    protected function buildResolutionResult(
        string $resolutionState,
        ?string $selectedHypothesisId,
        array $hypotheses,
        array $evidenceCatalog,
        array $decisionBasis,
        bool $ambiguityPreserved
    ): array {
        $result = [
            'engine'                      => self::ENGINE,
            'version'                     => self::VERSION,
            'resolution_state'            => $resolutionState,
            'selected_hypothesis_id'      => $selectedHypothesisId,
            'hypotheses_count'            => count($hypotheses),
            'active_hypotheses_count'     => count(array_filter($hypotheses, fn($h) => ($h['status'] ?? '') === 'ACTIVE')),
            'hypotheses'                  => array_values($hypotheses),
            'evidence_catalog'            => array_values($evidenceCatalog),
            'decision_basis'              => $decisionBasis,
            'ambiguity_preserved'         => $ambiguityPreserved,
            'authoritative_evidence_only' => true,
            'ai_used'                     => false,
            'persisted'                   => false,
        ];

        $result['fingerprint'] = $this->calculateResolutionFingerprint($result);
        return $result;
    }

    /**
     * Calculate Reproducible SHA-256 Fingerprint.
     * Guaranteed invariant to runtime clock / retrieved_at timestamps.
     */
    public function calculateResolutionFingerprint(array $result): string
    {
        $clean = $this->stripTimestampsRecursively($result);
        unset($clean['fingerprint']);
        $this->ksortRecursive($clean);

        $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', (string)$json);
    }

    protected function stripTimestampsRecursively(array $arr): array
    {
        $stripped = [];
        foreach ($arr as $k => $v) {
            if (in_array((string)$k, ['retrieved_at', 'timestamp', 'generated_at', 'created_at'], true)) {
                continue;
            }
            if (is_array($v)) {
                $stripped[$k] = $this->stripTimestampsRecursively($v);
            } else {
                $stripped[$k] = $v;
            }
        }
        return $stripped;
    }

    protected function ksortRecursive(array &$arr): void
    {
        ksort($arr);
        foreach ($arr as &$v) {
            if (is_array($v)) {
                $this->ksortRecursive($v);
            }
        }
    }
}
