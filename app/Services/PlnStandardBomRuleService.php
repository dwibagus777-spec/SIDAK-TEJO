<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * PlnStandardBomRuleService
 *
 * Implements PLN Distribution Standard Bill of Materials (BOM) Rule Engine,
 * Finding-to-Asset Topology Linking, and NOGA Validation.
 *
 * Adheres strictly to PLN JTM Construction Taxonomy (SPLN D3 / Buku Standar Konstruksi).
 */
class PlnStandardBomRuleService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Sanitize NOGA (Nomor Gardu).
     *
     * Rule: NOGA is STRICTLY for distribution substations (e.g., "PA 1003", "GRD-01", "GTT-02").
     * Plain inspection item sequence numbers (e.g., "526", "320") or pole codes (e.g. "AST-...")
     * must NEVER be stored as NOGA.
     */
    public function sanitizeNoga(?string $noga, string $detailText = ''): ?string
    {
        $trimmedNoga = trim((string)$noga);

        // Check if noga is purely numeric or pole-like
        $isInvalidNoga = false;
        if ($trimmedNoga === '') {
            $isInvalidNoga = true;
        } elseif (preg_match('/^[0-9]+$/', $trimmedNoga)) {
            // Pure numeric: inspection row number, NOT a substation
            $isInvalidNoga = true;
        } elseif (preg_match('/^(tiang|pole|ast-|#)/i', $trimmedNoga)) {
            // Pole identity mistakenly put in noga
            $isInvalidNoga = true;
        }

        if (!$isInvalidNoga) {
            // It might be a valid gardu code (e.g. PA 1003, GRD-SDA-01)
            return $trimmedNoga;
        }

        // If noga was invalid, check if the text contains a real substation code
        if (!empty($detailText)) {
            if (preg_match('/\b(?:gtt|gardu|grd|trafo)\s*(?:no\.?|pa|gd)?\s*([A-Za-z0-9\-\/]+)\b/i', $detailText, $m)) {
                $garduCandidate = strtoupper(trim($m[1]));
                if (strlen($garduCandidate) >= 2 && !preg_match('/^[0-9]{1,2}$/', $garduCandidate)) {
                    return 'PA ' . preg_replace('/^PA\s*/i', '', $garduCandidate);
                }
            } elseif (preg_match('/\b(PA\s*\d{2,4})\b/i', $detailText, $mPa)) {
                return strtoupper(trim($mPa[1]));
            }
        }

        return null;
    }

    /**
     * Resolves Standard PLN BOM Material Requirements from Finding Attributes.
     *
     * Maps damage description and construction taxonomy to canonical PLN materials.
     * If no standard rule matches, returns mapping_status = 'PERLU_VERIFIKASI'.
     *
     * @param array<string, mixed> $temuan
     * @return array<string, mixed>
     */
    public function resolveBomFromFinding(array $temuan): array
    {
        $txt = strtolower(
            ($temuan['jenis_temuan'] ?? '') . ' ' .
            ($temuan['detail_temuan'] ?? '') . ' ' .
            ($temuan['material'] ?? '') . ' ' .
            ($temuan['konduktor'] ?? '')
        );

        $conductorType = strtoupper(trim((string)($temuan['conductor_type'] ?? 'AAAC')));
        if (empty($conductorType) || !in_array($conductorType, ['AAAC', 'AAACS', 'MVTIC', 'XLPE'], true)) {
            if (preg_match('/\b(aaacs|a3cs)\b/i', $txt)) {
                $conductorType = 'AAACS';
            } elseif (preg_match('/\b(mvtic|tic)\b/i', $txt)) {
                $conductorType = 'MVTIC';
            } elseif (preg_match('/\b(xlpe|sktm|kabel\s*tanah)\b/i', $txt)) {
                $conductorType = 'XLPE';
            } else {
                $conductorType = 'AAAC';
            }
        }

        $conductorSize = preg_replace('/[^0-9]/', '', (string)($temuan['conductor_size'] ?? '150'));
        if (!in_array($conductorSize, ['70', '110', '150', '240'], true)) {
            if (preg_match('/\b(70|110|150|240)\b/', $txt, $mSz)) {
                $conductorSize = $mSz[1];
            } else {
                $conductorSize = '150';
            }
        }

        // Detect construction code (e.g. TM-11, TM-1, etc.)
        $constructionCode = null;
        if (preg_match('/\b(tm[-\s]?(?:11|10|[1-9])|lbsm|lbs|pmcb|recloser|recl)\b/i', $txt, $mCt)) {
            $rawCt = strtoupper(str_replace(' ', '-', $mCt[1]));
            if (!str_contains($rawCt, '-') && preg_match('/^TM(\d+)$/', $rawCt, $mTm)) {
                $rawCt = 'TM-' . $mTm[1];
            }
            $constructionCode = $rawCt;
        }

        $items = [];
        $primaryKey = null;

        // 1. LIGHTNING ARRESTER (LA)
        if (preg_match('/\b(arrester|la\b|lightning|penangkal\s*petir)\b/i', $txt)) {
            $primaryKey = 'ARRESTER';
            $isSinglePhase = (bool)preg_match('/\b(phase|fasa|phasa)\s*[rst]\b/i', $txt)
                || (bool)preg_match('/\b(1\s*(?:set|buah|unit|phase|fasa))\b/i', $txt);
            $laQty = $isSinglePhase ? 1.0 : 3.0;

            $items[] = [
                'canonical_code' => 'CANON-MAT-002',
                'canonical_name' => 'Polymer Arrester 24 kV - 10 kA',
                'unit'           => 'buah',
                'quantity'       => $laQty,
                'category'       => 'PROTECTION',
                'justification'  => $isSinglePhase
                    ? 'Penggantian 1 unit Lightning Arrester 24 kV phasa terdampak per standar konstruksi PLN'
                    : 'Penggantian 1 set (3 unit) Lightning Arrester 24 kV lengkap per standar konstruksi PLN',
            ];
            $items[] = [
                'canonical_code' => 'CANON-HDW-023',
                'canonical_name' => 'PARALLEL GROOVE CLAMP 2 BAUT',
                'unit'           => 'buah',
                'quantity'       => $laQty,
                'category'       => 'CLAMP',
                'justification'  => 'Klem sambungan jumper konduktor ke arrester',
            ];
            $items[] = [
                'canonical_code' => 'CANON-HDW-015',
                'canonical_name' => 'Ground Wire Clamp Type A',
                'unit'           => 'buah',
                'quantity'       => 1.0,
                'category'       => 'GROUNDING',
                'justification'  => 'Klem pengikat kawat grounding arrester ke tiang',
            ];
        }

        // 2. PIN / POST INSULATOR (ISOLATOR TUMPU)
        if (preg_match('/\b(pin\s*isolator|isolator\s*pin|pin\s*post|isolator\s*tumpu|pin\s*bantu|isolator\s*(?:retak|pecah|flash|fong))\b/i', $txt)) {
            if (!$primaryKey) $primaryKey = 'PIN_ISOLATOR';
            $isThreePhase = (bool)preg_match('/\b(3\s*(?:fasa|phase|set|buah))\b/i', $txt);
            $pinQty = $isThreePhase ? 3.0 : 1.0;

            $items[] = [
                'canonical_code' => 'CANON-MAT-001',
                'canonical_name' => 'Insulator - Pin Post Insulator 20 Kv;12,5 kN - Porcelain (Tumpu)',
                'unit'           => 'buah',
                'quantity'       => $pinQty,
                'category'       => 'ISOLATOR',
                'justification'  => 'Penggantian isolator tumpu retak/rusak',
            ];
            $items[] = [
                'canonical_code' => 'CANON-ACC-012',
                'canonical_name' => 'Preformed Top Tie 150mm (Semi Cond/non metalic/Composite)',
                'unit'           => 'buah',
                'quantity'       => $pinQty,
                'category'       => 'PENGIKAT',
                'justification'  => 'Ikatan isolator tumpu ke konduktor',
            ];
        }

        // 3. STRAIN / HANG INSULATOR (ISOLATOR TARIK)
        if (preg_match('/\b(hang\s*isolator|isolator\s*hang|strain\s*insulator|isolator\s*tarik|piring)\b/i', $txt)) {
            if (!$primaryKey) $primaryKey = 'HANG_ISOLATOR';
            $isThree = (bool)preg_match('/\b(3\s*(?:fasa|phase|set))\b/i', $txt);
            $hangQty = $isThree ? 3.0 : 1.0;

            $items[] = [
                'canonical_code' => 'CANON-MAT-004',
                'canonical_name' => 'Insulator - Strain Insulator 20 kV lengkap (SIR) Porcelain (Tarik)',
                'unit'           => 'buah',
                'quantity'       => $hangQty,
                'category'       => 'ISOLATOR',
                'justification'  => 'Penggantian rangkaian isolator tarik',
            ];
            $items[] = [
                'canonical_code' => 'CANON-HDW-007',
                'canonical_name' => 'STRAIN CLAMP 3 NUT',
                'unit'           => 'buah',
                'quantity'       => $hangQty,
                'category'       => 'CLAMP',
                'justification'  => 'Klem penegang konduktor pada tiang tarik',
            ];
        }

        // 4. FUSE CUT OUT (FCO)
        if (preg_match('/\b(fco|cut\s*out|dexsel|dexel|deksel|dexxel|co\s*branch|co\s*gtt|fuse)\b/i', $txt)) {
            if (!$primaryKey) $primaryKey = 'FCO';
            $items[] = [
                'canonical_code' => 'CANON-MAT-003',
                'canonical_name' => 'Polymer Cut Out Switch 24 kV + Fuse',
                'unit'           => 'buah',
                'quantity'       => 1.0,
                'category'       => 'PROTECTION',
                'justification'  => 'Penggantian unit FCO rusak/panas',
            ];
        }

        // 5. CROSS ARM / TRAVERS
        if (preg_match('/\b(cross\s*arm|crossarm|travers|kanal|unp|traves)\b/i', $txt)) {
            if (!$primaryKey) $primaryKey = 'CROSSARM';
            $items[] = [
                'canonical_code' => 'CANON-HDW-001',
                'canonical_name' => 'Cross Arm UNP 2000 mm',
                'unit'           => 'buah',
                'quantity'       => 1.0,
                'category'       => 'CROSS_ARM_TRAVERS',
                'justification'  => 'Penggantian cross-arm/travers bengkok atau berkarat parah',
            ];
            $items[] = [
                'canonical_code' => 'CANON-HDW-003',
                'canonical_name' => 'Arm Tie Type 750 - 3/4"',
                'unit'           => 'buah',
                'quantity'       => 2.0,
                'category'       => 'CROSS_ARM_TRAVERS',
                'justification'  => 'Arm tie penopang travers',
            ];
            $items[] = [
                'canonical_code' => 'CANON-HDW-005',
                'canonical_name' => 'Bolt & Nut M.16 x 50',
                'unit'           => 'buah',
                'quantity'       => 2.0,
                'category'       => 'BAUT_DAN_MUR',
                'justification'  => 'Baut pengikat arm tie ke travers',
            ];
        }

        // 6. TERMINAL LUG / SKUN / HOTSPOT JUMPER
        if (preg_match('/\b(skun|terminasi|termjnasi|therminasi|sepatu\s*kabel|bimetal|lug)\b/i', $txt)
            || preg_match('/\b(linetap|line\s*tap|lentap|pg\s*clamp|parallel\s*groove|jumperan|konektor|connector|llc|joint)\b/i', $txt)
            || preg_match('/\bhotspot\b/i', $txt)) {
            if (!$primaryKey) $primaryKey = 'SKUN_KONEKTOR';
            $items[] = [
                'canonical_code' => 'CANON-HDW-025',
                'canonical_name' => 'Terminal Lug / Bimetal Clamp 20 kV',
                'unit'           => 'buah',
                'quantity'       => 1.0,
                'category'       => 'CONNECTOR',
                'justification'  => 'Penggantian terminal lug / skun bimetal terdampak hotspot',
            ];
            $items[] = [
                'canonical_code' => 'CANON-HDW-023',
                'canonical_name' => 'PARALLEL GROOVE CLAMP 2 BAUT',
                'unit'           => 'buah',
                'quantity'       => 1.0,
                'category'       => 'CLAMP',
                'justification'  => 'Klem paralel jumper pengganti titik panas',
            ];
        }

        // 7. GROUND WIRE / GSW / ARDE
        if (preg_match('/\b(gsw|ground|arde|pentanahan|skur|schoor|kontramas)\b/i', $txt)) {
            if (!$primaryKey) $primaryKey = 'GSW_GROUNDING';
            $items[] = [
                'canonical_code' => 'CANON-HDW-022',
                'canonical_name' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',
                'unit'           => 'buah',
                'quantity'       => 1.0,
                'category'       => 'GROUNDING',
                'justification'  => 'Pemasangan elektroda pentanahan sistem',
            ];
            $items[] = [
                'canonical_code' => 'CANON-HDW-015',
                'canonical_name' => 'Ground Wire Clamp Type A',
                'unit'           => 'buah',
                'quantity'       => 1.0,
                'category'       => 'CLAMP',
                'justification'  => 'Klem pentanahan',
            ];
        }

        // 8. CONDUCTOR (RANTAS / ANDONGAN / MEKAR)
        if (preg_match('/\b(konduktor|kawat|kabel|rantas|mekar|andongan|putus)\b/i', $txt)) {
            if (!$primaryKey) $primaryKey = 'KONDUKTOR';
            $condMatCode = "MAT-COND-{$conductorType}-{$conductorSize}";
            $items[] = [
                'canonical_code' => $condMatCode,
                'canonical_name' => "Konduktor {$conductorType} {$conductorSize} mm² (20 kV)",
                'unit'           => 'meter',
                'quantity'       => 15.0,
                'category'       => 'KONDUKTOR',
                'justification'  => "Perbaikan kawat rantas / span standar 15 meter konduktor {$conductorType} {$conductorSize} mm²",
            ];
        }

        // Deduplicate items by canonical_code
        $unique = [];
        foreach ($items as $it) {
            $code = $it['canonical_code'];
            if (!isset($unique[$code])) {
                $unique[$code] = $it;
            } else {
                $unique[$code]['quantity'] += $it['quantity'];
            }
        }
        $resolvedItems = array_values($unique);

        if (!empty($resolvedItems)) {
            $summaryParts = [];
            foreach ($resolvedItems as $ri) {
                $summaryParts[] = sprintf("%.2f %s %s [%s]", $ri['quantity'], $ri['unit'], $ri['canonical_name'], $ri['canonical_code']);
            }
            return [
                'mapping_status'        => 'RESOLVED',
                'finding_component_key' => $primaryKey ?: 'KONSTRUKSI_LAIN',
                'construction_code'     => $constructionCode,
                'conductor_type'        => $conductorType,
                'conductor_size'        => $conductorSize,
                'materials'             => $resolvedItems,
                'formatted_summary'     => implode(', ', $summaryParts),
            ];
        }

        // Unmapped finding: explicitly marked as "PERLU_VERIFIKASI" rather than blank
        return [
            'mapping_status'        => 'PERLU_VERIFIKASI',
            'finding_component_key' => $primaryKey ?: 'PERLU_VERIFIKASI',
            'construction_code'     => $constructionCode,
            'conductor_type'        => $conductorType,
            'conductor_size'        => $conductorSize,
            'materials'             => [],
            'formatted_summary'     => 'Perlu Verifikasi Lapangan (Belum Terpetakan ke Katalog Standar)',
        ];
    }

    /**
     * Synchronizes and persists standard BOM materials for a specific finding.
     *
     * Transactional and idempotent: safely replaces existing automated BOM rows
     * without multiplying or corrupting manual BOM inputs.
     *
     * @param int $temuanId
     * @param bool $forceRegenerate
     * @return array<string, mixed>
     */
    public function syncTemuanMaterials(int $temuanId, bool $forceRegenerate = false): array
    {
        $temuan = $this->db->table('temuan')->where('id', $temuanId)->get()->getRowArray();
        if (!$temuan) {
            return [
                'status'  => 'error',
                'message' => "Temuan ID #{$temuanId} tidak ditemukan.",
            ];
        }

        // If manual BOM already exists and force is false, keep it
        $existingManual = $this->db->table('temuan_materials')
            ->where('temuan_id', $temuanId)
            ->where('source_mode', 'BOM_PICKER')
            ->countAllResults();

        if ($existingManual > 0 && !$forceRegenerate) {
            return [
                'status'         => 'success',
                'mapping_status' => 'MANUAL_PRESERVED',
                'message'        => 'Kebutuhan material manual dipertahankan.',
                'items_count'    => $existingManual,
            ];
        }

        // Resolve BOM requirements using the rule engine
        $bomResult = $this->resolveBomFromFinding($temuan);

        // Sanitize NOGA
        $cleanNoga = $this->sanitizeNoga($temuan['noga'] ?? null, $temuan['detail_temuan'] ?? '');

        // Resolve construction type ID from construction code
        $constructionTypeId = null;
        if (!empty($bomResult['construction_code']) && $this->db->tableExists('construction_types')) {
            $ctRow = $this->db->table('construction_types')
                ->where('code', $bomResult['construction_code'])
                ->orWhere('code', str_replace('-', '', $bomResult['construction_code']))
                ->get()
                ->getRowArray();
            if ($ctRow) {
                $constructionTypeId = (int)$ctRow['id'];
            }
        }

        // Cache master materials map: canonical_code => id
        $matIdMap = [];
        if ($this->db->tableExists('master_materials')) {
            $mmRows = $this->db->table('master_materials')->get()->getResultArray();
            foreach ($mmRows as $mm) {
                $matIdMap[$mm['material_code']] = (int)$mm['id'];
            }
        }

        $this->db->transStart();

        // 1. Delete previous automated BOM entries for this finding (idempotency guarantee)
        $this->db->table('temuan_materials')
            ->where('temuan_id', $temuanId)
            ->groupStart()
                ->where('source_mode', 'BOM_RULE_ENGINE')
                ->orWhere('source_mode', 'BOM_AUTO')
                ->orWhere('source_mode', 'BOM_CLASSIFIED')
            ->groupEnd()
            ->delete();

        if ($forceRegenerate) {
            $this->db->table('temuan_materials')
                ->where('temuan_id', $temuanId)
                ->delete();
        }

        // 2. Insert new BOM materials if resolved
        $insertedCount = 0;
        $totalQty = 0.0;
        $now = date('Y-m-d H:i:s');

        foreach ($bomResult['materials'] as $item) {
            $materialId = $matIdMap[$item['canonical_code']] ?? null;
            if (!$materialId) {
                // If material code not in master_materials, use fallback ID 15 or 1
                $materialId = 15;
            }

            $this->db->table('temuan_materials')->insert([
                'temuan_id'               => $temuanId,
                'asset_id'                => !empty($temuan['asset_id']) ? (int)$temuan['asset_id'] : null,
                'construction_type_id'    => $constructionTypeId,
                'material_id'             => $materialId,
                'canonical_code_snapshot' => $item['canonical_code'],
                'canonical_name_snapshot' => $item['canonical_name'],
                'unit_snapshot'           => $item['unit'],
                'quantity'                => (float)$item['quantity'],
                'conductor_type'          => $bomResult['conductor_type'],
                'conductor_size'          => $bomResult['conductor_size'],
                'justification_note'      => $item['justification'],
                'source_mode'             => 'BOM_RULE_ENGINE',
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);

            $insertedCount++;
            $totalQty += (float)$item['quantity'];
        }

        // 3. Update temuan row: material summary, finding_component_key, clean noga, conductor attributes
        $updateTemuan = [
            'material'              => $bomResult['formatted_summary'],
            'finding_component_key' => $bomResult['finding_component_key'],
            'conductor_type'        => $bomResult['conductor_type'],
            'conductor_size'        => $bomResult['conductor_size'],
            'noga'                  => $cleanNoga,
            'updated_at'            => $now,
        ];

        $this->db->table('temuan')->where('id', $temuanId)->update($updateTemuan);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'status'  => 'error',
                'message' => 'Gagal menyimpan transaksi BOM ke database.',
            ];
        }

        return [
            'status'                => 'success',
            'temuan_id'             => $temuanId,
            'mapping_status'        => $bomResult['mapping_status'],
            'finding_component_key' => $bomResult['finding_component_key'],
            'materials_count'       => $insertedCount,
            'total_quantity'        => $totalQty,
            'formatted_summary'     => $bomResult['formatted_summary'],
            'clean_noga'            => $cleanNoga,
        ];
    }

    /**
     * Batch synchronizes BOM requirements for findings.
     *
     * @param bool $isExecute
     * @param int|null $penyulangId
     * @return array<string, mixed>
     */
    public function batchSyncBom(bool $isExecute = false, ?int $penyulangId = null): array
    {
        $builder = $this->db->table('temuan')->where('deleted_at IS NULL');
        if ($penyulangId !== null && $penyulangId > 0) {
            $builder->where('penyulang_id', $penyulangId);
        }

        $findings = $builder->get()->getResultArray();
        $totalEvaluated = count($findings);
        $resolvedCount = 0;
        $unmappedCount = 0;
        $totalItemsGenerated = 0;
        $syncedFindings = [];

        foreach ($findings as $f) {
            $tid = (int)$f['id'];
            $bom = $this->resolveBomFromFinding($f);
            $cleanNoga = $this->sanitizeNoga($f['noga'] ?? null, $f['detail_temuan'] ?? '');
            $nogaChanged = ($cleanNoga !== ($f['noga'] ?? null));

            if ($bom['mapping_status'] === 'RESOLVED') {
                $resolvedCount++;
                $totalItemsGenerated += count($bom['materials']);
            } else {
                $unmappedCount++;
            }

            if ($isExecute) {
                $this->syncTemuanMaterials($tid, false);
            }

            if ($bom['mapping_status'] === 'RESOLVED' || $nogaChanged) {
                $syncedFindings[] = [
                    'id'            => $tid,
                    'nomor_temuan'  => $f['nomor_temuan'],
                    'old_noga'      => $f['noga'],
                    'new_noga'      => $cleanNoga,
                    'status'        => $bom['mapping_status'],
                    'component'     => $bom['finding_component_key'],
                    'summary'       => $bom['formatted_summary'],
                ];
            }
        }

        return [
            'status'                 => 'success',
            'is_execute'             => $isExecute,
            'total_evaluated'        => $totalEvaluated,
            'resolved_bom_count'     => $resolvedCount,
            'unmapped_bom_count'     => $unmappedCount,
            'total_items_generated'  => $totalItemsGenerated,
            'sample_synced'          => array_slice($syncedFindings, 0, 10),
        ];
    }

    /**
     * Finds nearest candidate poles in the same feeder for an unlinked finding.
     *
     * @param array<string, mixed> $temuan
     * @param float $maxRadiusMeters
     * @param int $limit
     * @return array<string, mixed>
     */
    public function findCandidateAssets(array $temuan, float $maxRadiusMeters = 50.0, int $limit = 5): array
    {
        $penyulangId = (int)($temuan['penyulang_id'] ?? 0);
        $lat = (float)($temuan['latitude'] ?? 0);
        $lng = (float)($temuan['longitude'] ?? 0);

        if ($lat == 0.0 || $lng == 0.0) {
            return [
                'status'     => 'no_coordinates',
                'candidates' => [],
            ];
        }

        $query = $this->db->table('assets a')
            ->select("
                a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.section_id, a.penyulang_id, a.latitude, a.longitude,
                s.nama_section, p.nama_penyulang,
                (6371000 * acos(
                    cos(radians({$lat})) * cos(radians(CAST(a.latitude AS DECIMAL(10,8)))) *
                    cos(radians(CAST(a.longitude AS DECIMAL(11,8))) - radians({$lng})) +
                    sin(radians({$lat})) * sin(radians(CAST(a.latitude AS DECIMAL(10,8))))
                )) AS distance_meters
            ")
            ->join('sections s', 's.id = a.section_id', 'left')
            ->join('penyulang p', 'p.id = a.penyulang_id', 'left')
            ->where('a.deleted_at IS NULL')
            ->where('a.latitude !=', 0)
            ->where('a.longitude !=', 0);

        if ($penyulangId > 0) {
            $query->where('a.penyulang_id', $penyulangId);
        }

        $query->having('distance_meters <=', $maxRadiusMeters)
            ->orderBy('distance_meters', 'ASC')
            ->limit($limit);

        $candidates = $query->get()->getResultArray();

        // Check if any candidate name matches pole keywords in detail_temuan
        $detailText = strtoupper((string)($temuan['detail_temuan'] ?? ''));
        foreach ($candidates as &$c) {
            $c['distance_meters'] = round((float)$c['distance_meters'], 1);
            $cName = strtoupper($c['nama_asset']);
            $cCode = strtoupper($c['kode_asset']);
            $c['name_match'] = false;
            if (str_contains($detailText, $cName) || str_contains($detailText, $cCode)) {
                $c['name_match'] = true;
            }
        }
        unset($c);

        $isDeterministic = (count($candidates) === 1 && $candidates[0]['distance_meters'] <= 25.0);
        $isAmbiguous = (count($candidates) > 1);

        return [
            'status'           => 'success',
            'candidates_count' => count($candidates),
            'is_deterministic' => $isDeterministic,
            'is_ambiguous'     => $isAmbiguous,
            'candidates'       => $candidates,
        ];
    }

    /**
     * Transactionally links a finding to an authoritative network asset.
     *
     * @param int $temuanId
     * @param int $assetId
     * @param int|null $actorUserId
     * @return array<string, mixed>
     */
    public function linkFindingToAsset(int $temuanId, int $assetId, ?int $actorUserId = null): array
    {
        $temuan = $this->db->table('temuan')->where('id', $temuanId)->where('deleted_at IS NULL')->get()->getRowArray();
        if (!$temuan) {
            return ['status' => 'error', 'message' => "Temuan #{$temuanId} tidak ditemukan."];
        }

        $asset = $this->db->table('assets a')
            ->select('a.*, p.nama_penyulang, s.nama_section')
            ->join('penyulang p', 'p.id = a.penyulang_id', 'left')
            ->join('sections s', 's.id = a.section_id', 'left')
            ->where('a.id', $assetId)
            ->where('a.deleted_at IS NULL')
            ->get()
            ->getRowArray();

        if (!$asset) {
            return ['status' => 'error', 'message' => "Aset #{$assetId} tidak ditemukan."];
        }

        // Feeder alignment check
        if (!empty($temuan['penyulang_id']) && !empty($asset['penyulang_id']) && (int)$temuan['penyulang_id'] !== (int)$asset['penyulang_id']) {
            return [
                'status'  => 'error',
                'message' => "Aset berada pada penyulang berbeda ({$asset['nama_penyulang']}). Penautan lintas penyulang dilarang.",
            ];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->transStart();

        // Update temuan with authoritative asset ID and verified coordinates
        $updateData = [
            'asset_id'     => $assetId,
            'section_id'   => !empty($asset['section_id']) ? (int)$asset['section_id'] : $temuan['section_id'],
            'penyulang_id' => !empty($asset['penyulang_id']) ? (int)$asset['penyulang_id'] : $temuan['penyulang_id'],
            'updated_by'   => $actorUserId,
            'updated_at'   => $now,
        ];

        if (!empty($asset['latitude']) && !empty($asset['longitude']) && (float)$asset['latitude'] != 0.0) {
            $updateData['latitude']  = (float)$asset['latitude'];
            $updateData['longitude'] = (float)$asset['longitude'];
        }

        // Clean noga if it was accidentally holding a tiang identity
        $cleanNoga = $this->sanitizeNoga($temuan['noga'] ?? null, $temuan['detail_temuan'] ?? '');
        $updateData['noga'] = $cleanNoga;

        $this->db->table('temuan')->where('id', $temuanId)->update($updateData);

        // Update temuan_materials asset_id linkage
        if ($this->db->tableExists('temuan_materials')) {
            $this->db->table('temuan_materials')
                ->where('temuan_id', $temuanId)
                ->update(['asset_id' => $assetId, 'updated_at' => $now]);
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['status' => 'error', 'message' => 'Gagal menautkan temuan ke aset pada database.'];
        }

        return [
            'status'         => 'success',
            'message'        => "Temuan #{$temuanId} berhasil ditautkan ke aset {$asset['kode_asset']} ({$asset['nama_asset']}).",
            'asset_id'       => $assetId,
            'kode_asset'     => $asset['kode_asset'],
            'nama_asset'     => $asset['nama_asset'],
            'nama_penyulang' => $asset['nama_penyulang'],
            'nama_section'   => $asset['nama_section'],
            'latitude'       => $updateData['latitude'] ?? $temuan['latitude'],
            'longitude'      => $updateData['longitude'] ?? $temuan['longitude'],
        ];
    }

    /**
     * Audits and prunes illegal cross-feeder translines.
     *
     * Invariant: Every transline connecting source_asset_id and target_asset_id
     * MUST connect poles within the SAME feeder. Cross-feeder bridges based on mere
     * coordinate proximity are strictly forbidden.
     *
     * @param bool $isExecute
     * @return array<string, mixed>
     */
    public function auditCrossFeederTranslines(bool $isExecute = false): array
    {
        if (!$this->db->tableExists('gis_translines') || !$this->db->tableExists('assets')) {
            return ['status' => 'error', 'message' => 'Tabel gis_translines atau assets tidak tersedia.'];
        }

        $sql = "
            SELECT t.id, t.transline_code, t.penyulang_id, t.source_asset_id, t.target_asset_id,
                   a1.penyulang_id AS a1_penyulang_id, a2.penyulang_id AS a2_penyulang_id,
                   a1.kode_asset AS a1_kode, a2.kode_asset AS a2_kode,
                   COALESCE(p1.nama_penyulang, '-') AS a1_penyulang,
                   COALESCE(p2.nama_penyulang, '-') AS a2_penyulang
            FROM gis_translines t
            JOIN assets a1 ON a1.id = t.source_asset_id
            JOIN assets a2 ON a2.id = t.target_asset_id
            LEFT JOIN penyulang p1 ON p1.id = a1.penyulang_id
            LEFT JOIN penyulang p2 ON p2.id = a2.penyulang_id
            WHERE t.deleted_at IS NULL
              AND (a1.penyulang_id != a2.penyulang_id OR t.penyulang_id != a1.penyulang_id OR t.penyulang_id != a2.penyulang_id)
        ";

        $illegalRows = $this->db->query($sql)->getResultArray();
        $count = count($illegalRows);
        $prunedCount = 0;

        if ($isExecute && $count > 0) {
            $ids = array_column($illegalRows, 'id');
            $now = date('Y-m-d H:i:s');
            $this->db->table('gis_translines')
                ->whereIn('id', $ids)
                ->update(['deleted_at' => $now, 'is_active' => 0]);
            $prunedCount = $count;
        }

        // Also check proposals
        $proposalBlockedCount = 0;
        if ($this->db->tableExists('gis_transline_proposals')) {
            $sqlProp = "
                SELECT tp.id
                FROM gis_transline_proposals tp
                JOIN assets a1 ON a1.id = tp.source_asset_id
                JOIN assets a2 ON a2.id = tp.target_asset_id
                WHERE a1.penyulang_id != a2.penyulang_id
                  AND tp.canonical_operational_state != 'BLOCKED'
            ";
            $badProps = $this->db->query($sqlProp)->getResultArray();
            if ($isExecute && !empty($badProps)) {
                $propIds = array_column($badProps, 'id');
                $this->db->table('gis_transline_proposals')
                    ->whereIn('id', $propIds)
                    ->update([
                        'canonical_operational_state' => 'BLOCKED',
                        'anomaly_reason'              => 'Cross-feeder connection between different feeders is forbidden.',
                    ]);
                $proposalBlockedCount = count($badProps);
            }
        }

        return [
            'status'                 => 'success',
            'is_execute'             => $isExecute,
            'cross_feeder_count'     => $count,
            'pruned_translines'      => $prunedCount,
            'blocked_proposals'      => $proposalBlockedCount,
            'sample_cross_feeders'   => array_slice($illegalRows, 0, 10),
        ];
    }
}
