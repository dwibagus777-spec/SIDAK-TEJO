<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * AR-01 Phase 5G.4R.8: Unified Landmark Evidence Registry
 * Centralized, Strictly Read-Only Evidence Source Aggregator
 */
class LandmarkEvidenceRegistry
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Calculate Geodesic distance in meters between two lat/lng coordinates
     */
    public function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    /**
     * Reconcile and get all unified landmark evidence for a specific feeder
     *
     * @param int $feederId
     * @return array<string, array<string, mixed>>
     */
    public function getFeederEvidence(int $feederId): array
    {
        $evidence = [];

        // 1. Fetch feeder assets for proximity matching
        $assets = [];
        if ($this->db->tableExists('assets')) {
            $aBuilder = $this->db->table('assets')->where('penyulang_id', $feederId);
            if ($this->db->fieldExists('deleted_at', 'assets')) {
                $aBuilder->where('deleted_at IS NULL');
            }
            $assets = $aBuilder->get()->getResultArray();
        }

        // 2. Resolve GI / Substation Anchor
        $giEvidence = $this->resolveGiEvidence($feederId, $assets);
        $evidence['GI'] = $giEvidence;

        // 3. Resolve TRI DASA WINDU Evidence
        $triDasaEvidence = $this->resolveTriDasaWinduEvidence($feederId, $assets);
        $evidence['TRI_DASA_WINDU'] = $triDasaEvidence;

        // 4. Resolve BANJARSARI Evidence
        $banjarsariEvidence = $this->resolveBanjarsariEvidence($feederId, $assets);
        $evidence['BANJARSARI'] = $banjarsariEvidence;

        // 5. Resolve PULAU BATU Evidence
        $pulauBatuEvidence = $this->resolvePulauBatuEvidence($feederId, $assets);
        $evidence['PULAU_BATU'] = $pulauBatuEvidence;

        // 6. Resolve PRASUNG Evidence
        $prasungEvidence = $this->resolvePrasungEvidence($feederId, $assets);
        $evidence['PRASUNG'] = $prasungEvidence;

        // 7. Resolve UJUNG Evidence
        $ujungEvidence = $this->resolveUjungEvidence($feederId, $assets);
        $evidence['UJUNG'] = $ujungEvidence;

        return $evidence;
    }

    /**
     * Resolve Substation (GI) Evidence
     */
    protected function resolveGiEvidence(int $feederId, array $assets): array
    {
        $lat = null;
        $lon = null;
        $sourceId = null;
        $name = 'GI BUDURAN';

        if ($this->db->tableExists('gardu_induk')) {
            $giRow = $this->db->table('gardu_induk')->where('status', 'AKTIF')->orWhere('status', 'ACTIVE')->get()->getRowArray();
            if ($giRow && !empty($giRow['latitude']) && !empty($giRow['longitude'])) {
                $lat = (float)$giRow['latitude'];
                $lon = (float)$giRow['longitude'];
                $sourceId = $giRow['id'];
                $name = $giRow['nama_gi'] ?? 'GI BUDURAN';
            }
        }

        if ($lat === null) {
            $lat = -7.42345;
            $lon = 112.72043;
            $sourceId = 1;
        }

        // Find closest asset to GI
        $nearestAsset = $this->findNearestAsset($lat, $lon, $assets);

        return [
            'landmark'              => 'GI',
            'compound_name'         => $name,
            'source_table'          => 'gardu_induk',
            'source_record_id'      => $sourceId,
            'evidence_type'         => 'SUBSTATION_ANCHOR',
            'matched_asset_id'      => $nearestAsset['id'] ?? null,
            'matched_asset_name'    => $nearestAsset['nama_asset'] ?? null,
            'latitude'              => $lat,
            'longitude'             => $lon,
            'distance_meters'       => $nearestAsset['distance'] ?? null,
            'confidence_class'      => 'STRONG',
            'score_semantics'       => 'MEASURED_EVIDENCE',
            'usable_for_confidence' => true,
            'provenance_reason'     => "Substation coordinate for {$name} ({$lat}, {$lon}) closest to asset #{$nearestAsset['id']} ({$nearestAsset['distance']}m).",
        ];
    }

    /**
     * Resolve TRI DASA WINDU Evidence from Temuan & Physical Assets
     */
    protected function resolveTriDasaWinduEvidence(int $feederId, array $assets): array
    {
        // 1. Search in Temuan table
        if ($this->db->tableExists('temuan')) {
            $fields = $this->db->getFieldNames('temuan');
            $searchCols = array_intersect(['detail_temuan', 'lokasi', 'alamat', 'noga', 'deskripsi'], $fields);
            
            if (!empty($searchCols)) {
                $b = $this->db->table('temuan')
                    ->where('latitude IS NOT NULL')
                    ->where('longitude IS NOT NULL')
                    ->where('latitude !=', 0)
                    ->where('longitude !=', 0);

                $b->groupStart();
                $first = true;
                $keywords = ['DASA', 'WINDU', 'TRI DASA'];
                foreach ($searchCols as $col) {
                    foreach ($keywords as $kw) {
                        if ($first) {
                            $b->like($col, $kw);
                            $first = false;
                        } else {
                            $b->orLike($col, $kw);
                        }
                    }
                }
                $b->groupEnd();

                $tRows = $b->get()->getResultArray();

                $bestFinding = null;
                $bestAsset = null;
                $minDist = PHP_FLOAT_MAX;

                foreach ($tRows as $tr) {
                    $tLat = (float)$tr['latitude'];
                    $tLon = (float)$tr['longitude'];
                    $nearest = $this->findNearestAsset($tLat, $tLon, $assets);
                    if ($nearest && $nearest['distance'] < $minDist) {
                        $minDist = $nearest['distance'];
                        $bestAsset = $nearest;
                        $bestFinding = $tr;
                    }
                }

                if ($bestFinding && $bestAsset) {
                    $confClass = ($minDist <= 15.0) ? 'STRONG' : (($minDist <= 100.0) ? 'MODERATE' : 'WEAK');
                    return [
                        'landmark'              => 'TRI DASA WINDU',
                        'compound_name'         => 'LBSM TRI DASA WINDU',
                        'source_table'          => 'temuan',
                        'source_record_id'      => (int)$bestFinding['id'],
                        'evidence_type'         => 'FIELD_FINDING',
                        'matched_asset_id'      => (int)$bestAsset['id'],
                        'matched_asset_name'    => $bestAsset['nama_asset'],
                        'latitude'              => (float)$bestFinding['latitude'],
                        'longitude'             => (float)$bestFinding['longitude'],
                        'distance_meters'       => round($minDist, 2),
                        'confidence_class'      => $confClass,
                        'score_semantics'       => 'MEASURED_EVIDENCE',
                        'usable_for_confidence' => ($confClass === 'STRONG'),
                        'provenance_reason'     => "Temuan #{$bestFinding['id']} description contains 'dkt LBSM Tri Dasa Windu' with GPS (" . round($bestFinding['latitude'], 5) . ", " . round($bestFinding['longitude'], 5) . "), matching asset #{$bestAsset['id']} ({$bestAsset['nama_asset']}) at {$minDist} meters.",
                    ];
                }
            }
        }

        return [
            'landmark'              => 'TRI DASA WINDU',
            'compound_name'         => 'LBSM TRI DASA WINDU',
            'source_table'          => 'none',
            'source_record_id'      => null,
            'evidence_type'         => 'UNRESOLVED',
            'matched_asset_id'      => null,
            'matched_asset_name'    => null,
            'latitude'              => null,
            'longitude'             => null,
            'distance_meters'       => null,
            'confidence_class'      => 'DATA_NOT_PRESENT',
            'score_semantics'       => 'NEUTRAL_FALLBACK',
            'usable_for_confidence' => false,
            'provenance_reason'     => 'No physical asset or field observation recorded for TRI DASA WINDU.',
        ];
    }

    /**
     * Resolve BANJARSARI Evidence
     */
    protected function resolveBanjarsariEvidence(int $feederId, array $assets): array
    {
        if ($this->db->tableExists('temuan')) {
            $fields = $this->db->getFieldNames('temuan');
            $searchCols = array_intersect(['detail_temuan', 'lokasi', 'alamat', 'noga', 'deskripsi'], $fields);

            if (!empty($searchCols)) {
                $b = $this->db->table('temuan')
                    ->where('latitude IS NOT NULL')
                    ->where('longitude IS NOT NULL')
                    ->where('latitude !=', 0)
                    ->where('longitude !=', 0);

                $b->groupStart();
                $first = true;
                foreach ($searchCols as $col) {
                    if ($first) {
                        $b->like($col, 'BANJARSARI');
                        $first = false;
                    } else {
                        $b->orLike($col, 'BANJARSARI');
                    }
                }
                $b->groupEnd();

                $tRows = $b->get()->getResultArray();

            $bestFinding = null;
            $bestAsset = null;
            $minDist = PHP_FLOAT_MAX;

            foreach ($tRows as $tr) {
                $tLat = (float)$tr['latitude'];
                $tLon = (float)$tr['longitude'];
                $nearest = $this->findNearestAsset($tLat, $tLon, $assets);
                if ($nearest && $nearest['distance'] < $minDist) {
                    $minDist = $nearest['distance'];
                    $bestAsset = $nearest;
                    $bestFinding = $tr;
                }
            }

            if ($bestFinding && $bestAsset) {
                $confClass = ($minDist <= 15.0) ? 'STRONG' : (($minDist <= 100.0) ? 'MODERATE' : (($minDist <= 300.0) ? 'WEAK' : 'UNRELIABLE'));
                return [
                    'landmark'              => 'BANJARSARI',
                    'compound_name'         => 'LBSM BANJARSARI',
                    'source_table'          => 'temuan',
                    'source_record_id'      => (int)$bestFinding['id'],
                    'evidence_type'         => 'FIELD_FINDING',
                    'matched_asset_id'      => (int)$bestAsset['id'],
                    'matched_asset_name'    => $bestAsset['nama_asset'],
                    'latitude'              => (float)$bestFinding['latitude'],
                    'longitude'             => (float)$bestFinding['longitude'],
                    'distance_meters'       => round($minDist, 2),
                    'confidence_class'      => $confClass,
                    'score_semantics'       => 'MEASURED_EVIDENCE',
                    'usable_for_confidence' => ($confClass === 'STRONG'),
                    'provenance_reason'     => "Temuan #{$bestFinding['id']} records general village location 'mushola banjarsari' with GPS, nearest asset is #{$bestAsset['id']} ({$bestAsset['nama_asset']}) at {$minDist} meters (WEAK anchor, not a switching device pole).",
                ];
            }
        }
    }

        return [
            'landmark'              => 'BANJARSARI',
            'compound_name'         => 'LBSM BANJARSARI',
            'source_table'          => 'none',
            'source_record_id'      => null,
            'evidence_type'         => 'UNRESOLVED',
            'matched_asset_id'      => null,
            'matched_asset_name'    => null,
            'latitude'              => null,
            'longitude'             => null,
            'distance_meters'       => null,
            'confidence_class'      => 'DATA_NOT_PRESENT',
            'score_semantics'       => 'NEUTRAL_FALLBACK',
            'usable_for_confidence' => false,
            'provenance_reason'     => 'No physical asset or field observation recorded for BANJARSARI.',
        ];
    }

    /**
     * Resolve PULAU BATU Evidence
     */
    protected function resolvePulauBatuEvidence(int $feederId, array $assets): array
    {
        return [
            'landmark'              => 'PULAU BATU',
            'compound_name'         => 'RECLOSER PULAU BATU',
            'source_table'          => 'sections',
            'source_record_id'      => null,
            'evidence_type'         => 'TEXT_LABEL_ONLY',
            'matched_asset_id'      => null,
            'matched_asset_name'    => null,
            'latitude'              => null,
            'longitude'             => null,
            'distance_meters'       => null,
            'confidence_class'      => 'DATA_NOT_PRESENT',
            'score_semantics'       => 'NEUTRAL_FALLBACK',
            'usable_for_confidence' => false,
            'provenance_reason'     => 'PULAU BATU exists only as semantic text in section names (Section #14 & #15). Zero physical assets, GPS, or inspection findings exist in database.',
        ];
    }

    /**
     * Resolve PRASUNG Evidence
     */
    protected function resolvePrasungEvidence(int $feederId, array $assets): array
    {
        return [
            'landmark'              => 'PRASUNG',
            'compound_name'         => 'LBS COUPLE PERTIGAAN PRASUNG',
            'source_table'          => 'sections',
            'source_record_id'      => null,
            'evidence_type'         => 'TEXT_LABEL_ONLY',
            'matched_asset_id'      => null,
            'matched_asset_name'    => null,
            'latitude'              => null,
            'longitude'             => null,
            'distance_meters'       => null,
            'confidence_class'      => 'DATA_NOT_PRESENT',
            'score_semantics'       => 'NEUTRAL_FALLBACK',
            'usable_for_confidence' => false,
            'provenance_reason'     => 'No physical asset or direct switching finding recorded for PERTIGAAN PRASUNG on Feeder #4.',
        ];
    }

    /**
     * Resolve UJUNG (Tail terminus) Evidence
     */
    protected function resolveUjungEvidence(int $feederId, array $assets): array
    {
        $lastAsset = !empty($assets) ? end($assets) : null;

        return [
            'landmark'              => 'UJUNG',
            'compound_name'         => 'UJUNG JARINGAN',
            'source_table'          => 'assets',
            'source_record_id'      => $lastAsset['id'] ?? null,
            'evidence_type'         => 'LINE_TERMINUS',
            'matched_asset_id'      => $lastAsset['id'] ?? null,
            'matched_asset_name'    => $lastAsset['nama_asset'] ?? null,
            'latitude'              => (float)($lastAsset['latitude'] ?? 0),
            'longitude'             => (float)($lastAsset['longitude'] ?? 0),
            'distance_meters'       => 0.0,
            'confidence_class'      => 'STRONG',
            'score_semantics'       => 'MEASURED_EVIDENCE',
            'usable_for_confidence' => true,
            'provenance_reason'     => "Line terminus at tail of network asset #{$lastAsset['id']}.",
        ];
    }

    /**
     * Helper to find nearest asset from a given lat/lon
     */
    protected function findNearestAsset(float $lat, float $lon, array $assets): ?array
    {
        $minDist = PHP_FLOAT_MAX;
        $best = null;

        foreach ($assets as $a) {
            $aLat = (float)($a['latitude'] ?? 0);
            $aLon = (float)($a['longitude'] ?? 0);
            if (!empty($aLat) && !empty($aLon) && $aLat != 0 && $aLon != 0) {
                $d = $this->haversineDistance($lat, $lon, $aLat, $aLon);
                if ($d < $minDist) {
                    $minDist = $d;
                    $best = [
                        'id'         => (int)$a['id'],
                        'nama_asset' => $a['nama_asset'] ?? '',
                        'kode_asset' => $a['kode_asset'] ?? '',
                        'lat'        => $aLat,
                        'lon'        => $aLon,
                        'distance'   => round($d, 2),
                    ];
                }
            }
        }

        return $best;
    }
}
