<?php
$helperFile = __DIR__ . '/method_b22.php';
$helperCode = file_get_contents($helperFile);

// Extract the method
$startPos = strpos($helperCode, '    public function b22ProductionLockAudit()');
$endPos = strrpos($helperCode, '}'); // class closing brace
$methodCode = substr($helperCode, $startPos, $endPos - $startPos);

$docBlock = <<<DOC
    /**
     * Phase B.2.2: Post-Commit Forensic & Production Lock Verification Engine
     * Executes 7 strictly read-only audits against live production network state:
     * 1. Authoritative Invariant (243 = 217 Old Preserved + 26 New Active)
     * 2. Fingerprint Uniqueness (243 = 243 Distinct Canonical Edge Fingerprints)
     * 3. Spatial Integrity (Distance > 0, No Self Loops, Coordinate Bounds Valid)
     * 4. Graph Boundary Integrity (0 Cross-Feeder, 0 Cross-ULP, 0 Section Violations)
     * 5. Asset Immutability (parent_asset_id & section_id Delta = 0)
     * 6. GIS Network Truth (Feeder 118 Bahagia Steel 1 = 1 TL, Tiang 20->21 Blocked)
     * 7. Conductor Analytics & Canonical Registry Normalization
     */

DOC;

$fullMethod = "\n" . $docBlock . $methodCode . "\n";

$targetFile = __DIR__ . '/../app/Controllers/MigrateController.php';
$controllerCode = file_get_contents($targetFile);

$needle = '    public function getB2AcceptedCandidates(): array';
$pos = strpos($controllerCode, $needle);
if ($pos === false) {
    die("Needle not found in MigrateController.php\n");
}

$newCode = substr($controllerCode, 0, $pos) . $fullMethod . substr($controllerCode, $pos);
file_put_contents($targetFile, $newCode);
echo "Injected b22ProductionLockAudit into MigrateController.php!\n";

$res = exec('php -l ' . escapeshellarg($targetFile), $output, $ret);
echo implode("\n", $output) . "\n";
if ($ret === 0) {
    echo "SYNTAX OK!\n";
} else {
    echo "SYNTAX ERROR!\n";
}
